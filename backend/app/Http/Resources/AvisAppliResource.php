<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AvisAppli;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AvisAppliResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AvisAppli $avis */
        $avis = $this->resource;
        $avis->loadMissing('auteur');

        return [
            'id' => $avis->id,
            'note' => $avis->note,
            'commentaire' => $avis->commentaire,
            'auteur' => DevResource::champs($avis->auteur),
            'cree_le' => $avis->created_at?->toIso8601String(),
            'modifie_le' => $avis->updated_at?->toIso8601String(),
        ];
    }
}
