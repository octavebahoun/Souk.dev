<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\DepotsGithub;
use App\Comptes\ProfilCalcule;
use App\Http\Requests\ModifierCompteRequest;
use App\Http\Resources\CompteResource;
use App\Http\Resources\DevResource;
use App\Models\Appli;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompteController extends Controller
{
    public function show(Request $request): CompteResource
    {
        return new CompteResource($request->user());
    }

    public function dev(string $username, DepotsGithub $depots): JsonResponse
    {
        $compte = User::query()->where('username', $username)->first();

        if ($compte === null) {
            abort(404);
        }

        $calcule = new ProfilCalcule;
        $applis = Appli::query()
            ->where('user_id', $compte->id)
            ->latest()
            ->get()
            ->map(fn (Appli $appli): array => $appli->versApi())
            ->all();

        return response()->json([
            ...DevResource::champs($compte),
            'bio' => $compte->bio,
            'competences' => array_values($compte->competences ?? []),
            'specialites' => array_values($compte->specialites ?? []),
            'disponible' => $compte->disponible,
            'stats' => $calcule->stats(),
            'reputation' => $calcule->reputation(),
            'souk_score' => $calcule->soukScore(),
            'depots' => $depots->pour($compte->username),
            'applis' => $applis,
        ]);
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
