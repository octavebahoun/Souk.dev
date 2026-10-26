<?php

namespace Tests\Feature\Paiement;

use App\Events\FactureModifiee;
use App\Jobs\LancerDeploiement;
use App\Jobs\RapprochementQuotidien;
use App\Jobs\RapprocherPaiementsEnAttente;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Rapprochement;
use App\Notifications\AlerteAdmin;
use App\Notifications\EcartRapprochement;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\DemandeSession;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserellePaiement;
use App\Paiement\Passerelles\SessionPaiement;
use App\Paiement\Passerelles\StatutSession;
use App\Paiement\Passerelles\VerificationPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ce que SasPay répond vraiment, cas par cas : succès tardif, carte dans une autre devise,
 * frais retenus, montant inattendu, transaction en attente, panne pendant le rapprochement.
 */
class VerificationPaiementTest extends TestCase
{
    use RefreshDatabase;

    private PasserelleFactice $passerelle;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([LancerDeploiement::class]);
        Event::fake([FactureModifiee::class]);
        Notification::fake();
        $this->passerelle = new PasserelleFactice;
        $this->app->instance(PasserellePaiement::class, $this->passerelle);
    }

    public function test_un_succes_apres_l_expiration_de_la_session_est_credite(): void
    {
        [$facture, $paiement] = $this->sessionOuverte();
        $this->passerelle->session = new StatutSession('EXPIRED', 'f3b1c2d4-0000-4000-8000-000000000001', 'PENDING');
        $this->passerelle->transaction = $this->verification('PENDING');

        app(Encaissement::class)->verifier($paiement);
        $this->assertSame('en_attente', $paiement->refresh()->statut, 'Une transaction en attente n’est pas close par l’expiration de la session.');

        // Plus tard, la tentative a été close à tort ; le filet des 15 minutes la revérifie quand même.
        $paiement->forceFill(['statut' => 'annule'])->save();
        $this->passerelle->transaction = $this->verification('SUCCESS');
        $this->travel(20)->minutes();
        (new RapprocherPaiementsEnAttente)->handle(app(Encaissement::class));

        $this->assertSame('reussi', $paiement->refresh()->statut);
        $this->assertSame('payee', $facture->refresh()->statut);
    }

    public function test_une_carte_debitee_dans_une_autre_devise_est_creditee_et_verifiee_par_l_equipe(): void
    {
        [$facture, $paiement] = $this->sessionOuverte();
        $this->passerelle->session = new StatutSession('PAID', 'f3b1c2d4-0000-4000-8000-000000000002', 'SUCCESS');
        $this->passerelle->transaction = new VerificationPaiement('SUCCESS', 31, 1, 32, 31, 'TXN-CARTE', 'USD');

        app(Encaissement::class)->verifier($paiement);

        $this->assertSame('payee', $facture->refresh()->statut);
        $this->assertAlerte('en USD');
    }

    public function test_un_montant_inattendu_n_est_jamais_applique(): void
    {
        [$facture, $paiement] = $this->sessionOuverte();
        $this->passerelle->session = new StatutSession('PAID', 'f3b1c2d4-0000-4000-8000-000000000003', 'SUCCESS');
        $this->passerelle->transaction = new VerificationPaiement('SUCCESS', 100, 4, 104, 100, 'TXN-ETRANGE', 'XOF');

        app(Encaissement::class)->verifier($paiement);

        $this->assertSame('en_attente', $paiement->refresh()->statut, 'Ni payé, ni « refusé » : l’équipe vérifie.');
        $this->assertSame('a_payer', $facture->refresh()->statut);
        $this->assertSame(0, Ecriture::query()->count());
        $this->assertAlerte('montant à vérifier');
    }

    public function test_des_frais_retenus_par_saspay_sont_une_charge_de_souk_dev(): void
    {
        [$facture, $paiement] = $this->sessionOuverte();
        $this->passerelle->session = new StatutSession('PAID', 'f3b1c2d4-0000-4000-8000-000000000004', 'SUCCESS');
        $this->passerelle->transaction = new VerificationPaiement('SUCCESS', 18500, 0, 18500, 17853, 'TXN-DEDUIT', 'XOF');

        app(Encaissement::class)->verifier($paiement);

        $this->assertSame('payee', $facture->refresh()->statut);
        $this->assertSame(17853, (int) Ecriture::query()->where('compte', 'saspay')->sum('montant'));
        $this->assertSame(647, (int) Ecriture::query()->where('compte', 'charge_frais_encaissement')->sum('montant'));
        $this->assertSame(-15000, (int) Ecriture::query()->where('compte', 'dev')->sum('montant'), 'La part du dev reste entière.');
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
        $this->assertAlerte('frais retenus');
    }

    public function test_pas_de_seconde_session_pendant_qu_une_transaction_attend_sa_validation(): void
    {
        [$facture, $paiement] = $this->sessionOuverte();
        $paiement->forceFill(['transaction_id' => 'f3b1c2d4-0000-4000-8000-000000000005'])->save();
        $this->passerelle->session = new StatutSession('PENDING', 'f3b1c2d4-0000-4000-8000-000000000005', 'PENDING');
        $this->passerelle->transaction = $this->verification('PENDING');

        $this->actingAs($facture->client)->postJson("/api/factures/{$facture->id}/paiement", [], ['Idempotency-Key' => 'seconde-session'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Un paiement est en cours de validation : confirmez-le sur votre téléphone, ou attendez sa réponse avant de réessayer.');
        $this->assertSame(1, Paiement::query()->count());
    }

    public function test_une_panne_de_saspay_pendant_le_rapprochement_est_signalee(): void
    {
        $this->passerelle->panne = true;

        (new RapprochementQuotidien)->handle($this->passerelle, app(Encaissement::class));

        $this->assertSame(['verification_impossible'], collect(Rapprochement::query()->sole()->ecarts)->pluck('type')->all());
        Notification::assertSentTo(new AnonymousNotifiable, EcartRapprochement::class);
    }

    /**
     * @return array{0: Facture, 1: Paiement}
     */
    private function sessionOuverte(): array
    {
        $facture = Facture::factory()->create();
        $paiement = app(Encaissement::class)->ouvrirSession($facture, $facture->client);

        return [$facture, $paiement];
    }

    private function verification(string $statut): VerificationPaiement
    {
        return new VerificationPaiement($statut, 18500, 648, 19148, 18500, 'TXN-1', 'XOF');
    }

    private function assertAlerte(string $sujet): void
    {
        Notification::assertSentTo(new AnonymousNotifiable, AlerteAdmin::class, fn (AlerteAdmin $alerte) => str_contains(mb_strtolower($alerte->sujet), mb_strtolower($sujet)));
    }
}

/** Passerelle aux réponses choisies par le test. */
class PasserelleFactice implements PasserellePaiement
{
    public ?StatutSession $session = null;

    public ?VerificationPaiement $transaction = null;

    public bool $panne = false;

    public function nom(): string
    {
        return 'saspay';
    }

    public function creerSession(DemandeSession $demande): SessionPaiement
    {
        return new SessionPaiement('c9a17e2c-4b1a-4f0e-9c3d-'.str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT), 'slug', 'https://checkout.saspay.test/essai');
    }

    public function statutSession(string $sessionId): StatutSession
    {
        return $this->session ?? new StatutSession('PENDING', null, null);
    }

    public function verifierPaiement(string $transactionId): VerificationPaiement
    {
        return $this->transaction ?? new VerificationPaiement('PENDING', 18500, null, null, null, null, 'XOF');
    }

    public function transactionsReussies(\DateTimeInterface $debut, \DateTimeInterface $fin): iterable
    {
        if ($this->panne) {
            throw new PasserelleIndisponible('SasPay ne répond pas.');
        }

        return [];
    }
}
