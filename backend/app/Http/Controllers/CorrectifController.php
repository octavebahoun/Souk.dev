<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Events\CorrectifAccepte;
use App\Events\CorrectifPropose;
use App\Events\DiscussionModifiee;
use App\Http\Pagination;
use App\Http\Requests\CreerCorrectifRequest;
use App\Http\Resources\CorrectifResource;
use App\Models\Correctif;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CorrectifController extends Controller
{
    public function index(Discussion $discussion): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage(request());

        $correctifs = $discussion->correctifs()
            ->with('auteur')
            ->orderBy('id')
            ->paginate($parPage)
            ->withQueryString();

        return CorrectifResource::collection($correctifs);
    }

    public function store(CreerCorrectifRequest $request, Discussion $discussion, Diffuseur $diffuseur): JsonResponse
    {
        /** @var User $auteur */
        $auteur = $request->user();

        if (! $auteur->github_lie) {
            abort(403, 'GitHub n\'est pas lié à ce compte.');
        }

        $correctif = $discussion->correctifs()->create([
            'user_id' => $auteur->id,
            'depot_url' => $request->validated('depot_url'),
            'demo_url' => $request->validated('demo_url'),
            'description' => $request->validated('description'),
            'accepte' => false,
        ]);
        $correctif->setRelation('auteur', $auteur);
        $diffuseur->envoyer(new CorrectifPropose($correctif));

        return (new CorrectifResource($correctif))->response()->setStatusCode(201);
    }

    public function accepter(Correctif $correctif, Diffuseur $diffuseur): CorrectifResource
    {
        $discussion = $correctif->discussion()->firstOrFail();
        Gate::authorize('update', $discussion);

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
            $correctif->save();
            $discussion->resolue = true;
            $discussion->save();

            return $discussion;
        });

        $correctif->refresh()->load('auteur');
        $discussion->load(['auteur', 'canal'])->loadCount('messages');
        $diffuseur->envoyer(new CorrectifAccepte($correctif));
        $diffuseur->envoyer(new DiscussionModifiee($discussion));

        return new CorrectifResource($correctif);
    }
}
