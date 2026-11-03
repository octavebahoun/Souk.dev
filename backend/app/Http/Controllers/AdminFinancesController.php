<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\FactureModifiee;
use App\Http\Pagination;
use App\Http\Resources\DevResource;
use App\Http\Resources\FactureResource;
use App\Models\Abonnement;
use App\Models\Avoir;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Rapprochement;
use App\Models\Reversement;
use App\Paiement\GrandLivre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Vue financière de l'admin, tirée du grand livre, et saisie des avoirs (SasPay n'a pas
 * d'API de remboursement : l'admin rembourse depuis leur tableau de bord, puis saisit l'avoir).
 */
final class AdminFinancesController extends Controller
{
    public function finances(Request $request, GrandLivre $grandLivre): JsonResponse
    {
        $donnees = $request->validate([
            'debut' => ['sometimes', 'date_format:Y-m-d'],
            'fin' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:debut'],
        ]);
        $debut = isset($donnees['debut']) ? Carbon::parse($donnees['debut'])->startOfDay() : now()->startOfMonth();
        $fin = isset($donnees['fin']) ? Carbon::parse($donnees['fin'])->addDay()->startOfDay() : now()->addDay()->startOfDay();

        $revenus = -$grandLivre->solde(Ecriture::COMPTE_REVENU_HEBERGEMENT, $debut, $fin, 'encaissement');
        $avoirsHebergement = $grandLivre->solde(Ecriture::COMPTE_REVENU_HEBERGEMENT, $debut, $fin, 'avoir');
        // Charges de Souk.dev : frais des payouts, et frais retenus par SasPay sur un encaissement.
        $frais = $grandLivre->solde(Ecriture::COMPTE_FRAIS_REVERSEMENT, $debut, $fin);
        $fraisEncaissement = $grandLivre->solde(Ecriture::COMPTE_FRAIS_ENCAISSEMENT, $debut, $fin);
        $reverse = Ecriture::query()->where('compte', Ecriture::COMPTE_DEV)->whereIn('type', ['reversement', 'contrepassation'])
            ->whereNotNull('reversement_id')->where('cree_le', '>=', $debut)->where('cree_le', '<', $fin)->sum('montant');

        return response()->json([
            'debut' => $debut->toDateString(),
            'fin' => $fin->copy()->subDay()->toDateString(),
            'encaissements' => $grandLivre->solde(Ecriture::COMPTE_SASPAY, $debut, $fin, 'encaissement'),
            'revenus_hebergement' => $revenus,
            'parts_devs' => -$grandLivre->solde(Ecriture::COMPTE_DEV, $debut, $fin, 'encaissement'),
            'reversements' => (int) $reverse,
            'frais_reversement' => $frais,
            'avoirs' => -$grandLivre->solde(Ecriture::COMPTE_SASPAY, $debut, $fin, 'avoir'),
            'marge' => $revenus - $frais - $fraisEncaissement - $avoirsHebergement,
            'solde_devs' => -$grandLivre->solde(Ecriture::COMPTE_DEV),
            'solde_saspay_attendu' => $grandLivre->solde(Ecriture::COMPTE_SASPAY),
            'factures_impayees' => Facture::query()->where('statut', 'a_payer')->where('echeance_le', '<', today())->count(),
            'copies_suspendues' => Abonnement::query()->where('statut', 'suspendu')->count(),
            'trop_percus' => $this->tropPercus($grandLivre),
        ]);
    }

    /** Remboursement d'un paiement en double, fait depuis le tableau de bord SasPay. */
    public function rembourserTropPercu(Request $request, Paiement $paiement, GrandLivre $grandLivre): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $montant = DB::transaction(function () use ($paiement, $grandLivre, $donnees): int {
            Paiement::query()->lockForUpdate()->findOrFail($paiement->id);
            $reste = $grandLivre->tropPercuDe($paiement->id);

            if ($reste <= 0) {
                throw new ConflictHttpException('Ce paiement n’est pas un paiement en double à rembourser.');
            }

            $grandLivre->rembourserTropPercu($paiement, $reste, $donnees['motif']);

            return $reste;
        });

        return response()->json($this->tropPercu($paiement->load('facture'), $montant, true), 201);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tropPercus(GrandLivre $grandLivre): array
    {
        $restes = $grandLivre->tropPercusARembourser();

        return Paiement::query()
            ->whereIn('id', array_keys($restes))
            ->with('facture')
            ->orderBy('id')
            ->get()
            ->map(fn (Paiement $paiement) => $this->tropPercu($paiement, $restes[$paiement->id], false))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function tropPercu(Paiement $paiement, int $montant, bool $rembourse): array
    {
        return [
            'paiement_id' => $paiement->id,
            'facture_id' => $paiement->facture_id,
            'facture_numero' => (string) $paiement->facture?->numero,
            'montant' => $montant,
            'reference' => $paiement->reference,
            'recu_le' => ($paiement->verifie_le ?? $paiement->updated_at)->toIso8601String(),
            'rembourse' => $rembourse,
        ];
    }

    public function factures(Request $request): AnonymousResourceCollection
    {
        $request->validate(['statut' => ['sometimes', 'in:a_payer,payee,annulee,remboursee']]);

        return FactureResource::collection(Facture::query()
            ->when($request->filled('statut'), fn ($requete) => $requete->where('statut', $request->string('statut')->toString()))
            ->with(['abonnement.appli', 'abonnement.deploiement.appli', 'paiements', 'avoirs'])
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->withQueryString());
    }

    public function avoir(Request $request, Facture $facture, GrandLivre $grandLivre): JsonResponse
    {
        $donnees = $request->validate([
            'montant_hebergement' => ['required', 'integer', 'min:0'],
            'montant_appli' => ['required', 'integer', 'min:0'],
            'motif' => ['required', 'string', 'max:500'],
        ]);

        $avoir = DB::transaction(function () use ($facture, $donnees, $request, $grandLivre): Avoir {
            $facture = Facture::query()->lockForUpdate()->findOrFail($facture->id);

            if (! in_array($facture->statut, ['payee', 'remboursee'], true)) {
                throw new ConflictHttpException('Seule une facture payée peut recevoir un avoir.');
            }

            $deja = Avoir::query()->where('facture_id', $facture->id);
            $resteHebergement = $facture->montant_hebergement - (int) (clone $deja)->sum('montant_hebergement');
            $resteAppli = $facture->montant_appli - (int) (clone $deja)->sum('montant_appli');

            if ($donnees['montant_hebergement'] + $donnees['montant_appli'] === 0) {
                throw ValidationException::withMessages(['montant_hebergement' => 'L’avoir doit porter sur un montant.']);
            }

            if ($donnees['montant_hebergement'] > $resteHebergement || $donnees['montant_appli'] > $resteAppli) {
                throw ValidationException::withMessages(['montant_hebergement' => "L’avoir dépasse le reste remboursable ({$resteHebergement} F d’hébergement, {$resteAppli} F d’appli)."]);
            }

            $avoir = Avoir::query()->create([...$donnees, 'facture_id' => $facture->id, 'admin_id' => $request->user()->id]);
            $avoir->numeroter();
            $grandLivre->avoir($avoir);

            if ($donnees['montant_hebergement'] === $resteHebergement && $donnees['montant_appli'] === $resteAppli) {
                $facture->forceFill(['statut' => 'remboursee'])->save();
            }

            return $avoir;
        });

        event(new FactureModifiee($facture->refresh()));

        return response()->json($avoir->versApi(), 201);
    }

    public function reversements(Request $request): AnonymousResourceCollection
    {
        return JsonResource::collection(Reversement::query()
            ->with('dev')
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->through(fn (Reversement $reversement) => [
                'id' => $reversement->id,
                'montant' => $reversement->montant,
                'statut' => $reversement->statut,
                'reseau' => $reversement->reseau,
                'numero_masque' => $reversement->numero_masque,
                'declenchement' => $reversement->declenchement,
                'cree_le' => $reversement->created_at->toIso8601String(),
                'verifie_le' => $reversement->verifie_le?->toIso8601String(),
                'erreur' => $reversement->erreur,
                'dev' => DevResource::champs($reversement->dev),
                'frais' => $reversement->frais,
            ]));
    }

    public function rapprochements(Request $request): AnonymousResourceCollection
    {
        return JsonResource::collection(Rapprochement::query()
            ->latest('jour')
            ->paginate(Pagination::parPage($request))
            ->through(fn (Rapprochement $rapprochement) => [
                'id' => $rapprochement->id,
                'jour' => $rapprochement->jour->toDateString(),
                'transactions_saspay' => $rapprochement->transactions_saspay,
                'paiements_locaux' => $rapprochement->paiements_locaux,
                'ecarts' => $rapprochement->ecarts,
                'cree_le' => $rapprochement->created_at->toIso8601String(),
            ]));
    }
}
