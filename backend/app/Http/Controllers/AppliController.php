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
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppliController extends Controller
{
    private const CATEGORIES = [
        'commerce', 'gestion', 'education', 'sante', 'finance',
        'association', 'restauration', 'autre',
    ];

    private const TECHNOS = [
        'laravel', 'symfony', 'php', 'node', 'express', 'nestjs', 'nextjs',
        'react', 'vue', 'angular', 'svelte', 'django', 'flask', 'fastapi',
        'spring', 'dotnet', 'go', 'postgresql', 'mysql', 'mongodb', 'redis', 'autre',
    ];

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

    public function update(Request $request, Appli $appli): JsonResponse
    {
        abort_unless($appli->user_id === $request->user()->id, 403);

        $appli->fill($this->donneesModification($request))->save();

        return response()->json($appli->fresh()->versApi());
    }

    public function destroy(Request $request, Appli $appli): Response
    {
        abort_unless($appli->user_id === $request->user()->id, 403);

        Storage::disk('public')->deleteDirectory('captures/'.$appli->id);
        $appli->delete();

        return response()->noContent();
    }

    public function captures(Request $request, Appli $appli): JsonResponse
    {
        abort_unless($appli->user_id === $request->user()->id, 403);

        $donnees = $request->validate([
            'captures' => ['required', 'array', 'min:1', 'max:5'],
            'captures.*' => ['file', 'mimes:png,jpeg,jpg,webp', 'max:5120'],
        ]);

        Storage::disk('public')->deleteDirectory('captures/'.$appli->id);
        $appli->forceFill([
            'captures' => $this->enregistrerCaptures($appli, $donnees['captures']),
        ])->save();

        return response()->json($appli->fresh()->versApi());
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
            'categorie' => ['required', Rule::in(self::CATEGORIES)],
            'depot_url' => ['required', 'string', 'url', 'max:500'],
            'demo_url' => ['required', 'string', 'url', 'max:500'],
            'prix' => ['required', 'integer', 'min:0'],
            'type_prix' => ['required', Rule::in(['mensuel', 'unique'])],
            'taille' => ['required', Rule::in(['petite', 'moyenne', 'grande'])],
            'stack' => ['required', 'array', 'min:1', 'max:8'],
            'stack.*' => ['string', Rule::in(self::TECHNOS)],
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
    private function donneesModification(Request $request): array
    {
        $donnees = $request->validate([
            'nom' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'demo_url' => ['sometimes', 'string', 'url', 'max:500'],
            'categorie' => ['sometimes', Rule::in(self::CATEGORIES)],
            'prix' => ['sometimes', 'integer', 'min:0'],
            'type_prix' => ['sometimes', Rule::in(['mensuel', 'unique'])],
            'taille' => ['sometimes', Rule::in(['petite', 'moyenne', 'grande'])],
            'stack' => ['sometimes', 'array', 'min:1', 'max:8'],
            'stack.*' => ['string', Rule::in(self::TECHNOS)],
        ]);

        if (array_key_exists('stack', $donnees)) {
            $stack = array_values(array_unique($donnees['stack']));

            if (count($stack) !== count($donnees['stack'])) {
                throw ValidationException::withMessages([
                    'stack' => 'Chaque techno ne peut apparaître qu\'une fois.',
                ]);
            }

            $donnees['stack'] = $stack;
        }

        if (isset($donnees['taille']) && ! LimitesTaille::connue($donnees['taille'])) {
            throw ValidationException::withMessages([
                'taille' => 'La taille doit être petite, moyenne ou grande.',
            ]);
        }

        return $donnees;
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
