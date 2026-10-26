<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Http\Requests\CreerAvisRequest;
use App\Http\Resources\AvisAppliResource;
use App\Models\Appli;
use App\Models\AvisAppli;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Avis des clients sur une appli : publics, déposés seulement par un client qui en a eu une copie
 * payée, un par client et par appli. La moyenne est recalculée sous le verrou de l'appli.
 */
final class AvisAppliController extends Controller
{
    public function index(Request $request, Appli $appli): JsonResponse
    {
        $parPage = Pagination::parPage($request);
        /** @var User|null $compte */
        $compte = $request->user();

        $avis = $appli->avis()
            ->with('auteur')
            ->latest()
            ->latest('id')
            ->paginate($parPage)
            ->withQueryString();

        $monAvis = $compte !== null ? $appli->avis()->with('auteur')->where('user_id', $compte->id)->first() : null;

        return AvisAppliResource::collection($avis)->additional([
            'peut_noter' => $compte !== null && $monAvis === null && $appli->peutEtreNoteePar($compte),
            'mon_avis' => $monAvis !== null ? (new AvisAppliResource($monAvis))->resolve($request) : null,
        ])->response();
    }

    public function store(CreerAvisRequest $request, Appli $appli): JsonResponse
    {
        /** @var User $compte */
        $compte = $request->user();

        Gate::authorize('noter', $appli);

        try {
            $avis = DB::transaction(function () use ($request, $appli, $compte): AvisAppli {
                $verrouillee = Appli::query()->lockForUpdate()->findOrFail($appli->id);

                if ($verrouillee->avis()->where('user_id', $compte->id)->exists()) {
                    throw new ConflictHttpException('Vous avez déjà noté cette appli : modifiez votre avis.');
                }

                $avis = $verrouillee->avis()->create([
                    'user_id' => $compte->id,
                    'note' => $request->integer('note'),
                    'commentaire' => $request->validated('commentaire'),
                ]);
                $verrouillee->recalculerAvis();

                return $avis;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous avez déjà noté cette appli : modifiez votre avis.');
        }

        $avis->setRelation('auteur', $compte);

        return (new AvisAppliResource($avis))->response()->setStatusCode(201);
    }

    public function update(CreerAvisRequest $request, Appli $appli): JsonResponse
    {
        /** @var User $compte */
        $compte = $request->user();

        $avis = DB::transaction(function () use ($request, $appli, $compte): AvisAppli {
            $verrouillee = Appli::query()->lockForUpdate()->findOrFail($appli->id);
            $avis = $verrouillee->avis()->where('user_id', $compte->id)->first()
                ?? throw new NotFoundHttpException('Vous n\'avez pas encore noté cette appli.');

            $avis->note = $request->integer('note');
            if (array_key_exists('commentaire', $request->validated())) {
                $avis->commentaire = $request->validated('commentaire');
            }
            $avis->save();
            $verrouillee->recalculerAvis();

            return $avis;
        });

        $avis->setRelation('auteur', $compte);

        return response()->json((new AvisAppliResource($avis))->resolve($request));
    }
}
