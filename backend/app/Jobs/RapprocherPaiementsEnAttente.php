<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Notifications\AlerteAdmin;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Toutes les 15 minutes : revérifie les paiements encore ouverts. C'est le filet si un webhook
 * n'arrive jamais (ou si aucun n'est déclaré) ; une session SasPay vit une heure.
 */
class RapprocherPaiementsEnAttente implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Planifié : jamais deux passages en même temps (withoutOverlapping ne protège pas la file). */
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(Encaissement $encaissement): void
    {
        // Sessions encore ouvertes, et tentatives « annulées » qui portent une transaction : un
        // succès tardif (USSD validé après l'expiration de la session) doit être crédité.
        Paiement::query()
            ->whereNotNull('session_id')
            ->where(fn ($requete) => $requete
                ->whereIn('statut', ['en_attente', 'echoue'])
                ->orWhere(fn ($annule) => $annule->where('statut', 'annule')->whereNotNull('transaction_id')))
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->each(function (Paiement $paiement) use ($encaissement): void {
                try {
                    $encaissement->verifier($paiement);
                } catch (PasserelleIndisponible|PasserelleRefus $exception) {
                    Log::warning('paiement: rapprochement impossible', ['paiement' => $paiement->id, 'erreur' => $exception->getMessage()]);
                }
            });

        // Une session de plus de deux jours sans aucune transaction est abandonnée.
        Paiement::query()
            ->whereIn('statut', ['en_attente', 'echoue'])
            ->whereNull('transaction_id')
            ->where('created_at', '<', now()->subDays(2))
            ->update(['statut' => 'annule', 'updated_at' => now()]);

        // Une transaction toujours en attente après deux jours : l'équipe est prévenue une fois.
        Paiement::query()
            ->where('statut', 'en_attente')
            ->whereNotNull('transaction_id')
            ->whereNull('erreur')
            ->where('created_at', '<', now()->subDays(2))
            ->orderBy('id')
            ->each(function (Paiement $paiement): void {
                $paiement->forceFill(['erreur' => 'Transaction toujours en attente chez SasPay.'])->save();
                AlerteAdmin::envoyer("Paiement n° {$paiement->id} bloqué en attente", [
                    'La transaction '.($paiement->reference ?? $paiement->transaction_id).' est en attente chez SasPay depuis plus de deux jours.',
                    'Vérifiez-la dans le tableau de bord SasPay ; elle continue d’être revérifiée pendant sept jours.',
                ]);
            });
    }
}
