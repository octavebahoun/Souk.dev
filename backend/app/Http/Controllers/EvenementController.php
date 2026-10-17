<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Echange\Diffuseur;
use App\Events\EvenementAnnule;
use App\Events\EvenementModifie;
use App\Events\InscriptionsMaj;
use App\Http\Pagination;
use App\Http\Requests\CreerAvisRequest;
use App\Http\Requests\CreerEvenementRequest;
use App\Http\Requests\ModifierEvenementRequest;
use App\Http\Resources\AvisResource;
use App\Http\Resources\DevResource;
use App\Http\Resources\EvenementResource;
use App\Mail\EvenementAnnuleMail;
use App\Models\Evenement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class EvenementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage($request);

        $evenements = $this->avecCompte($request, Evenement::query())
            ->with('organisateur')
            ->latest('date')
            ->paginate($parPage)
            ->withQueryString();

        return EvenementResource::collection($evenements);
    }

    public function store(CreerEvenementRequest $request): JsonResponse
    {
        /** @var User $organisateur */
        $organisateur = $request->user();
        $donnees = $request->validated();
        $enLigne = $donnees['type'] === 'en_ligne';

        $evenement = Evenement::query()->create([
            'user_id' => $organisateur->id,
            'titre' => $donnees['titre'],
            'description' => $donnees['description'],
            'date' => $donnees['date'],
            'type' => $donnees['type'],
            'lieu' => $enLigne ? null : ($donnees['lieu'] ?? null),
            'lien' => $enLigne ? ($donnees['lien'] ?? null) : null,
        ]);
        $evenement->setRelation('organisateur', $organisateur);
        $evenement->setAttribute('inscriptions_count', 0);
        $evenement->setAttribute('est_inscrit', false);
        $evenement->setAttribute('avis_avg_note', null);

        return (new EvenementResource($evenement))->response()->setStatusCode(201);
    }

    public function show(Request $request, Evenement $evenement): EvenementResource
    {
        $evenement = $this->avecCompte($request, Evenement::query()->whereKey($evenement->id))
            ->with('organisateur')
            ->firstOrFail();

        return new EvenementResource($evenement);
    }

    public function update(ModifierEvenementRequest $request, Evenement $evenement, Diffuseur $diffuseur): EvenementResource
    {
        $donnees = $request->validated();
        $type = $donnees['type'] ?? $evenement->type;
        $enLigne = $type === 'en_ligne';

        $evenement->fill([
            'titre' => $donnees['titre'] ?? $evenement->titre,
            'description' => $donnees['description'] ?? $evenement->description,
            'date' => $donnees['date'] ?? $evenement->date,
            'type' => $type,
            'lieu' => $enLigne ? null : ($donnees['lieu'] ?? $evenement->lieu),
            'lien' => $enLigne ? ($donnees['lien'] ?? $evenement->lien) : null,
        ])->save();

        $evenement = $this->avecCompte($request, Evenement::query()->whereKey($evenement->id))
            ->with('organisateur')
            ->firstOrFail();
        $diffuseur->envoyer(new EvenementModifie($evenement));

        return new EvenementResource($evenement);
    }

    public function destroy(Evenement $evenement, Diffuseur $diffuseur): Response
    {
        Gate::authorize('delete', $evenement);

        $inscrits = $evenement->inscriptions()->with('compte')->get();
        $titre = $evenement->titre;
        $id = $evenement->id;

        foreach ($inscrits as $inscription) {
            if ($inscription->compte?->email !== null) {
                Mail::to($inscription->compte->email)->send(new EvenementAnnuleMail($titre));
            }
        }

        $evenement->delete();
        $diffuseur->envoyer(new EvenementAnnule($id));

        return response()->noContent();
    }

    public function inscrire(Request $request, Evenement $evenement, Diffuseur $diffuseur): Response
    {
        /** @var User $compte */
        $compte = $request->user();

        try {
            $evenement->inscriptions()->create(['user_id' => $compte->id]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous êtes déjà inscrit.');
        }

        $diffuseur->envoyer(new InscriptionsMaj($evenement->id, $evenement->inscriptions()->count()));

        return response()->noContent();
    }

    public function desinscrire(Request $request, Evenement $evenement, Diffuseur $diffuseur): Response
    {
        /** @var User $compte */
        $compte = $request->user();

        $evenement->inscriptions()->where('user_id', $compte->id)->delete();
        $diffuseur->envoyer(new InscriptionsMaj($evenement->id, $evenement->inscriptions()->count()));

        return response()->noContent();
    }

    public function participants(Request $request, Evenement $evenement): AnonymousResourceCollection
    {
        $compte = $request->user();

        if (! $compte instanceof User || ! $compte->can('voirParticipants', $evenement)) {
            abort(403, 'Action non autorisée.');
        }

        $parPage = Pagination::parPage($request);
        $comptes = User::query()
            ->whereIn('id', $evenement->inscriptions()->select('user_id'))
            ->orderBy('id')
            ->paginate($parPage)
            ->withQueryString();

        return DevResource::collection($comptes);
    }

    public function avis(Evenement $evenement): AnonymousResourceCollection
    {
        $parPage = Pagination::parPage(request());

        $avis = $evenement->avis()
            ->with('auteur')
            ->oldest()
            ->paginate($parPage)
            ->withQueryString();

        return AvisResource::collection($avis);
    }

    public function noter(CreerAvisRequest $request, Evenement $evenement): JsonResponse
    {
        /** @var User $compte */
        $compte = $request->user();

        $inscrit = $evenement->inscriptions()->where('user_id', $compte->id)->exists();

        if (! $inscrit || $evenement->date === null || ! $evenement->date->isPast()) {
            abort(403, 'Action non autorisée.');
        }

        if ($evenement->avis()->where('user_id', $compte->id)->exists()) {
            throw new ConflictHttpException('Vous avez déjà laissé un avis.');
        }

        try {
            $avis = $evenement->avis()->create([
                'user_id' => $compte->id,
                'note' => $request->integer('note'),
                'commentaire' => $request->validated('commentaire'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous avez déjà laissé un avis.');
        }

        $avis->setRelation('auteur', $compte);

        return (new AvisResource($avis))->response()->setStatusCode(201);
    }

    private function avecCompte(Request $request, Builder $requete): Builder
    {
        $compteId = $request->user()?->id ?? 0;

        return $requete
            ->withCount('inscriptions')
            ->withAvg('avis', 'note')
            ->withExists([
                'inscriptions as est_inscrit' => fn ($inscription) => $inscription->where('user_id', $compteId),
            ]);
    }
}
