<?php

namespace App\Http\Controllers;

use App\Models\Appli;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\LimitesTaille;
use App\Moteur\PublicationDepot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppliController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'gratuit' => ['sometimes'],
            'techno' => ['sometimes', 'string', Rule::in(Appli::TECHNOS)],
            'securite' => ['sometimes'],
            'categorie' => ['sometimes', Rule::in(Appli::CATEGORIES)],
            'pays' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'prix_max' => ['sometimes', 'integer', 'min:0'],
            'type_prix' => ['sometimes', Rule::in(['mensuel', 'unique'])],
            'tri' => ['sometimes', 'in:recent,prix_croissant,prix_decroissant'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $applis = Appli::query()
            ->with('auteur')
            ->when($request->filled('q'), function ($requete) use ($request): void {
                $terme = '%'.$request->string('q')->toString().'%';
                $requete->where(function ($recherche) use ($terme): void {
                    $recherche->where('nom', 'like', $terme)
                        ->orWhere('description', 'like', $terme);
                });
            })
            ->when($request->has('gratuit'), fn ($requete) => $requete->where(
                'prix',
                $request->boolean('gratuit') ? '=' : '>',
                0,
            ))
            ->when($request->filled('techno'), fn ($requete) => $requete->whereJsonContains(
                'stack',
                $request->string('techno')->toString(),
            ))
            ->when($request->boolean('securite'), fn ($requete) => $requete->where('securite_statut', 'verifie'))
            ->when($request->filled('categorie'), fn ($requete) => $requete->where(
                'categorie',
                $request->string('categorie')->toString(),
            ))
            ->when($request->filled('pays') && Schema::hasColumn('users', 'pays'), function ($requete) use ($request): void {
                $requete->whereHas(
                    'auteur',
                    fn ($auteur) => $auteur->where('pays', $request->string('pays')->toString()),
                );
            })
            ->when($request->filled('prix_max'), fn ($requete) => $requete->where(
                'prix',
                '<=',
                $request->integer('prix_max'),
            ))
            ->when($request->filled('type_prix'), fn ($requete) => $requete->where(
                'type_prix',
                $request->string('type_prix')->toString(),
            ))
            ->when(
                $request->input('tri') === 'prix_croissant',
                fn ($requete) => $requete->orderBy('prix')->orderByDesc('id'),
            )
            ->when(
                $request->input('tri') === 'prix_decroissant',
                fn ($requete) => $requete->orderByDesc('prix')->orderByDesc('id'),
            )
            ->when(
                ! in_array($request->input('tri'), ['prix_croissant', 'prix_decroissant'], true),
                fn ($requete) => $requete->latest(),
            )
            ->paginate($request->integer('per_page', 20))
            ->through(fn (Appli $appli) => $appli->versApi());

        return JsonResource::collection($applis);
    }

    public function show(Request $request, Appli $appli): JsonResponse
    {
        $appli->compterVue($request);

        return response()->json($appli->fresh()->versApi());
    }

    public function categories(): JsonResponse
    {
        return response()->json(Appli::CATEGORIES);
    }

    public function store(Request $request, PublicationDepot $depot): JsonResponse
    {
        $donnees = $this->donnees($request);
        $manifeste = $this->manifeste($donnees['depot_url'], $depot);

        $appli = Appli::query()->create([
            'user_id' => $request->user()->id,
            'nom' => $donnees['nom'],
            'description' => $donnees['description'],
            'categorie' => $donnees['categorie'],
            'prix' => $donnees['prix'],
            'type_prix' => $donnees['type_prix'],
            'taille' => $donnees['taille'],
            'demo_url' => $donnees['demo_url'],
            'depot_url' => $donnees['depot_url'],
            'stack' => $donnees['stack'],
            'backend' => $manifeste['backend'],
            'variables_client' => $this->variablesClient($manifeste),
        ]);

        $appli->forceFill([
            'captures' => $this->enregistrerCaptures($appli, $donnees['captures']),
        ])->save();

        $appli->demanderMesure();

        return response()->json($appli->fresh()->versApi(), 201);
    }

    /**
     * @return array{
     *     nom: string,
     *     description: string,
     *     categorie: string,
     *     depot_url: string,
     *     demo_url: string,
     *     prix: int,
     *     type_prix: string,
     *     taille: string,
     *     stack: list<string>,
     *     captures: list<UploadedFile>
     * }
     */
    private function donnees(Request $request): array
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'categorie' => ['required', Rule::in(Appli::CATEGORIES)],
            'depot_url' => ['required', 'string', 'url', 'max:500'],
            'demo_url' => ['required', 'string', 'url', 'max:500'],
            'prix' => ['required', 'integer', 'min:0'],
            'type_prix' => ['required', Rule::in(['mensuel', 'unique'])],
            'taille' => ['required', Rule::in(['petite', 'moyenne', 'grande'])],
            'stack' => ['required', 'array', 'min:1', 'max:8'],
            'stack.*' => ['string', Rule::in(Appli::TECHNOS)],
            'captures' => ['required', 'array', 'min:1', 'max:5'],
            'captures.*' => ['file', 'mimes:png,jpeg,jpg,webp', 'max:5120'],
        ]);

        $stack = array_values(array_unique($donnees['stack']));

        if (count($stack) !== count($donnees['stack'])) {
            throw ValidationException::withMessages([
                'stack' => 'Chaque techno ne peut apparaître qu\'une fois.',
            ]);
        }

        if (! LimitesTaille::connue($donnees['taille'])) {
            throw ValidationException::withMessages([
                'taille' => 'La taille doit être petite, moyenne ou grande.',
            ]);
        }

        return [
            ...$donnees,
            'stack' => $stack,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifeste(string $url, PublicationDepot $depot): array
    {
        try {
            return $depot->lire($url);
        } catch (DepotInvalide|ManifestInvalide|LancementEchoue $exception) {
            throw ValidationException::withMessages([
                'depot_url' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $manifeste
     * @return list<array{nom: string, libelle: string, requis: bool}>
     */
    private function variablesClient(array $manifeste): array
    {
        $variables = [];

        if (! isset($manifeste['variables']) || ! is_array($manifeste['variables'])) {
            return [];
        }

        foreach ($manifeste['variables'] as $variable) {
            if (! is_array($variable) || ($variable['source'] ?? null) !== 'client') {
                continue;
            }

            $variables[] = [
                'nom' => (string) $variable['nom'],
                'libelle' => (string) $variable['libelle'],
                'requis' => (bool) $variable['requis'],
            ];
        }

        return $variables;
    }

    /**
     * @param  list<UploadedFile>  $fichiers
     * @return list<array{url: string}>
     */
    private function enregistrerCaptures(Appli $appli, array $fichiers): array
    {
        $captures = [];

        foreach (array_values($fichiers) as $index => $fichier) {
            $extension = $fichier->extension() ?: 'png';
            $chemin = $fichier->storeAs(
                'captures/'.$appli->id,
                ($index + 1).'.'.$extension,
                'public',
            );

            $captures[] = ['url' => url('/storage/'.$chemin)];
        }

        return $captures;
    }
}
