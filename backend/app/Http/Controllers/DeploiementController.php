<?php

namespace App\Http\Controllers;

use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Moteur\LimitesTaille;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeploiementController extends Controller
{
    public function store(Request $request, Appli $appli): JsonResponse
    {
        $variables = $this->variables($request);
        $taille = $this->taille($appli);

        $deploiement = Deploiement::query()->create([
            'user_id' => $request->user()->id,
            'appli_id' => $appli->id,
            'type' => 'client',
            'taille' => $taille,
            'etat' => 'en_file',
            'variables' => $variables,
        ]);

        $deploiement->diffuser();
        LancerDeploiement::dispatch($deploiement->id);

        return response()->json($deploiement->versApi(), 202);
    }

    public function index(Request $request): JsonResource
    {
        $request->validate([
            'type' => ['sometimes', 'in:client,test,correctif'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $deploiements = Deploiement::query()
            ->where('user_id', $request->user()->id)
            ->with('appli')
            ->when($request->filled('type'), fn ($requete) => $requete->where('type', $request->string('type')))
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->through(fn (Deploiement $deploiement) => $deploiement->versApi());

        return JsonResource::collection($deploiements);
    }

    public function show(Deploiement $deploiement): JsonResponse
    {
        Gate::authorize('view', $deploiement);

        return response()->json($deploiement->versApi());
    }

    /**
     * L'arrêt tourne sur le moteur : arret_en_cours tout de suite, arrete par Reverb ensuite.
     */
    public function destroy(Deploiement $deploiement): JsonResponse
    {
        Gate::authorize('delete', $deploiement);

        $deploiement->demanderArret();

        return response()->json($deploiement->refresh()->versApi(), 202);
    }

    /**
     * @return array<string, string>
     */
    private function variables(Request $request): array
    {
        $donnees = $request->validate([
            'variables' => ['present', 'array'],
            'variables.*' => ['string', 'max:500'],
        ]);

        $variables = $donnees['variables'];

        if (array_is_list($variables) && $variables !== []) {
            throw ValidationException::withMessages([
                'variables' => 'Les variables doivent être un objet nom → valeur.',
            ]);
        }

        return $variables;
    }

    private function taille(Appli $appli): string
    {
        $taille = (string) $appli->taille;

        if (! LimitesTaille::connue($taille)) {
            throw ValidationException::withMessages([
                'taille' => 'La taille doit être petite, moyenne ou grande.',
            ]);
        }

        return $taille;
    }
}
