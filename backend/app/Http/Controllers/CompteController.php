<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ModifierCompteRequest;
use App\Http\Resources\CompteResource;
use App\Models\User;
use Illuminate\Http\Request;

final class CompteController extends Controller
{
    public function show(Request $request): CompteResource
    {
        return new CompteResource($request->user());
    }

    public function update(ModifierCompteRequest $request): CompteResource
    {
        /** @var User $compte */
        $compte = $request->user();
        $donnees = $request->validated();

        if (array_key_exists('pays', $donnees)) {
            $compte->pays = $donnees['pays'];
        }

        if (array_key_exists('bio', $donnees)) {
            $compte->bio = $donnees['bio'];
        }

        if (array_key_exists('competences', $donnees)) {
            $compte->competences = array_values($donnees['competences']);
        }

        if (array_key_exists('specialites', $donnees)) {
            $compte->specialites = array_values($donnees['specialites']);
        }

        if (array_key_exists('disponible', $donnees)) {
            $compte->disponible = (bool) $donnees['disponible'];
        }

        $compte->save();

        return new CompteResource($compte);
    }
}
