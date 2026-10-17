<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Echange\DiscussionEnregistreur;
use App\Enums\Difficulte;
use App\Enums\Etiquette;
use App\Events\DiscussionCreee;
use App\Events\DiscussionModifiee;
use App\Events\DiscussionSupprimee;
use App\Http\Pagination;
use App\Http\Requests\CreerDiscussionRequest;
use App\Http\Requests\ModifierDiscussionRequest;
use App\Http\Resources\DiscussionResource;
use App\Models\Canal;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class DiscussionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage($request);

        $request->validate([
            'app' => ['sometimes', 'integer'],
            'canal' => ['sometimes', 'string'],
            'etiquette' => ['sometimes', 'string', Rule::enum(Etiquette::class)],
            'statut' => ['sometimes', 'in:ouvertes,resolues'],
            'difficulte' => ['sometimes', 'string', Rule::enum(Difficulte::class)],
        ]);

        $discussions = Discussion::query()
            ->with(['auteur', 'canal'])
            ->withCount('messages')
            ->when($request->filled('app'), fn ($query) => $query->where('app_id', $request->integer('app')))
            ->when($request->filled('canal'), function ($query) use ($request): void {
                $query->whereHas('canal', fn ($canal) => $canal->where('slug', $request->string('canal')->toString()));
            })
            ->when($request->filled('etiquette'), fn ($query) => $query->whereJsonContains('etiquettes', $request->string('etiquette')->toString()))
            ->when($request->input('statut') === 'ouvertes', fn ($query) => $query->where('resolue', false))
            ->when($request->input('statut') === 'resolues', fn ($query) => $query->where('resolue', true))
            ->when($request->filled('difficulte'), fn ($query) => $query->where('bug->difficulte', $request->string('difficulte')->toString()))
            ->latest()
            ->paginate($parPage)
            ->withQueryString();

        return DiscussionResource::collection($discussions);
    }

    public function store(CreerDiscussionRequest $request, DiscussionEnregistreur $enregistreur, Diffuseur $diffuseur): JsonResponse
    {
        /** @var User $auteur */
        $auteur = $request->user();
        $donnees = $request->validated();

        if (isset($donnees['canal'])) {
            $donnees['canal_id'] = Canal::query()->where('slug', $donnees['canal'])->value('id');
        }

        $discussion = $enregistreur->creer($auteur, $donnees);
        $discussion->setRelation('auteur', $auteur);
        $discussion->load('canal');
        $discussion->loadCount('messages');
        $diffuseur->envoyer(new DiscussionCreee($discussion));

        return (new DiscussionResource($discussion))->response()->setStatusCode(201);
    }

    public function show(Discussion $discussion): DiscussionResource
    {
        $discussion->load(['auteur', 'canal'])->loadCount('messages');

        return new DiscussionResource($discussion);
    }

    public function update(ModifierDiscussionRequest $request, Discussion $discussion, DiscussionEnregistreur $enregistreur, Diffuseur $diffuseur): DiscussionResource
    {
        $discussion = $enregistreur->modifier($discussion, $request->validated());
        $discussion->load(['auteur', 'canal'])->loadCount('messages');
        $diffuseur->envoyer(new DiscussionModifiee($discussion));

        return new DiscussionResource($discussion);
    }

    public function destroy(Request $request, Discussion $discussion, Diffuseur $diffuseur): Response
    {
        Gate::authorize('delete', $discussion);

        $id = $discussion->id;
        $discussion->delete();
        $diffuseur->envoyer(new DiscussionSupprimee($id));

        return response()->noContent();
    }
}
