<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Jobs\ReactiverCopieAbus;
use App\Jobs\SuspendreCopieAbus;
use App\Models\Deploiement;
use App\Notifications\CopieSuspendueAbus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Copies vues par l'équipe : liste, suspension pour abus (minage, hameçonnage…) et réactivation.
 * La suspension pour abus ne touche pas la facturation : une copie suspendue pour impayé ne se
 * réactive qu'au paiement, et une copie suspendue pour abus reste suspendue tant que l'équipe
 * ne lève pas la sanction, même à jour de paiement.
 */
final class AdminCopiesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'etat' => ['sometimes', 'string', 'max:20'],
            'suspension_admin' => ['sometimes', 'boolean'],
        ]);

        return JsonResource::collection(Deploiement::query()
            ->when($request->filled('etat'), fn ($requete) => $requete->where('etat', $request->string('etat')->toString()))
            ->when($request->boolean('suspension_admin'), fn ($requete) => $requete->whereNotNull('suspension_admin_motif'))
            ->with(['appli', 'utilisateur', 'machine'])
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString()
            ->through(fn (Deploiement $copie) => $copie->versApi()));
    }

    public function suspendre(Request $request, Deploiement $deploiement): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $deploiement = Deploiement::query()->lockForUpdate()->findOrFail($deploiement->id);

        if ($deploiement->suspension_admin_motif !== null) {
            throw new ConflictHttpException('Cette copie est déjà suspendue pour abus.');
        }

        if ($deploiement->etat !== 'en_ligne') {
            throw new ConflictHttpException('Seule une copie en ligne peut être suspendue pour abus.');
        }

        $deploiement->forceFill(['suspension_admin_motif' => $donnees['motif']])->save();
        $deploiement->changerEtat('suspendu');
        SuspendreCopieAbus::dispatch($deploiement->id);

        $deploiement->loadMissing('utilisateur');
        $deploiement->utilisateur?->notify(new CopieSuspendueAbus($deploiement, $donnees['motif']));

        return response()->json($deploiement->refresh()->versApi());
    }

    public function reactiver(Deploiement $deploiement): JsonResponse
    {
        $deploiement = Deploiement::query()->with('abonnement')->lockForUpdate()->findOrFail($deploiement->id);

        if ($deploiement->suspension_admin_motif === null) {
            throw new ConflictHttpException('Cette copie n’est pas suspendue pour abus.');
        }

        if (in_array($deploiement->abonnement?->statut, ['suspendu', 'resilie'], true)) {
            throw new ConflictHttpException('Cette copie est aussi suspendue pour impayé : elle repartira au paiement.');
        }

        $deploiement->forceFill(['suspension_admin_motif' => null])->save();
        ReactiverCopieAbus::dispatch($deploiement->id);

        return response()->json($deploiement->refresh()->versApi());
    }
}
