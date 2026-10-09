<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'app_id' => $this->app_id,
        'statut' => $this->statut,
        'note' => $this->note === null ? null : [
            'note' => $this->note,
            'commentaire' => $this->note_commentaire,
        ],
        'client' => new DevResource($this->client),
        'auteur' => new DevResource($this->auteur),
        'message' => $this->message,
        'budget' => $this->budget,
        'delai' => $this->delai?->toDateString(),
        'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
