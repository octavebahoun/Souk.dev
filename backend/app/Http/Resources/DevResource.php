<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Profil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DevResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function champs(User $compte): array
    {
        $profil = $compte->profil;

        return [
            'id' => $compte->id,
            'nom' => $compte->name,
            'pays' => $compte->pays,
            'username' => $compte->username,
            'avatar_url' => $compte->avatar_url,
            // Les comptes de démo n'ont pas de vrai compte GitHub : pas de lien vers une page vide.
            'github_url' => $compte->username !== null && ! $compte->est_demo ? 'https://github.com/'.$compte->username : null,
            'profil' => $profil instanceof Profil ? $profil->value : $profil,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::champs($this->resource);
    }
}
