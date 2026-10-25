<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\DepotsGithub;
use App\Comptes\ProfilCalcule;
use App\Enums\Profil;
use App\Enums\Specialite;
use App\Http\Pagination;
use App\Http\Requests\ModifierCompteRequest;
use App\Http\Resources\CompteResource;
use App\Http\Resources\DevResource;
use App\Models\Appli;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CompteController extends Controller
{
    public function show(Request $request): CompteResource
    {
        return new CompteResource($request->user());
    }

    public function tableau(Request $request): JsonResponse
    {
        $applis = Appli::query()
            ->where('user_id', $request->user()->id)
            ->withCount([
                'deploiements as deploiements_total' => fn ($requete) => $requete->where('type', 'client'),
                'deploiements as copies_actives' => fn ($requete) => $requete
                    ->where('type', 'client')
                    ->where('etat', 'en_ligne'),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function (Appli $appli): array {
                $prix = (int) $appli->prix;
                $revenu = $appli->type_prix === 'mensuel'
                    ? (int) $appli->copies_actives * $prix
                    : (int) $appli->deploiements_total * $prix;

                return [
                    'app_id' => $appli->id,
                    'nom' => (string) $appli->nom,
                    'type_prix' => (string) $appli->type_prix,
                    'prix' => $prix,
                    'vues' => (int) $appli->vues,
                    'copies_actives' => (int) $appli->copies_actives,
                    'deploiements_total' => (int) $appli->deploiements_total,
                    'revenu_estime' => $revenu,
                ];
            });

        return response()->json([
            'totaux' => [
                'vues' => (int) $applis->sum('vues'),
                'copies_actives' => (int) $applis->sum('copies_actives'),
                'revenus_estimes_mensuels' => (int) $applis
                    ->where('type_prix', 'mensuel')
                    ->sum('revenu_estime'),
                'revenus_estimes_uniques' => (int) $applis
                    ->where('type_prix', 'unique')
                    ->sum('revenu_estime'),
            ],
            'applis' => $applis->values(),
        ]);
    }

    public function index(Request $request, ProfilCalcule $profils): JsonResponse
    {
        $request->validate([
            'disponible' => ['sometimes', Rule::in(['true', 'false', '0', '1'])],
            'competence' => ['sometimes', 'string', Rule::in(Appli::TECHNOS)],
            'specialite' => ['sometimes', 'string', Rule::enum(Specialite::class)],
            'pays' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
        ]);

        $devs = User::query()
            ->where('profil', Profil::Dev)
            ->whereNotNull('username')
            ->when($request->boolean('disponible'), fn ($requete) => $requete->where('disponible', true))
            ->when($request->filled('competence'), fn ($requete) => $requete->whereJsonContains(
                'competences',
                $request->string('competence')->toString(),
            ))
            ->when($request->filled('specialite'), fn ($requete) => $requete->whereJsonContains(
                'specialites',
                $request->string('specialite')->toString(),
            ))
            ->when($request->filled('pays'), fn ($requete) => $requete->where(
                'pays',
                $request->string('pays')->toString(),
            ))
            // Du meilleur Souk Score au plus faible (copie rangée, rafraîchie à chaque action validée).
            ->orderByDesc('souk_score')
            ->orderBy('name')
            ->paginate(Pagination::parPage($request))
            ->through(fn (User $compte): array => [
                ...DevResource::champs($compte),
                'bio' => $compte->bio,
                'competences' => array_values($compte->competences ?? []),
                'specialites' => array_values($compte->specialites ?? []),
                'disponible' => (bool) $compte->disponible,
                ...$profils->range($compte),
            ]);

        return response()->json($devs);
    }

    public function dev(string $username, DepotsGithub $depots, ProfilCalcule $profils): JsonResponse
    {
        $compte = User::query()->where('username', $username)->first();

        if ($compte === null) {
            abort(404);
        }

        $applis = Appli::query()
            ->where('user_id', $compte->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Appli $appli): array => $appli->versApi())
            ->all();

        return response()->json([
            ...DevResource::champs($compte),
            'bio' => $compte->bio,
            'competences' => array_values($compte->competences ?? []),
            'specialites' => array_values($compte->specialites ?? []),
            'disponible' => $compte->disponible,
            ...$profils->pour($compte),
            // Un compte de démo n'a pas de vrai compte GitHub derrière son identifiant.
            'depots' => $compte->est_demo ? [] : $depots->pour($compte->username),
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
