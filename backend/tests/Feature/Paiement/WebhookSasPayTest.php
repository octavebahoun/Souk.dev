<?php

namespace Tests\Feature\Paiement;

use App\Jobs\TraiterWebhookSasPay;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\SimulationSasPay;
use App\Models\WebhookSasPay;
use App\Paiement\SignatureWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/webhooks/saspay : signature HMAC sur le corps brut, horodatage de moins de 300 s,
 * traitement idempotent, et jamais de changement sans revérification par l'API.
 */
class WebhookSasPayTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_signature_fausse_est_refusee(): void
    {
        Queue::fake();
        $corps = $this->corps('transaction.success', 'f3b1c2d4-0000-4000-8000-000000000001');

        $this->webhook($corps, time(), signature: str_repeat('a', 64))->assertUnauthorized();

        $this->assertSame(0, WebhookSasPay::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_un_horodatage_de_plus_de_300_secondes_est_refuse(): void
    {
        Queue::fake();
        $corps = $this->corps('transaction.success', 'f3b1c2d4-0000-4000-8000-000000000001');

        $this->webhook($corps, time() - 301)->assertUnauthorized();
        $this->webhook($corps, time() + 301)->assertUnauthorized();
        $this->webhook($corps, time() - 290)->assertOk();
    }

    public function test_la_signature_couvre_le_corps_brut(): void
    {
        Queue::fake();
        $corps = $this->corps('transaction.success', 'f3b1c2d4-0000-4000-8000-000000000001');
        $horodatage = time();
        $signature = $this->signer($corps, $horodatage);

        // Même JSON, autre mise en forme : la signature ne correspond plus.
        $this->webhook(json_encode(json_decode($corps), JSON_PRETTY_PRINT), $horodatage, $signature)->assertUnauthorized();
        $this->webhook($corps, $horodatage, $signature)->assertOk();
    }

    public function test_un_webhook_rejoue_n_est_traite_qu_une_fois(): void
    {
        Queue::fake();
        $corps = $this->corps('transaction.success', 'f3b1c2d4-0000-4000-8000-000000000001');
        $horodatage = time();

        $this->webhook($corps, $horodatage)->assertOk()->assertJsonPath('message', 'Reçu.');
        $this->webhook($corps, $horodatage)->assertOk()->assertJsonPath('message', 'Déjà reçu.');

        $this->assertSame(1, WebhookSasPay::query()->count());
        Queue::assertPushed(TraiterWebhookSasPay::class, 1);
    }

    public function test_un_evenement_hors_transaction_est_accuse_sans_traitement(): void
    {
        Queue::fake();

        $this->webhook((string) json_encode(['event' => 'webhook.test', 'data' => []]), time())->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_un_webhook_bien_signe_ne_paie_rien_sans_la_verification(): void
    {
        // Un webhook « success » pour une session que le prestataire voit encore en attente.
        $facture = Facture::factory()->create();
        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id, 'client_id' => $facture->client_id, 'passerelle' => 'simulee',
            'session_id' => SimulationSasPay::query()->create(['type' => 'session', 'montant' => 18500, 'statut' => 'PENDING'])->id,
            'montant' => 18500, 'statut' => 'en_attente',
        ]);

        $this->webhook($this->corps('transaction.success', 'f3b1c2d4-0000-4000-8000-000000000009'), time())->assertOk();

        $this->assertSame('en_attente', $paiement->refresh()->statut);
        $this->assertSame('a_payer', $facture->refresh()->statut);
        $this->assertNotNull(WebhookSasPay::query()->sole()->traite_le);
    }

    public function test_une_transaction_simplement_creee_ne_declenche_rien(): void
    {
        Queue::fake();

        $this->webhook($this->corps('transaction.created', 'f3b1c2d4-0000-4000-8000-000000000001'), time())->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_une_rafale_de_webhooks_n_est_pas_bridee_comme_un_visiteur(): void
    {
        Queue::fake();

        foreach (range(1, 70) as $numero) {
            $this->webhook($this->corps('transaction.success', sprintf('f3b1c2d4-0000-4000-8000-%012d', $numero)), time())->assertOk();
        }
    }

    public function test_un_corps_illisible_repond_400(): void
    {
        $this->webhook('pas du json', time())->assertBadRequest();
    }

    private function corps(string $evenement, string $transaction): string
    {
        return (string) json_encode([
            'event' => $evenement,
            'data' => [
                'id' => $transaction,
                'reference' => 'TXN-2026-000456',
                'type' => 'PAIEMENT',
                'status' => 'SUCCESS',
                'amount' => '18500.00',
                'fee' => '648.00',
                'charged' => '19148.00',
                'net_amount' => '18500.00',
                'fee_charge_mode' => 'ADD_ON',
                'currency' => 'XOF',
                'country' => 'BJ',
                'network' => 'mtn_bj',
                'msisdn' => '22990000000',
            ],
        ]);
    }

    private function signer(string $corps, int $horodatage): string
    {
        return (new SignatureWebhook('secret-webhook-de-test'))->signer($corps, $horodatage);
    }

    private function webhook(string $corps, int $horodatage, ?string $signature = null): TestResponse
    {
        return $this->call('POST', '/api/webhooks/saspay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature ?? $this->signer($corps, $horodatage),
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $horodatage,
            'HTTP_X_WEBHOOK_EVENT' => 'transaction.success',
        ], $corps);
    }
}
