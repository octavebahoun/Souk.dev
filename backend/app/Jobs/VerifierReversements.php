<?php

namespace App\Jobs;

use App\Models\Reversement;
use App\Paiement\Reversements;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Toutes les 15 minutes : revérifie les payouts encore en attente (GET /payouts/{id}/verify/). */
class VerifierReversements implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(Reversements $reversements): void
    {
        Reversement::query()->where('statut', 'en_attente')->orderBy('id')->each(function (Reversement $reversement) use ($reversements): void {
            if ($reversement->payout_id === null) {
                InitierReversement::dispatch($reversement->id);

                return;
            }

            $reversements->verifier($reversement);
        });
    }
}
