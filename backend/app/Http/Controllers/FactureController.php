<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Http\Resources\FactureResource;
use App\Http\Resources\PaiementResource;
use App\Models\Facture;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * « Mes paiements » : factures du client, paiement par session SasPay, vérification au retour.
 */
final class FactureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['statut' => ['sometimes', 'in:a_payer,payee,annulee,remboursee']]);

        $factures = Facture::query()
            ->where('client_id', $request->user()->id)
            ->when($request->filled('statut'), fn ($requete) => $requete->where('statut', $request->string('statut')->toString()))
            ->with(['abonnement.appli', 'abonnement.deploiement.appli', 'paiements', 'avoirs'])
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString();

        return FactureResource::collection($factures);
    }

    public function show(Facture $facture): FactureResource
    {
        Gate::authorize('view', $facture);

        return new FactureResource($facture);
    }

    public function payer(Request $request, Facture $facture, Encaissement $encaissement): JsonResponse
    {
        Gate::authorize('payer', $facture);

        try {
            $paiement = $encaissement->ouvrirSession($facture, $request->user());
        } catch (PasserelleIndisponible $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (PasserelleRefus $exception) {
            // 401/403 : clé ou compte marchand refusés ; le client ne peut rien y faire.
            Log::error('saspay: session refusée', ['facture' => $facture->id, 'statut' => $exception->statutHttp, 'code' => $exception->codeErreur]);

            return response()->json(['message' => 'Le paiement est momentanément indisponible. Réessayez plus tard ou contactez le support.'], 503);
        }

        return response()->json([
            'paiement' => (new PaiementResource($paiement))->resolve(),
            'checkout_url' => $paiement->checkout_url,
        ], 201);
    }

    public function verifier(Facture $facture, Encaissement $encaissement): FactureResource
    {
        Gate::authorize('view', $facture);

        return new FactureResource($encaissement->verifierFacture($facture));
    }
}
