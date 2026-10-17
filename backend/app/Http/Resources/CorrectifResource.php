<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Correctif;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CorrectifResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Correctif $correctif */
        $correctif = $this->resource;
        $correctif->loadMissing('auteur');

        return [
            'id' => $correctif->id,
            'discussion_id' => $correctif->discussion_id,
            'depot_url' => $correctif->depot_url,
            'demo_url' => $correctif->demo_url,
            'description' => $correctif->description,
            'accepte' => $correctif->accepte,
            'deploiement' => null,
            'auteur' => DevResource::champs($correctif->auteur),
            'cree_le' => $correctif->created_at?->toIso8601String(),
        ];
    }
}
