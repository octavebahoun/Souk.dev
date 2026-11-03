<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Comptes\ProfilCalcule;
use App\Echange\Diffuseur;
use App\Events\CorrectifAccepte;
use App\Events\CorrectifPropose;
use App\Events\DiscussionModifiee;
use App\Http\Pagination;
use App\Http\Requests\CreerCorrectifRequest;
use App\Http\Resources\CorrectifResource;
use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\User;
use App\Moteur\DepotGithub;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\LimitesTaille;
use App\Moteur\SourceCopieTest;
use App\Notifications\CorrectifRecu;
use App\Notifications\CorrectifRetenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CorrectifController extends Controller
{
    public function index(Discussion $discussion): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage(request());

        $correctifs = $discussion->correctifs()
            ->with(['auteur', 'deploiement'])
            ->orderBy('id')
            ->paginate($parPage)
            ->withQueryString();

        return CorrectifResource::collection($correctifs);
    }

    public function store(CreerCorrectifRequest $request, Discussion $discussion, Diffuseur $diffuseur): JsonResponse
    {
        Gate::authorize('lierUnDepot');

        /** @var User $auteur */
        $auteur = $request->user();

        $correctif = $discussion->correctifs()->create([
            'user_id' => $auteur->id,
            'depot_url' => $request->validated('depot_url'),
            'demo_url' => $request->validated('demo_url'),
            'description' => $request->validated('description'),
            'accepte' => false,
        ]);
        $correctif->setRelation('auteur', $auteur);
        $diffuseur->envoyer(new CorrectifPropose($correctif));
        if ($discussion->user_id !== $auteur->id) {
            $discussion->auteur?->notify(new CorrectifRecu($correctif));
        }

        return (new CorrectifResource($correctif))->response()->setStatusCode(201);
    }

    public function accepter(Correctif $correctif, Diffuseur $diffuseur, ProfilCalcule $profils): CorrectifResource
    {
        Gate::authorize('accepter', $correctif);

        $discussion = DB::transaction(function () use ($correctif): Discussion {
            $discussion = Discussion::query()->whereKey($correctif->discussion_id)->lockForUpdate()->firstOrFail();

            $dejaAccepte = Correctif::query()
                ->where('discussion_id', $discussion->id)
                ->where('accepte', true)
                ->lockForUpdate()
                ->exists();

            if ($dejaAccepte) {
                throw new ConflictHttpException('Un correctif est déjà accepté pour cette discussion.');
            }

            $correctif->accepte = true;
            $correctif->accepte_le = now();
            $correctif->save();
            $discussion->resolue = true;
            $discussion->save();

            return $discussion;
        });

        $correctif->refresh()->load('auteur');
        $profils->rafraichir($correctif->auteur);
        $discussion->load(['auteur', 'canal'])->loadCount('messages');
        $diffuseur->envoyer(new CorrectifAccepte($correctif));
        $diffuseur->envoyer(new DiscussionModifiee($discussion));
        if ($correctif->user_id !== $discussion->user_id) {
            $correctif->auteur->notify(new CorrectifRetenu($correctif));
        }

        return new CorrectifResource($correctif);
    }

    /**
     * Optionnel : l'auteur du correctif déploie sa version corrigée pour la montrer tourner.
     */
    public function deployer(Request $request, Correctif $correctif): JsonResponse
    {
        Gate::authorize('deployer', $correctif);

        $actif = Deploiement::query()
            ->where('correctif_id', $correctif->id)
            ->where('type', 'correctif')
            ->whereIn('etat', CopieTestController::ETATS_ACTIFS)
            ->exists();

        abort_if($actif, 409, 'La version corrigée de ce correctif tourne déjà.');

        try {
            DepotGithub::depuisUrl($correctif->depot_url);
        } catch (DepotInvalide $exception) {
            throw ValidationException::withMessages(['depot_url' => $exception->getMessage()]);
        }

        $discussion = $correctif->discussion()->firstOrFail();
        $appli = $discussion->app_id !== null ? Appli::query()->find($discussion->app_id) : null;
        $taille = is_string($appli?->taille) && LimitesTaille::connue($appli->taille) ? $appli->taille : SourceCopieTest::TAILLE_DEPOT;

        // Le fork ou la branche du correctif, jamais le dépôt de l'appli : appli_id reste vide.
        $deploiement = Deploiement::query()->create([
            'user_id' => $request->user()->id,
            'discussion_id' => $discussion->id,
            'correctif_id' => $correctif->id,
            'depot_url' => $correctif->depot_url,
            'type' => 'correctif',
            'taille' => $taille,
            'etat' => 'en_file',
            'variables' => [],
            'expire_le' => now()->addMinutes(max(1, (int) config('moteur.copie_test_minutes'))),
        ]);

        $deploiement->diffuser();
        LancerDeploiement::dispatch($deploiement->id);

        return response()->json($deploiement->versApi(), 202);
    }
}
