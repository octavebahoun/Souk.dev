<?php

namespace App\Paiement;

use App\Models\Abonnement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\Facture;
use App\Models\User;
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
     * après l'arrêt). La copie est arrêtée par l'appelant.
     */
    public function resilier(Abonnement $abonnement): void
    {
        DB::transaction(function () use ($abonnement): void {
            $verrouille = Abonnement::query()->lockForUpdate()->findOrFail($abonnement->id);

            if ($verrouille->statut === 'resilie') {
                return;
            }

            $verrouille->forceFill(['statut' => 'resilie', 'resilie_le' => now(), 'prochaine_echeance' => null])->save();
            $verrouille->factures()->where('statut', 'a_payer')->update(['statut' => 'annulee', 'updated_at' => now()]);
        });

        $abonnement->refresh();
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
