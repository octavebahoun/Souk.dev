<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaiementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Paiement $paiement */
        $paiement = $this->resource;

        return [
            'id' => $paiement->id,
            'statut' => $paiement->statut,
            'montant' => $paiement->montant,
            'frais_client' => $paiement->frais_client,
            'montant_debite' => $paiement->montant_debite,
            'reference' => $paiement->reference,
            'cree_le' => $paiement->created_at->toIso8601String(),
            'verifie_le' => $paiement->verifie_le?->toIso8601String(),
        ];
    }
}
