<?php

namespace App\Paiement;

use App\Jobs\SuspendreCopie;
use App\Models\Abonnement;
use App\Models\Facture;
use App\Notifications\AvertissementArret;
use App\Notifications\CopieResiliee;
use App\Notifications\CopieSuspendue;
use App\Notifications\FactureEmise;
use App\Notifications\FactureEnRetard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Passage quotidien de la facturation, à date anniversaire :
 * facture émise et rappel 3 jours avant l'échéance (lien de paiement), relance le lendemain
 * de l'échéance, suspension après 7 jours de grâce, avertissement 7 jours avant l'arrêt,
 * arrêt définitif après 30 jours de suspension. Une commande jamais payée est annulée après
 * 7 jours. Les délais se comptent en jours de calendrier ; chaque étape est idempotente :
 * relancer le passage le même jour ne fait rien de plus.
 *
 * Chaque étape verrouille l'abonnement et revérifie ses impayés avant d'écrire : un client qui
 * paie pendant le passage (Encaissement verrouille la même ligne) n'est jamais suspendu ni
 * arrêté à tort. Les e-mails et les jobs du moteur partent après la transaction.
 */
final class CycleFacturation
{
    public function __construct(private Facturation $facturation) {}

    /**
     * @return array{emises: int, relancees: int, suspendues: int, averties: int, resiliees: int, annulees: int}
     */
    public function executer(?CarbonImmutable $maintenant = null): array
    {
        $aujourdhui = ($maintenant ?? CarbonImmutable::now())->startOfDay();

        return [
            'emises' => $this->emettre($aujourdhui),
            'relancees' => $this->relancer($aujourdhui),
            'suspendues' => $this->suspendre($aujourdhui),
            'averties' => $this->avertir($aujourdhui),
            'resiliees' => $this->resilierLesSuspendues($aujourdhui),
            'annulees' => $this->annulerLesCommandesImpayees($aujourdhui),
        ];
    }

    /** Prochaine période facturée 3 jours avant son échéance, rappel envoyé avec le lien de paiement. */
    private function emettre(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->addDays((int) config('facturation.jours_rappel'));
        $emises = 0;

        $this->candidats(fn ($requete) => $requete
            ->whereIn('statut', Abonnement::STATUTS_ACTIFS)
            ->whereNotNull('prochaine_echeance')
            ->whereDate('prochaine_echeance', '<=', $limite->toDateString()))
            ->each(function (int $id) use ($limite, &$emises): void {
                $facture = DB::transaction(function () use ($id, $limite): ?Facture {
                    $abonnement = Abonnement::query()->lockForUpdate()->find($id);

                    if ($abonnement === null || ! in_array($abonnement->statut, Abonnement::STATUTS_ACTIFS, true)
                        || $abonnement->prochaine_echeance === null || $abonnement->prochaine_echeance->gt($limite)) {
                        return null;
                    }

                    $debut = $abonnement->prochaine_echeance->copy();
                    $abonnement->forceFill(['prochaine_echeance' => $debut->copy()->addMonthNoOverflow()])->save();

                    // Déjà émise (passage interrompu) : on ne la touche pas, un paiement peut la tenir verrouillée.
                    if ($abonnement->factures()->whereDate('periode_debut', $debut->toDateString())->exists()) {
                        return null;
                    }

                    $facture = $this->facturation->emettre($abonnement, $debut);
                    $facture->forceFill(['rappel_envoye_le' => now()])->save();

                    return $facture;
                });

                if ($facture !== null) {
                    $facture->client?->notify(new FactureEmise($facture));
                    $emises++;
                }
            });

        return $emises;
    }

    /** Lendemain de l'échéance : l'abonnement passe en retard, le client est relancé une fois. */
    private function relancer(CarbonImmutable $aujourdhui): int
    {
        $relancees = 0;

        Facture::query()
            ->where('statut', 'a_payer')
            ->whereNull('retard_signale_le')
            ->whereDate('echeance_le', '<', $aujourdhui->toDateString())
            ->whereHas('abonnement', fn ($abonnement) => $abonnement->whereIn('statut', Abonnement::STATUTS_ACTIFS))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use ($aujourdhui, &$relancees): void {
                $facture = DB::transaction(function () use ($id, $aujourdhui): ?Facture {
                    // Même ordre de verrous que le paiement (facture, puis abonnement) : pas d'interblocage.
                    $facture = Facture::query()->lockForUpdate()->find($id);
                    $abonnement = $facture ? Abonnement::query()->lockForUpdate()->find($facture->abonnement_id) : null;

                    if ($facture === null || $abonnement === null || $facture->statut !== 'a_payer' || $facture->retard_signale_le !== null
                        || ! $facture->echeance_le->lt($aujourdhui) || ! in_array($abonnement->statut, Abonnement::STATUTS_ACTIFS, true)) {
                        return null;
                    }

                    $facture->forceFill(['retard_signale_le' => now()])->save();
                    $abonnement->forceFill(['statut' => 'en_retard'])->save();

                    return $facture;
                });

                if ($facture !== null) {
                    $facture->client?->notify(new FactureEnRetard($facture));
                    $relancees++;
                }
            });

        return $relancees;
    }

    /** Fin des 7 jours de grâce : conteneurs arrêtés (données gardées), page « copie suspendue ». */
    private function suspendre(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->subDays((int) config('facturation.jours_grace'));
        $suspendues = 0;

        $this->candidats(fn ($requete) => $requete
            ->whereIn('statut', Abonnement::STATUTS_ACTIFS)
            ->whereHas('factures', fn ($facture) => $this->echue($facture, $limite)))
            ->each(function (int $id) use ($limite, &$suspendues): void {
                $abonnement = DB::transaction(function () use ($id, $limite): ?Abonnement {
                    $abonnement = Abonnement::query()->lockForUpdate()->find($id);

                    if ($abonnement === null || ! in_array($abonnement->statut, Abonnement::STATUTS_ACTIFS, true)
                        || ! $abonnement->factures()->where(fn ($facture) => $this->echue($facture, $limite))->exists()) {
                        return null;
                    }

                    $abonnement->forceFill(['statut' => 'suspendu', 'suspendu_le' => now(), 'avertissement_arret_le' => null])->save();

                    return $abonnement;
                });

                if ($abonnement === null) {
                    return;
                }

                if ($abonnement->deploiement_id !== null) {
                    SuspendreCopie::dispatch($abonnement->deploiement_id);
                }

                $facture = $abonnement->facturesAPayer()->first();

                if ($facture !== null) {
                    $abonnement->client?->notify(new CopieSuspendue($facture));
                }

                $suspendues++;
            });

        return $suspendues;
    }

    /** 7 jours avant l'arrêt définitif : dernier avertissement par e-mail. */
    private function avertir(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->subDays((int) config('facturation.jours_avant_arret') - (int) config('facturation.jours_avertissement_arret'));
        $averties = 0;

        $this->candidats(fn ($requete) => $requete
            ->where('statut', 'suspendu')
            ->whereNull('avertissement_arret_le')
            ->whereDate('suspendu_le', '<=', $limite->toDateString()))
            ->each(function (int $id) use ($limite, &$averties): void {
                $abonnement = DB::transaction(function () use ($id, $limite): ?Abonnement {
                    $abonnement = Abonnement::query()->lockForUpdate()->find($id);

                    if ($abonnement === null || $abonnement->statut !== 'suspendu' || $abonnement->avertissement_arret_le !== null
                        || $abonnement->suspendu_le === null || $abonnement->suspendu_le->startOfDay()->gt($limite)) {
                        return null;
                    }

                    $abonnement->forceFill(['avertissement_arret_le' => now()])->save();

                    return $abonnement;
                });

                if ($abonnement !== null) {
                    $abonnement->client?->notify(new AvertissementArret($abonnement));
                    $averties++;
                }
            });

        return $averties;
    }

    /** 30 jours de suspension : arrêt définitif, volumes supprimés, impayés annulés. */
    private function resilierLesSuspendues(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->subDays((int) config('facturation.jours_avant_arret'));
        $resiliees = 0;

        $this->candidats(fn ($requete) => $requete
            ->where('statut', 'suspendu')
            ->whereDate('suspendu_le', '<=', $limite->toDateString()))
            ->each(function (int $id) use ($limite, &$resiliees): void {
                $abonnement = DB::transaction(function () use ($id, $limite): ?Abonnement {
                    $abonnement = Abonnement::query()->lockForUpdate()->find($id);

                    // Toujours suspendu, et toujours un impayé : un paiement arrivé entre-temps l'emporte.
                    if ($abonnement === null || $abonnement->statut !== 'suspendu' || $abonnement->suspendu_le === null
                        || $abonnement->suspendu_le->startOfDay()->gt($limite) || ! $abonnement->factures()->where('statut', 'a_payer')->exists()) {
                        return null;
                    }

                    $this->facturation->resilier($abonnement, ['suspendu']);

                    return $abonnement;
                });

                if ($abonnement === null) {
                    return;
                }

                $abonnement->deploiement?->demanderArret();
                $abonnement->client?->notify(new CopieResiliee($abonnement));
                $resiliees++;
            });

        return $resiliees;
    }

    /** Première facture impayée après 7 jours : la commande est annulée, rien n'avait démarré. */
    private function annulerLesCommandesImpayees(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->subDays((int) config('facturation.jours_grace'));
        $annulees = 0;

        $this->candidats(fn ($requete) => $requete
            ->where('statut', 'en_attente')
            ->whereDate('created_at', '<=', $limite->toDateString()))
            ->each(function (int $id) use (&$annulees): void {
                $abonnement = DB::transaction(function () use ($id): ?Abonnement {
                    $abonnement = Abonnement::query()->lockForUpdate()->find($id);

                    if ($abonnement === null || $abonnement->statut !== 'en_attente') {
                        return null;
                    }

                    $this->facturation->resilier($abonnement, ['en_attente']);

                    return $abonnement;
                });

                if ($abonnement === null) {
                    return;
                }

                if ($abonnement->deploiement?->etat === 'attente_paiement') {
                    $abonnement->deploiement->changerEtat('arrete');
                }

                $abonnement->client?->notify(new CopieResiliee($abonnement, jamaisPayee: true));
                $annulees++;
            });

        return $annulees;
    }

    /**
     * Identifiants des abonnements à examiner (relus sous verrou à l'étape suivante).
     *
     * @param  callable(Builder<Abonnement>): mixed  $filtre
     * @return Collection<int, int>
     */
    private function candidats(callable $filtre): Collection
    {
        $requete = Abonnement::query()->orderBy('id');
        $filtre($requete);

        return $requete->pluck('id');
    }

    /** Facture à payer dont l'échéance est passée depuis la grâce. */
    private function echue($facture, CarbonImmutable $limite)
    {
        return $facture->where('statut', 'a_payer')->whereDate('echeance_le', '<=', $limite->toDateString());
    }
}
