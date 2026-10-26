<?php

namespace App\Paiement;

use App\Models\Avoir;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Reversement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Grand livre en partie double, en entiers FCFA. Chaque opération s'équilibre (somme nulle) ;
 * positif au débit, négatif au crédit. Rien n'est jamais modifié : une erreur se corrige par
 * une écriture inverse (contrepassation).
 *
 * Comptes : saspay (argent chez SasPay), dev:{id} (dû au dev), revenu_hebergement, frais de
 * reversement (charge de Souk.dev), trop_percu (dû à un client qui a payé deux fois).
 */
final class GrandLivre
{
    public const COMPTE_TROP_PERCU = 'trop_percu';

    /**
     * Facture payée : Souk.dev reçoit le montant plein (frais en ADD_ON payés par le client).
     * La part de l'appli va entièrement au dev, sans commission ; l'hébergement reste à Souk.dev.
     * Si SasPay a quand même retenu des frais, ils sont une charge de Souk.dev, jamais du dev.
     */
    public function encaisser(Facture $facture, Paiement $paiement, int $fraisRetenus = 0): void
    {
        $facture->loadMissing('abonnement');
        $libelle = "Facture {$facture->numero} payée";

        $this->passer('encaissement', [
            ['compte' => Ecriture::COMPTE_SASPAY, 'montant' => $facture->montant_total - $fraisRetenus],
            ['compte' => Ecriture::COMPTE_FRAIS_ENCAISSEMENT, 'montant' => $fraisRetenus],
            ['compte' => Ecriture::COMPTE_REVENU_HEBERGEMENT, 'montant' => -$facture->montant_hebergement],
            ['compte' => Ecriture::COMPTE_DEV, 'dev_id' => $facture->abonnement->dev_id, 'montant' => -$facture->montant_appli],
        ], $libelle, ['facture_id' => $facture->id, 'paiement_id' => $paiement->id]);
    }

    /** Second paiement réussi d'une facture déjà payée : l'argent est dû au client (avoir à faire). */
    public function tropPercu(Facture $facture, Paiement $paiement): void
    {
        $this->passer('trop_percu', [
            ['compte' => Ecriture::COMPTE_SASPAY, 'montant' => $paiement->montant],
            ['compte' => self::COMPTE_TROP_PERCU, 'montant' => -$paiement->montant],
        ], "Paiement en double de la facture {$facture->numero}", ['facture_id' => $facture->id, 'paiement_id' => $paiement->id]);
    }

    /** Reversement initié : le solde du dev baisse tout de suite, l'argent quitte le compte SasPay. */
    public function reserverReversement(Reversement $reversement): void
    {
        $this->passer('reversement', [
            ['compte' => Ecriture::COMPTE_DEV, 'dev_id' => $reversement->dev_id, 'montant' => $reversement->montant],
            ['compte' => Ecriture::COMPTE_SASPAY, 'montant' => -$reversement->montant],
        ], "Reversement n° {$reversement->id} sur Mobile Money", ['reversement_id' => $reversement->id]);
    }

    /** Frais du payout, connus à la vérification : pris sur la marge de Souk.dev, jamais sur le dev. */
    public function fraisReversement(Reversement $reversement, int $frais): void
    {
        if ($frais <= 0) {
            return;
        }

        $this->passer('frais_reversement', [
            ['compte' => Ecriture::COMPTE_FRAIS_REVERSEMENT, 'montant' => $frais],
            ['compte' => Ecriture::COMPTE_SASPAY, 'montant' => -$frais],
        ], "Frais du reversement n° {$reversement->id}", ['reversement_id' => $reversement->id]);
    }

    /** Reversement refusé par le réseau : écritures inverses, le dev retrouve son solde. */
    public function annulerReversement(Reversement $reversement): void
    {
        $this->contrepasser(
            Ecriture::query()->where('reversement_id', $reversement->id)->where('type', 'reversement')->get()->all(),
            "Reversement n° {$reversement->id} refusé, solde rendu",
            ['reversement_id' => $reversement->id],
        );
    }

    /** Avoir après un remboursement fait chez SasPay : l'argent sort, hébergement et part du dev reculent. */
    public function avoir(Avoir $avoir): void
    {
        $avoir->loadMissing('facture.abonnement');

        $this->passer('avoir', [
            ['compte' => Ecriture::COMPTE_SASPAY, 'montant' => -$avoir->montantTotal()],
            ['compte' => Ecriture::COMPTE_REVENU_HEBERGEMENT, 'montant' => $avoir->montant_hebergement],
            ['compte' => Ecriture::COMPTE_DEV, 'dev_id' => $avoir->facture->abonnement->dev_id, 'montant' => $avoir->montant_appli],
        ], "Avoir {$avoir->numero} sur la facture {$avoir->facture->numero}", ['facture_id' => $avoir->facture_id, 'avoir_id' => $avoir->id]);
    }

    /** Dû au dev aujourd'hui (ses parts moins ses reversements et corrections). */
    public function soldeDev(int $devId): int
    {
        return -(int) Ecriture::query()->where('compte', Ecriture::COMPTE_DEV)->where('dev_id', $devId)->sum('montant');
    }

    /** Parts encaissées pour le dev (crédits d'encaissement), depuis une date ou depuis le début. */
    public function encaisseDev(int $devId, ?Carbon $depuis = null, ?int $appliId = null): int
    {
        return -(int) Ecriture::query()
            ->where('compte', Ecriture::COMPTE_DEV)
            ->where('dev_id', $devId)
            ->where('type', 'encaissement')
            ->when($depuis !== null, fn ($requete) => $requete->where('cree_le', '>=', $depuis))
            ->when($appliId !== null, fn ($requete) => $requete->whereHas('facture.abonnement', fn ($abonnement) => $abonnement->where('appli_id', $appliId)))
            ->sum('montant');
    }

    /**
     * Parts encaissées pour le dev, par appli, en une requête (tableau de bord).
     *
     * @return array<int, int> identifiant de l'appli => FCFA
     */
    public function encaisseDevParAppli(int $devId, ?Carbon $depuis = null): array
    {
        return Ecriture::query()
            ->join('factures', 'factures.id', '=', 'ecritures.facture_id')
            ->join('abonnements', 'abonnements.id', '=', 'factures.abonnement_id')
            ->where('ecritures.compte', Ecriture::COMPTE_DEV)
            ->where('ecritures.dev_id', $devId)
            ->where('ecritures.type', 'encaissement')
            ->when($depuis !== null, fn ($requete) => $requete->where('ecritures.cree_le', '>=', $depuis))
            ->groupBy('abonnements.appli_id')
            ->selectRaw('abonnements.appli_id as appli_id, -sum(ecritures.montant) as total')
            ->pluck('total', 'appli_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function solde(string $compte, ?Carbon $debut = null, ?Carbon $fin = null, ?string $type = null): int
    {
        return (int) Ecriture::query()
            ->where('compte', $compte)
            ->when($type !== null, fn ($requete) => $requete->where('type', $type))
            ->when($debut !== null, fn ($requete) => $requete->where('cree_le', '>=', $debut))
            ->when($fin !== null, fn ($requete) => $requete->where('cree_le', '<', $fin))
            ->sum('montant');
    }

    /**
     * @param  list<Ecriture>  $ecritures
     * @param  array<string, int|null>  $liens
     */
    private function contrepasser(array $ecritures, string $libelle, array $liens): void
    {
        if ($ecritures === []) {
            return;
        }

        $this->passer('contrepassation', array_map(fn (Ecriture $ecriture) => [
            'compte' => $ecriture->compte,
            'dev_id' => $ecriture->dev_id,
            'montant' => -$ecriture->montant,
            'contrepasse_id' => $ecriture->id,
        ], $ecritures), $libelle, $liens);
    }

    /**
     * @param  list<array{compte: string, montant: int, dev_id?: int|null, contrepasse_id?: int}>  $lignes
     * @param  array<string, int|null>  $liens
     */
    private function passer(string $type, array $lignes, string $libelle, array $liens): void
    {
        $lignes = array_values(array_filter($lignes, fn (array $ligne) => $ligne['montant'] !== 0));

        if ($lignes === []) {
            return;
        }

        if (array_sum(array_column($lignes, 'montant')) !== 0) {
            throw new LogicException("Opération déséquilibrée ({$type}) : le grand livre refuse.");
        }

        $operation = (string) Str::uuid();

        DB::transaction(function () use ($lignes, $type, $operation, $libelle, $liens): void {
            foreach ($lignes as $ligne) {
                Ecriture::query()->create([
                    ...$liens,
                    'operation' => $operation,
                    'type' => $type,
                    'compte' => $ligne['compte'],
                    'dev_id' => $ligne['dev_id'] ?? null,
                    'montant' => $ligne['montant'],
                    'contrepasse_id' => $ligne['contrepasse_id'] ?? null,
                    'libelle' => $libelle,
                ]);
            }
        });
    }
}
