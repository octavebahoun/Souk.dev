<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreerCanalRequest;
use App\Http\Resources\CanalResource;
use App\Models\Canal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CanalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'pays' => ['sometimes', 'regex:/^[A-Z]{2}$/'],
        ], [
            'pays.regex' => 'Le pays doit être un code ISO à deux lettres.',
        ]);

        $canaux = Canal::query()
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
}
