<?php

namespace App\Http\Controllers;

use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\SourceCopieTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Copie de test d'une discussion : jamais automatique, seulement quand l'auteur
 * la demande (voir DECISIONS.md, « Copie de test »).
 */
class CopieTestController extends Controller
{
    /** États où la copie occupe encore le serveur. */
    public const ETATS_ACTIFS = ['en_file', 'construction', 'demarrage', 'en_ligne'];

    public function store(Request $request, Discussion $discussion): JsonResponse
    {
        abort_unless($discussion->user_id === $request->user()->id, 403, 'Seul l\'auteur de la discussion peut lancer sa copie de test.');

        $active = Deploiement::query()
            ->where('discussion_id', $discussion->id)
            ->where('type', 'test')
            ->whereIn('etat', self::ETATS_ACTIFS)
            ->exists();

        abort_if($active, 409, 'Une copie de test de cette discussion tourne déjà.');

        $appli = $discussion->app_id !== null ? Appli::query()->find($discussion->app_id) : null;

        try {
            $source = SourceCopieTest::choisir($appli?->depot_url, $appli?->taille, $discussion->depot_url);
        } catch (DepotInvalide $exception) {
            throw ValidationException::withMessages(['depot_url' => $exception->getMessage()]);
        }

        $deploiement = Deploiement::query()->create([
            'user_id' => $request->user()->id,
            'appli_id' => $appli?->id,
            'discussion_id' => $discussion->id,
            'depot_url' => $appli === null ? $source['depot_url'] : null,
            'type' => 'test',
            'taille' => $source['taille'],
            'etat' => 'en_file',
            'variables' => [],
            'expire_le' => now()->addMinutes(max(1, (int) config('moteur.copie_test_minutes'))),
        ]);

        $deploiement->diffuser();
        LancerDeploiement::dispatch($deploiement->id);

        return response()->json($deploiement->versApi(), 202);
    }
}
