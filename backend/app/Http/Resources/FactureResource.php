<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Facture;
use App\Paiement\Facturation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FactureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Facture $facture */
        $facture = $this->resource;
        $facture->loadMissing(['abonnement.appli', 'abonnement.deploiement.appli', 'paiements', 'avoirs']);

        return [
            'id' => $facture->id,
            'numero' => (string) $facture->numero,
            'deploiement_id' => $facture->abonnement->deploiement_id,
            'app_nom' => $facture->abonnement->appli?->nom ?? $facture->abonnement->deploiement->versApi()['app_nom'],
            'periode_debut' => $facture->periode_debut->toDateString(),
            'periode_fin' => $facture->periode_fin->toDateString(),
            'emise_le' => $facture->created_at->toIso8601String(),
            'echeance_le' => $facture->echeance_le->toDateString(),
            'montant_hebergement' => $facture->montant_hebergement,
            'montant_appli' => $facture->montant_appli,
            'montant_total' => $facture->montant_total,
            'montant_rembourse' => (int) $facture->avoirs->sum(fn ($avoir) => $avoir->montantTotal()),
            'statut' => $facture->statut,
            'payee_le' => $facture->payee_le?->toIso8601String(),
            'lignes' => app(Facturation::class)->lignes($facture),
            'paiements' => PaiementResource::collection($facture->paiements)->resolve(),
        ];
    }
}
