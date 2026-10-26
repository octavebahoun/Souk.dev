<?php

namespace App\Paiement;

use App\Models\Abonnement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\Facture;
use App\Models\User;
use App\Notifications\AlerteAdmin;
use App\Notifications\CopieEnIncident;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Commande d'une copie client et factures à date anniversaire : l'hébergement chaque mois,
 * le prix de l'appli chaque mois (mensuel) ou sur la première facture seulement (unique).
 * Aucun montant ne vient du navigateur : tout part de l'appli et de la formule.
 */
final class Facturation
{
    /**
     * @param  array<string, string>  $variables
     */
    public function commander(Appli $appli, User $client, array $variables): Deploiement
    {
        $formule = (string) $appli->taille;
        $hebergement = $this->prixHebergement($formule);

        return DB::transaction(function () use ($appli, $client, $variables, $formule, $hebergement): Deploiement {
            $deploiement = Deploiement::query()->create([
                'user_id' => $client->id,
                'appli_id' => $appli->id,
                'type' => 'client',
                'taille' => $formule,
                'etat' => 'attente_paiement',
                'variables' => $variables,
            ]);
            // La copie démarrera sur la dernière version publiée, et y restera jusqu'à ce que son client en change.
            $deploiement->forceFill(['version_id' => $appli->versionPubliee?->id])->save();

            $abonnement = Abonnement::query()->create([
                'deploiement_id' => $deploiement->id,
                'client_id' => $client->id,
                'dev_id' => $appli->user_id,
                'appli_id' => $appli->id,
                'formule' => $formule,
                'montant_hebergement' => $hebergement,
                // L'auteur qui déploie sa propre appli ne se paie pas lui-même.
                'prix_appli' => $appli->user_id === $client->id ? 0 : (int) $appli->prix,
                'type_prix' => (string) $appli->type_prix,
                'statut' => 'en_attente',
            ]);

            $this->emettre($abonnement, now()->startOfDay(), premiere: true);

            return $deploiement;
        });
    }

    /** Facture d'une période d'un mois qui commence à $debut (échéance le jour même). */
    public function emettre(Abonnement $abonnement, CarbonInterface $debut, bool $premiere = false): Facture
    {
        $debut = $debut->copy()->startOfDay();
        $appli = $abonnement->prix_appli > 0 && ($abonnement->type_prix === 'mensuel' || $premiere) ? $abonnement->prix_appli : 0;

        $facture = Facture::query()->create([
            'abonnement_id' => $abonnement->id,
            'client_id' => $abonnement->client_id,
            'periode_debut' => $debut->toDateString(),
            'periode_fin' => $debut->copy()->addMonthNoOverflow()->subDay()->toDateString(),
            'echeance_le' => $debut->toDateString(),
            'montant_hebergement' => $abonnement->montant_hebergement,
            'montant_appli' => $appli,
            'montant_total' => $abonnement->montant_hebergement + $appli,
            'statut' => 'a_payer',
        ]);
        $facture->numeroter();

        return $facture;
    }

    /**
     * Fin de l'abonnement : plus aucune facture, les impayés sont annulés (rien n'est réclamé
     * après l'arrêt). La copie est arrêtée par l'appelant. $seulementSi : les statuts dans
     * lesquels la résiliation a encore lieu (un paiement arrivé entre-temps l'emporte).
     *
     * @param  list<string>|null  $seulementSi
     */
    public function resilier(Abonnement $abonnement, ?array $seulementSi = null): void
    {
        DB::transaction(function () use ($abonnement, $seulementSi): void {
            $verrouille = Abonnement::query()->lockForUpdate()->findOrFail($abonnement->id);

            if ($verrouille->statut === 'resilie' || ($seulementSi !== null && ! in_array($verrouille->statut, $seulementSi, true))) {
                return;
            }

            $verrouille->forceFill(['statut' => 'resilie', 'resilie_le' => now(), 'prochaine_echeance' => null])->save();
            $verrouille->factures()->where('statut', 'a_payer')->update(['statut' => 'annulee', 'updated_at' => now()]);
        });

        $abonnement->refresh();
    }

    /**
     * Copie payée qui ne démarre pas (ou ne repart pas après un paiement) : plus aucune facture
     * tant que l'équipe n'a pas réglé le problème, l'équipe et le client sont prévenus.
     */
    public function signalerIncident(Deploiement $deploiement, string $raison): void
    {
        $abonnement = $deploiement->abonnement()->first();

        if ($abonnement === null) {
            return;
        }

        $signale = DB::transaction(function () use ($abonnement): bool {
            $verrouille = Abonnement::query()->lockForUpdate()->find($abonnement->id);

            if (! in_array($verrouille?->statut, ['actif', 'en_retard'], true)) {
                return false;
            }

            $verrouille->forceFill(['statut' => 'incident'])->save();

            return true;
        });

        if (! $signale) {
            return;
        }

        AlerteAdmin::envoyer("Copie payée n° {$deploiement->id} qui ne démarre pas", [
            "{$deploiement->versApi()['app_nom']}, pour le compte n° {$deploiement->user_id} : {$raison}",
            'Plus aucune facture n’est émise pour cette copie. Relancez-la, ou remboursez le client depuis SasPay puis saisissez un avoir.',
        ]);
        $abonnement->client?->notify(new CopieEnIncident($abonnement));
    }

    /** La copie tourne enfin : l'abonnement reprend son cours normal. */
    public function leverIncident(Deploiement $deploiement): void
    {
        Abonnement::query()->where('deploiement_id', $deploiement->id)->where('statut', 'incident')->update(['statut' => 'actif', 'updated_at' => now()]);
    }

    public function prixHebergement(string $formule): int
    {
        $prix = config('facturation.hebergement.'.$formule);

        if (! is_int($prix) || $prix <= 0) {
            throw new InvalidArgumentException("Formule d'hébergement inconnue : {$formule}.");
        }

        return $prix;
    }

    /**
     * Lignes lisibles d'une facture (« Hébergement, formule moyenne, du 25 octobre au 24 novembre »).
     *
     * @return list<array{libelle: string, montant: int}>
     */
    public function lignes(Facture $facture): array
    {
        $facture->loadMissing('abonnement.appli');
        $abonnement = $facture->abonnement;
        $periode = 'du '.$facture->periode_debut->locale('fr')->isoFormat('D MMMM').' au '.$facture->periode_fin->locale('fr')->isoFormat('D MMMM YYYY');
        $lignes = [[
            'libelle' => "Hébergement de la copie, formule {$abonnement->formule}, {$periode}",
            'montant' => $facture->montant_hebergement,
        ]];

        if ($facture->montant_appli > 0) {
            $nom = $abonnement->appli?->nom ?? 'l’appli';
            $lignes[] = [
                'libelle' => $abonnement->type_prix === 'unique' ? "Licence de {$nom} (paiement unique)" : "Abonnement à {$nom}, {$periode}",
                'montant' => $facture->montant_appli,
            ];
        }

        return $lignes;
    }
}
