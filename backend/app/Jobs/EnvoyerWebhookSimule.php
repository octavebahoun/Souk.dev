<?php

namespace App\Jobs;

use App\Models\SimulationSasPay;
use App\Paiement\SignatureWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Le simulateur envoie, comme SasPay, un webhook signé au format de leur documentation
 * (event + data, en-têtes X-Webhook-Signature, X-Webhook-Timestamp, X-Webhook-Event).
 */
class EnvoyerWebhookSimule implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public string $simulationId) {}

    public function handle(): void
    {
        $simulation = SimulationSasPay::query()->find($this->simulationId);

        if ($simulation === null || $simulation->transaction_id === null) {
            return;
        }

        $evenement = $simulation->statut === 'SUCCESS' ? 'transaction.success' : 'transaction.failed';
        $corps = (string) json_encode([
            'event' => $evenement,
            'data' => [
                'id' => $simulation->transaction_id,
                'reference' => $simulation->reference,
                'type' => 'PAIEMENT',
                'status' => $simulation->statut,
                'amount' => $simulation->montant.'.00',
                'fee' => $simulation->frais.'.00',
                'charged' => ($simulation->montant + $simulation->frais).'.00',
                'net_amount' => $simulation->montant.'.00',
                'fee_charge_mode' => 'ADD_ON',
                'currency' => 'XOF',
                'country' => 'BJ',
                'network' => $simulation->reseau ?? 'mtn_bj',
                'msisdn' => '',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $horodatage = time();
        $signature = SignatureWebhook::depuisConfig()->signer($corps, $horodatage);

        Http::withHeaders([
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Timestamp' => (string) $horodatage,
            'X-Webhook-Event' => $evenement,
        ])->withBody($corps, 'application/json')->timeout(15)->post((string) config('facturation.webhook_simulateur'))->throw();
    }
}
