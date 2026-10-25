<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Toutes les 15 minutes : revérifie les paiements encore ouverts. C'est le filet si un webhook
 * n'arrive jamais (ou si aucun n'est déclaré) ; une session SasPay vit une heure.
 */
class RapprocherPaiementsEnAttente implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(Encaissement $encaissement): void
    {
        Paiement::query()
            ->whereNotNull('session_id')
            ->whereIn('statut', ['en_attente', 'echoue'])
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')
            ->each(function (Paiement $paiement) use ($encaissement): void {
                try {
                    $encaissement->verifier($paiement);
                } catch (PasserelleIndisponible|PasserelleRefus $exception) {
                    Log::warning('paiement: rapprochement impossible', ['paiement' => $paiement->id, 'erreur' => $exception->getMessage()]);
                }
            });

        // Une session de plus de deux jours sans transaction réussie est abandonnée.
        Paiement::query()
            ->whereIn('statut', ['en_attente', 'echoue'])
            ->where('created_at', '<', now()->subDays(2))
            ->update(['statut' => 'annule', 'updated_at' => now()]);
    }
}
