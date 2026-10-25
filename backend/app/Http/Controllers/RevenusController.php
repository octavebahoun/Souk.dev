<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Models\CoordonneesReversement;
use App\Models\Ecriture;
use App\Models\Reversement;
use App\Models\User;
use App\Paiement\GrandLivre;
use App\Paiement\Reversements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Mes revenus » du dev : solde réel (grand livre), mouvements, reversements, demande de
 * reversement. Les données ne concernent que le compte connecté : aucun identifiant dans l'URL.
 */
final class RevenusController extends Controller
{
    public function resume(Request $request, GrandLivre $grandLivre, Reversements $reversements): JsonResponse
    {
        /** @var User $dev */
        $dev = $request->user();
        $enCours = $reversements->enCours($dev);
        $raison = $reversements->blocage($dev);
        $coordonnees = CoordonneesReversement::query()->where('user_id', $dev->id)->first();

        return response()->json([
            'solde' => $grandLivre->soldeDev($dev->id),
            'encaisse_total' => $grandLivre->encaisseDev($dev->id),
            'encaisse_mois' => $grandLivre->encaisseDev($dev->id, now()->startOfMonth()),
            'reverse_total' => (int) Reversement::query()->where('dev_id', $dev->id)->where('statut', 'reussi')->sum('montant'),
            'seuil_demande' => (int) config('facturation.seuil_reversement'),
            'peut_demander' => $raison === null,
            'raison_blocage' => $raison,
            'reversement_en_cours' => $enCours === null ? null : $this->reversement($enCours),
            'prochain_reversement_le' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'coordonnees' => $coordonnees?->versApi(),
        ]);
    }

    public function mouvements(Request $request): JsonResponse
    {
        $mouvements = Ecriture::query()
            ->where('compte', Ecriture::COMPTE_DEV)
            ->where('dev_id', $request->user()->id)
            ->orderByDesc('cree_le')
            ->orderByDesc('id')
            ->paginate(Pagination::parPage($request))
            ->through(fn (Ecriture $ecriture) => [
                'id' => $ecriture->id,
                'type' => match ($ecriture->type) {
                    'contrepassation' => 'reversement_annule',
                    'encaissement', 'reversement', 'avoir' => $ecriture->type,
                    default => 'encaissement',
                },
                // Vu par le dev : ce qu'il gagne est positif.
                'montant' => -$ecriture->montant,
                'libelle' => $ecriture->libelle,
                'cree_le' => $ecriture->cree_le->toIso8601String(),
            ]);

        return response()->json($mouvements);
    }

    public function reversements(Request $request): JsonResponse
    {
        return response()->json(Reversement::query()
            ->where('dev_id', $request->user()->id)
            ->latest('id')
            ->paginate(Pagination::parPage($request))
            ->through(fn (Reversement $reversement) => $this->reversement($reversement)));
    }

    public function demander(Request $request, Reversements $reversements): JsonResponse
    {
        $reversement = $reversements->demander($request->user(), 'demande');

        return response()->json($this->reversement($reversement->refresh()), 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function reversement(Reversement $reversement): array
    {
        return [
            'id' => $reversement->id,
            'montant' => $reversement->montant,
            'statut' => $reversement->statut,
            'reseau' => $reversement->reseau,
            'numero_masque' => $reversement->numero_masque,
            'declenchement' => $reversement->declenchement,
            'cree_le' => $reversement->created_at->toIso8601String(),
            'verifie_le' => $reversement->verifie_le?->toIso8601String(),
            'erreur' => $reversement->erreur,
        ];
    }
}
