<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Models\Reversement;
use App\Models\WebhookSasPay;
use App\Paiement\Encaissement;
use App\Paiement\Reversements;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Un webhook ne prouve rien : il déclenche une revérification par l'API. On cherche le paiement
 * (ou le payout) par sa transaction ; inconnue (le client vient de payer, la transaction n'est
 * pas encore liée à la session), on revérifie les sessions récentes.
 */
class TraiterWebhookSasPay implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $webhookId)
    {
        $this->onQueue('paiements');
    }

    public function handle(Encaissement $encaissement, Reversements $reversements): void
    {
        $webhook = WebhookSasPay::query()->find($this->webhookId);

        if ($webhook === null || $webhook->traite_le !== null) {
            return;
        }

        $reversement = $webhook->transaction_id !== null
            ? Reversement::query()->where('payout_id', $webhook->transaction_id)->first()
            : null;

        if ($reversement !== null) {
            $reversements->verifier($reversement);
            $webhook->forceFill(['traite_le' => now()])->save();

            return;
        }

        $paiements = $webhook->transaction_id !== null
            ? Paiement::query()->where('transaction_id', $webhook->transaction_id)->get()
            : collect();

        if ($paiements->isEmpty()) {
            $paiements = Paiement::query()
                ->whereNotNull('session_id')
                ->whereIn('statut', ['en_attente', 'echoue'])
                ->where('created_at', '>=', now()->subHours(2))
                ->latest('id')
                ->limit(20)
                ->get();
        }

        foreach ($paiements as $paiement) {
            $encaissement->verifier($paiement);
        }

        $webhook->forceFill(['traite_le' => now()])->save();
    }
}
