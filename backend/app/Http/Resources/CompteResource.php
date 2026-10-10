<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Comptes\ProfilCalcule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CompteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $compte */
        $compte = $this->resource;
        $calcule = new ProfilCalcule;

        return [
            ...DevResource::champs($compte),
            'email' => $compte->email,
            'github_lie' => $compte->github_lie,
            'est_admin' => $compte->est_admin,
            'bio' => $compte->bio,
            'competences' => array_values($compte->competences ?? []),
            'specialites' => array_values($compte->specialites ?? []),
            'disponible' => $compte->disponible,
            'stats' => $calcule->stats(),
            'reputation' => $calcule->reputation(),
            'souk_score' => $calcule->soukScore(),
        ];
    }
}
