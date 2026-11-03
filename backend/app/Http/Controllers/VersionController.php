<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Jobs\VerifierVersion;
use App\Models\Appli;
use App\Models\User;
use App\Models\VersionAppli;
use App\Moteur\CloneurGithub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Versions d'une appli : le journal des versions est public ; seul l'auteur en publie une
 * nouvelle, vérifiée en file comme une publication avant d'être proposée aux clients.
 */
final class VersionController extends Controller
{
    public function index(Request $request, Appli $appli): JsonResponse
    {
        $parPage = Pagination::parPage($request);
        /** @var User|null $compte */
        $compte = $request->user();

        $versions = $appli->versions()
            ->when($compte?->id !== $appli->user_id, fn ($requete) => $requete->where('statut', 'publiee'))
            ->orderByDesc('numero')
            ->paginate($parPage)
            ->withQueryString()
            ->through(fn (VersionAppli $version) => $version->versApi());

        return JsonResource::collection($versions)->response();
    }

    public function store(Request $request, Appli $appli): JsonResponse
    {
        Gate::authorize('update', $appli);
        $donnees = $request->validate([
            'ref' => ['sometimes', 'nullable', 'string', 'max:200', function (string $attribut, mixed $valeur, \Closure $echec): void {
                if (is_string($valeur) && ! CloneurGithub::refValide($valeur)) {
                    $echec('Indiquez une branche, un tag ou un commit (lettres, chiffres, « . », « _ », « - » et « / »).');
                }
            }],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'notes.required' => 'Dites en quelques mots ce qui change pour les clients.',
            'notes.min' => 'Dites en quelques mots ce qui change pour les clients.',
        ]);

        $version = DB::transaction(function () use ($appli, $donnees): VersionAppli {
            $verrouillee = Appli::query()->lockForUpdate()->findOrFail($appli->id);
            if ($verrouillee->versions()->where('statut', 'verification')->exists()) {
                throw new ConflictHttpException('Une version est déjà en vérification : attendez son résultat.');
            }

            return $verrouillee->versions()->create([
                'numero' => (int) $verrouillee->versions()->max('numero') + 1,
                'ref' => $donnees['ref'] ?? null,
                'notes' => trim($donnees['notes']),
                'statut' => 'verification',
            ]);
        });

        // La réponse décrit la demande acceptée (« verification ») ; le résultat arrive ensuite.
        $acceptee = $version->versApi();
        VerifierVersion::dispatch($version->id);

        return response()->json($acceptee, 202);
    }
}
