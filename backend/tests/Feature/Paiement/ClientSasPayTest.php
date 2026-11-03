<?php

namespace Tests\Feature\Paiement;

use App\Paiement\Passerelles\ClientSasPay;
use App\Paiement\Passerelles\DemandeReversement;
use App\Paiement\Passerelles\DemandeSession;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use App\Paiement\Passerelles\SasPayPaiement;
use App\Paiement\Passerelles\SasPayReversement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Adaptateur SasPay : seuls les champs de leur spécification, montants en chaîne décimale,
 * Idempotency-Key sur le payout, et chaque réponse d'erreur documentée bien classée.
 */
class ClientSasPayTest extends TestCase
{
    private const CLE = 'sk_live_cle_de_test_qui_ne_doit_jamais_sortir';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_la_session_de_checkout_envoie_le_contrat_de_saspay(): void
    {
        Http::fake(['saspay.test/api/v1/checkout-sessions/' => Http::response([
            'success' => true,
            'data' => ['id' => 'c9a17e2c-4b1a-4f0e-9c3d-2a1b3c4d5e6f', 'slug' => 'kZ2v9rT4', 'checkout_url' => 'https://pay.saspay.me/c/kZ2v9rT4'],
        ], 201)]);

        $session = $this->paiement()->creerSession(new DemandeSession(
            montant: 3500,
            description: 'Souk.dev — Pharmacie — facture SD-2026-000001',
            email: 'client@exemple.test',
            nom: 'Awa',
            urlRetour: 'http://localhost:5173/paiement/retour?facture=1',
            metadata: ['facture_id' => 1, 'paiement_id' => 7],
        ));

        $this->assertSame('https://pay.saspay.me/c/kZ2v9rT4', $session->urlPaiement);
        Http::assertSent(function (Request $requete): bool {
            return $requete->url() === 'https://saspay.test/api/v1/checkout-sessions/'
                && $requete->hasHeader('Authorization', 'Bearer '.self::CLE)
                && $requete['amount'] === '3500.00'
                && $requete['currency'] === 'XOF'
                && $requete['fee_charge_mode'] === 'ADD_ON'
                && $requete['metadata'] === ['facture_id' => 1, 'paiement_id' => 7]
                && ! $requete->hasHeader('Idempotency-Key');
        });
    }

    public function test_le_payout_porte_la_cle_d_idempotence(): void
    {
        Http::fake(['saspay.test/api/v1/payouts/initialize/' => Http::response(['message' => 'Payout transaction initialized successfully', 'id' => 'a4b5c6d7-e8f9-4a0b-8c1d-2e3f4a5b6c7d'], 201)]);

        $id = $this->reversement()->initier(new DemandeReversement(
            montant: 15000, pays: 'BJ', reseau: 'mtn_bj', numero: '2290197000000',
            email: 'dev@exemple.test', nom: 'Kofi', description: 'Souk.dev — reversement n° 3',
        ), '0d0c8a5e-9b39-4d47-9a0e-5a1f2b3c4d5e');

        $this->assertSame('a4b5c6d7-e8f9-4a0b-8c1d-2e3f4a5b6c7d', $id);
        Http::assertSent(fn (Request $requete) => $requete->hasHeader('Idempotency-Key', '0d0c8a5e-9b39-4d47-9a0e-5a1f2b3c4d5e')
            && $requete['amount'] === '15000.00'
            && $requete['recipient'] === ['msisdn' => '2290197000000']
            && $requete['method'] === 'mtn_bj'
            && $requete['fee_charge_mode'] === 'ADD_ON');
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function refus(): array
    {
        return [
            'clé invalide' => [401, 'authentication_failed'],
            'compte ou IP refusés' => [403, 'ip_not_whitelisted'],
            'conflit' => [409, 'idempotency_conflict'],
            'session expirée' => [410, 'expired'],
            'opération impossible' => [422, 'insufficient_balance'],
        ];
    }

    #[DataProvider('refus')]
    public function test_un_refus_documente_devient_un_refus_metier(int $statut, string $code): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['message' => 'Refusé.', 'code' => $code], $statut)]);

        try {
            $this->paiement()->statutSession('c9a17e2c-4b1a-4f0e-9c3d-2a1b3c4d5e6f');
            $this->fail('Le refus aurait dû remonter.');
        } catch (PasserelleRefus $refus) {
            $this->assertSame($statut, $refus->statutHttp);
            $this->assertSame($code, $refus->codeErreur);
        }

        $this->assertLaCleNestPasJournalisee();
    }

    public function test_429_et_les_erreurs_du_prestataire_se_reessaient(): void
    {
        foreach ([429, 500, 503] as $statut) {
            Http::fake(['*' => Http::response(['message' => 'Plus tard.'], $statut)]);

            try {
                $this->paiement()->verifierPaiement('9c3f2a10-4b7e-4f1a-9d2e-9b6a7c1e4a02');
                $this->fail("{$statut} aurait dû être une indisponibilité.");
            } catch (PasserelleIndisponible) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_une_coupure_reseau_est_une_indisponibilite(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('Délai dépassé'));

        $this->expectException(PasserelleIndisponible::class);

        try {
            $this->paiement()->statutSession('c9a17e2c-4b1a-4f0e-9c3d-2a1b3c4d5e6f');
        } finally {
            $this->assertLaCleNestPasJournalisee();
        }
    }

    public function test_un_identifiant_qui_n_est_pas_un_uuid_n_entre_jamais_dans_l_url(): void
    {
        $this->expectException(PasserelleRefus::class);

        $this->paiement()->verifierPaiement('../../payouts/initialize');
    }

    public function test_la_verification_lit_les_montants_en_entiers(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'id' => '9c3f2a10-4b7e-4f1a-9d2e-9b6a7c1e4a02', 'status' => 'SUCCESS', 'requested_amount' => '18500.00',
            'client_fee' => '647.50', 'debited_amount' => '19147.50', 'net_amount' => '18500.00', 'currency' => 'XOF', 'reference' => 'TXN-1',
        ]])]);

        $verification = $this->paiement()->verifierPaiement('9c3f2a10-4b7e-4f1a-9d2e-9b6a7c1e4a02');

        $this->assertTrue($verification->reussi());
        $this->assertSame(18500, $verification->montantDemande);
        $this->assertSame('XOF', $verification->devise);
    }

    private function paiement(): SasPayPaiement
    {
        return new SasPayPaiement(new ClientSasPay(self::CLE, 'https://saspay.test/api/v1'));
    }

    private function reversement(): SasPayReversement
    {
        return new SasPayReversement(new ClientSasPay(self::CLE, 'https://saspay.test/api/v1'));
    }

    private function assertLaCleNestPasJournalisee(): void
    {
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $niveau) {
            Log::shouldNotHaveReceived($niveau, fn (...$arguments) => str_contains((string) json_encode($arguments), self::CLE));
        }

        $this->addToAssertionCount(1);
    }
}
