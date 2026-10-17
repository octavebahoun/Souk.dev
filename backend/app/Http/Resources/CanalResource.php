<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Canal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CanalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Canal $canal */
        $canal = $this->resource;

        return [
            'slug' => $canal->slug,
            'nom' => $canal->nom,
            'description' => $canal->description,
            'pays' => $canal->pays,
        ];
    }
}
