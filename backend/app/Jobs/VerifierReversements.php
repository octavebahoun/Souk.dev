<?php

namespace App\Jobs;

use App\Models\Reversement;
use App\Paiement\Reversements;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Toutes les 15 minutes : revérifie les payouts encore en attente (GET /payouts/{id}/verify/). */
class VerifierReversements implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Planifié : jamais deux passages en même temps (withoutOverlapping ne protège pas la file). */
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(Reversements $reversements): void
    {
        Reversement::query()->where('statut', 'en_attente')->orderBy('id')->each(function (Reversement $reversement) use ($reversements): void {
            // Bloqué depuis un jour (409 sans fin, PENDING éternel) : l'équipe est prévenue une fois.
            if ($reversement->created_at->lt(now()->subDay())) {
                $reversements->aVerifier($reversement, 'Reversement toujours en attente après 24 heures.');
            }

            if ($reversement->payout_id === null) {
                InitierReversement::dispatch($reversement->id);

                return;
            }

            $reversements->verifier($reversement);
        });
    }
}
