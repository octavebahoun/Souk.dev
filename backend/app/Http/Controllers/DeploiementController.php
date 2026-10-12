<?php

namespace App\Http\Controllers;

use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Moteur\LanceurCompose;
use App\Moteur\LimitesTaille;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function show(Request $request, Deploiement $deploiement): JsonResponse
    {
        abort_unless($deploiement->user_id === $request->user()->id, 403);

        return response()->json($deploiement->versApi());
    }

    public function destroy(Request $request, Deploiement $deploiement, LanceurCompose $lanceur): Response
    {
        abort_unless($deploiement->user_id === $request->user()->id, 403);

        $deploiement->changerEtat('arrete');

        if (is_string($deploiement->copie) && is_string($deploiement->repertoire)) {
            try {
                $lanceur->arreter($deploiement->repertoire, $deploiement->copie);
            } catch (Throwable) {
                // La copie est déjà arrêtée côté plateforme.
            }
        }

        return response()->noContent();
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
