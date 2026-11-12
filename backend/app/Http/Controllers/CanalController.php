<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreerCanalRequest;
use App\Http\Resources\CanalResource;
use App\Models\Canal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CanalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'pays' => ['sometimes', 'regex:/^[A-Z]{2}$/'],
        ], [
            'pays.regex' => 'Le pays doit être un code ISO à deux lettres.',
        ]);

        $compte = $request->user('sanctum');

        $canaux = Canal::query()
            ->withCount('abonnes')
            ->when($compte !== null, fn ($query) => $query->withExists(['abonnes as suivi' => fn ($abonnes) => $abonnes->whereKey($compte->id)]))
            ->when($request->filled('pays'), fn ($query) => $query->where('pays', $request->string('pays')->toString()))
            ->orderBy('nom')
            ->get();

        return CanalResource::collection($canaux);
    }

    public function store(CreerCanalRequest $request): JsonResponse
    {
        $donnees = $request->validated();
        $canal = Canal::ouvrir(
            $donnees['nom'],
            $donnees['description'] ?? null,
            $donnees['pays'] ?? null,
        );

        return (new CanalResource($canal))->response()->setStatusCode(201);
    }

    /** Suivre un canal : ses discussions passent en premier dans le fil « Pour moi ». */
    public function suivre(Request $request, Canal $canal): CanalResource
    {
        $canal->abonnes()->syncWithoutDetaching([$request->user()->id => ['created_at' => now()]]);

        return $this->avecSuivi($request, $canal);
    }

    public function nePlusSuivre(Request $request, Canal $canal): CanalResource
    {
        $canal->abonnes()->detach($request->user()->id);

        return $this->avecSuivi($request, $canal);
    }

    private function avecSuivi(Request $request, Canal $canal): CanalResource
    {
        $canal->loadCount('abonnes');
        $canal->setAttribute('suivi', $canal->abonnes()->whereKey($request->user()->id)->exists());

        return new CanalResource($canal);
    }

    /** Renommer ou décrire un canal (admin). Le slug ne change pas : les liens restent valables. */
    public function update(Request $request, Canal $canal): CanalResource
    {
        $donnees = $request->validate([
            'nom' => ['sometimes', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'pays' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/'],
        ], [
            'nom.max' => 'Le nom du canal ne peut pas dépasser 50 caractères.',
            'description.max' => 'La description ne peut pas dépasser 300 caractères.',
            'pays.regex' => 'Le pays doit être un code ISO à deux lettres.',
        ]);

        $canal->fill($donnees)->save();

        return new CanalResource($canal->refresh());
    }

    /** Supprimer un canal vide (admin). Refusé tant que des discussions l'utilisent. */
    public function destroy(Canal $canal): JsonResponse
    {
        if ($canal->discussions()->exists()) {
            throw new ConflictHttpException('Ce canal accueille encore des discussions.');
        }

        $canal->delete();

        return response()->json(null, 204);
    }
}
