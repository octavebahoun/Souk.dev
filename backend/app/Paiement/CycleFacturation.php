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
use Illuminate\Support\Facades\DB;

/**
 * Passage quotidien de la facturation, à date anniversaire :
 * facture émise et rappel 3 jours avant l'échéance (lien de paiement), relance le lendemain
 * de l'échéance, suspension après 7 jours de grâce, avertissement 7 jours avant l'arrêt,
 * arrêt définitif après 30 jours de suspension. Une commande jamais payée est annulée après
 * 7 jours. Les délais se comptent en jours de calendrier ; chaque étape est idempotente :
 * relancer le passage le même jour ne fait rien de plus.
 */
final class CycleFacturation
{
    public function __construct(private Facturation $facturation) {}

    /**
     * @return array{emises: int, relancees: int, suspendues: int, averties: int, resiliees: int, annulees: int}
     */
    public function executer(?CarbonImmutable $maintenant = null): array
    {
        $maintenant ??= CarbonImmutable::now();
        $aujourdhui = $maintenant->startOfDay();

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

        Abonnement::query()
            ->whereIn('statut', Abonnement::STATUTS_ACTIFS)
            ->whereNotNull('prochaine_echeance')
            ->whereDate('prochaine_echeance', '<=', $limite->toDateString())
            ->orderBy('id')
            ->each(function (Abonnement $abonnement) use ($limite, &$emises): void {
                $facture = DB::transaction(function () use ($abonnement, $limite): ?Facture {
                    $verrouille = Abonnement::query()->lockForUpdate()->find($abonnement->id);

                    if ($verrouille === null || ! in_array($verrouille->statut, Abonnement::STATUTS_ACTIFS, true)
                        || $verrouille->prochaine_echeance === null || $verrouille->prochaine_echeance->gt($limite)) {
                        return null;
                    }

                    $debut = $verrouille->prochaine_echeance->copy();
                    $facture = $verrouille->factures()->whereDate('periode_debut', $debut->toDateString())->first()
                        ?? $this->facturation->emettre($verrouille, $debut);
                    $verrouille->forceFill(['prochaine_echeance' => $debut->copy()->addMonthNoOverflow()])->save();

                    return $facture;
                });

                if ($facture !== null && $facture->rappel_envoye_le === null && $facture->statut === 'a_payer') {
                    $facture->forceFill(['rappel_envoye_le' => now()])->save();
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
            ->with('abonnement', 'client')
            ->orderBy('id')
            ->each(function (Facture $facture) use (&$relancees): void {
                $facture->forceFill(['retard_signale_le' => now()])->save();
                $facture->abonnement->forceFill(['statut' => 'en_retard'])->save();
                $facture->client?->notify(new FactureEnRetard($facture));
                $relancees++;
            });

        return $relancees;
    }

    /** Fin des 7 jours de grâce : conteneurs arrêtés (données gardées), page « copie suspendue ». */
    private function suspendre(CarbonImmutable $aujourdhui): int
    {
        $limite = $aujourdhui->subDays((int) config('facturation.jours_grace'));
        $suspendues = 0;

        Abonnement::query()
            ->whereIn('statut', Abonnement::STATUTS_ACTIFS)
            ->whereHas('factures', fn ($facture) => $facture->where('statut', 'a_payer')->whereDate('echeance_le', '<=', $limite->toDateString()))
            ->with('deploiement', 'client')
            ->orderBy('id')
            ->each(function (Abonnement $abonnement) use (&$suspendues): void {
                $abonnement->forceFill(['statut' => 'suspendu', 'suspendu_le' => now(), 'avertissement_arret_le' => null])->save();

                if ($abonnement->deploiement !== null) {
                    SuspendreCopie::dispatch($abonnement->deploiement->id);
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
        $jours = (int) config('facturation.jours_avant_arret') - (int) config('facturation.jours_avertissement_arret');
        $averties = 0;

        Abonnement::query()
            ->where('statut', 'suspendu')
            ->whereNull('avertissement_arret_le')
            ->whereDate('suspendu_le', '<=', $aujourdhui->subDays($jours)->toDateString())
            ->with('client')
            ->orderBy('id')
            ->each(function (Abonnement $abonnement) use (&$averties): void {
                $abonnement->forceFill(['avertissement_arret_le' => now()])->save();
                $abonnement->client?->notify(new AvertissementArret($abonnement));
                $averties++;
            });

        return $averties;
    }

    /** 30 jours de suspension : arrêt définitif, volumes supprimés, impayés annulés. */
    private function resilierLesSuspendues(CarbonImmutable $aujourdhui): int
    {
        $resiliees = 0;

        Abonnement::query()
            ->where('statut', 'suspendu')
            ->whereDate('suspendu_le', '<=', $aujourdhui->subDays((int) config('facturation.jours_avant_arret'))->toDateString())
            ->with('deploiement', 'client')
            ->orderBy('id')
            ->each(function (Abonnement $abonnement) use (&$resiliees): void {
                $this->facturation->resilier($abonnement);
                $abonnement->deploiement?->demanderArret();
                $abonnement->client?->notify(new CopieResiliee($abonnement));
                $resiliees++;
            });

        return $resiliees;
    }

    /** Première facture impayée après 7 jours : la commande est annulée, rien n'avait démarré. */
    private function annulerLesCommandesImpayees(CarbonImmutable $aujourdhui): int
    {
        $annulees = 0;

        Abonnement::query()
            ->where('statut', 'en_attente')
            ->whereDate('created_at', '<=', $aujourdhui->subDays((int) config('facturation.jours_grace'))->toDateString())
            ->with('deploiement', 'client')
            ->orderBy('id')
            ->each(function (Abonnement $abonnement) use (&$annulees): void {
                $this->facturation->resilier($abonnement);

                if ($abonnement->deploiement?->etat === 'attente_paiement') {
                    $abonnement->deploiement->changerEtat('arrete');
                }

                $abonnement->client?->notify(new CopieResiliee($abonnement, jamaisPayee: true));
                $annulees++;
            });

        return $annulees;
    }
}
