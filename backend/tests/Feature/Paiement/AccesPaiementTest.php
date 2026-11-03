<?php

namespace Tests\Feature\Paiement;

use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\SimulationSasPay;
use App\Models\User;
use App\Paiement\GrandLivre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Qui voit et paie quoi : factures du client seulement, admin seulement, liens signés. */
class AccesPaiementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routesDeFacture(): array
    {
        return [
            'lire' => ['GET', '/api/factures/{id}'],
            'payer' => ['POST', '/api/factures/{id}/paiement'],
            'vérifier' => ['POST', '/api/factures/{id}/verification'],
        ];
    }

    #[DataProvider('routesDeFacture')]
    public function test_la_facture_d_un_autre_client_est_introuvable(string $methode, string $route): void
    {
        $facture = Facture::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->json($methode, str_replace('{id}', (string) $facture->id, $route))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Introuvable.']);
    }

    public function test_la_liste_ne_montre_que_mes_factures(): void
    {
        $mienne = Facture::factory()->create();
        Facture::factory()->create();

        $this->actingAs($mienne->client)->getJson('/api/factures')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mienne->id);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routesAdmin(): array
    {
        return [
            'finances' => ['GET', '/api/admin/finances'],
            'factures' => ['GET', '/api/admin/factures'],
            'avoir' => ['POST', '/api/admin/factures/1/avoir'],
            'reversements' => ['GET', '/api/admin/reversements'],
            'rapprochements' => ['GET', '/api/admin/rapprochements'],
            'remboursement' => ['POST', '/api/admin/paiements/1/remboursement'],
        ];
    }

    #[DataProvider('routesAdmin')]
    public function test_les_finances_sont_reservees_a_l_admin(string $methode, string $route): void
    {
        Facture::factory()->create();

        $this->actingAs(User::factory()->github()->create())->json($methode, $route)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->json($methode, $route)->assertUnauthorized();
    }

    public function test_l_admin_saisit_un_avoir_apres_un_remboursement(): void
    {
        $facture = Facture::factory()->create(['statut' => 'payee', 'payee_le' => now()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson("/api/admin/factures/{$facture->id}/avoir", [
            'montant_hebergement' => 3500, 'montant_appli' => 15000, 'motif' => 'Copie jamais démarrée.',
        ], ['Idempotency-Key' => 'avoir-facture-1'])->assertCreated()->assertJsonPath('montant_total', 18500);

        $this->assertSame('remboursee', $facture->refresh()->statut);
        $this->actingAs($admin)->postJson("/api/admin/factures/{$facture->id}/avoir", [
            'montant_hebergement' => 1, 'montant_appli' => 0, 'motif' => 'Encore ?',
        ], ['Idempotency-Key' => 'avoir-facture-2'])->assertUnprocessable();
    }

    public function test_une_operation_d_argent_sans_cle_d_idempotence_est_refusee(): void
    {
        $facture = Facture::factory()->create();

        $this->actingAs($facture->client)->postJson("/api/factures/{$facture->id}/paiement")
            ->assertStatus(428);
        $this->actingAs(User::factory()->github()->create())->postJson('/api/me/revenus/reversements')
            ->assertStatus(428);
    }

    public function test_l_admin_note_le_remboursement_d_un_paiement_en_double(): void
    {
        $facture = Facture::factory()->create(['statut' => 'payee', 'payee_le' => now()]);
        $double = Paiement::query()->create([
            'facture_id' => $facture->id, 'client_id' => $facture->client_id, 'passerelle' => 'simulee', 'montant' => 18500, 'statut' => 'reussi',
        ]);
        app(GrandLivre::class)->tropPercu($facture, $double);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/finances')
            ->assertOk()
            ->assertJsonPath('trop_percus.0.paiement_id', $double->id)
            ->assertJsonPath('trop_percus.0.montant', 18500);

        $this->actingAs($admin)->postJson("/api/admin/paiements/{$double->id}/remboursement", ['motif' => 'Remboursé par SasPay le 26 octobre.'], ['Idempotency-Key' => 'trop-percu-1'])
            ->assertCreated()
            ->assertJsonPath('rembourse', true);

        $this->assertSame(0, app(GrandLivre::class)->tropPercuDe($double->id));
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
        $this->actingAs($admin)->getJson('/api/admin/finances')->assertJsonCount(0, 'trop_percus');
        $this->actingAs($admin)->postJson("/api/admin/paiements/{$double->id}/remboursement", ['motif' => 'Encore.'], ['Idempotency-Key' => 'trop-percu-2'])
            ->assertStatus(409);
    }

    public function test_le_lien_de_paiement_de_l_e_mail_doit_etre_signe(): void
    {
        $facture = Facture::factory()->create();

        $this->get('/paiement/facture/'.$facture->id)->assertForbidden();

        $lien = URL::temporarySignedRoute('paiement.facture', now()->addDay(), ['id' => $facture->id]);
        $this->get($lien)->assertRedirectContains('/simulateur/saspay/');
    }

    public function test_le_simulateur_est_introuvable_avec_la_vraie_passerelle(): void
    {
        $simulation = SimulationSasPay::query()->create(['type' => 'session', 'montant' => 3500, 'statut' => 'PENDING']);
        config(['facturation.passerelle' => 'saspay']);

        $this->get('/simulateur/saspay/'.$simulation->id)->assertNotFound();
        $this->post('/simulateur/saspay/'.$simulation->id, ['issue' => 'succes'])->assertNotFound();
    }

    public function test_une_cle_d_idempotence_reutilisee_pour_une_autre_demande_repond_409(): void
    {
        $facture = Facture::factory()->create(['statut' => 'payee', 'payee_le' => now()]);
        $admin = User::factory()->admin()->create();
        $cle = ['Idempotency-Key' => 'avoir-meme-cle'];

        $this->actingAs($admin)->postJson("/api/admin/factures/{$facture->id}/avoir", ['montant_hebergement' => 500, 'montant_appli' => 0, 'motif' => 'Geste.'], $cle)->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/factures/{$facture->id}/avoir", ['montant_hebergement' => 900, 'montant_appli' => 0, 'motif' => 'Geste.'], $cle)->assertStatus(409);
    }

    public function test_la_page_de_copie_suspendue_ne_dit_rien_de_la_facture(): void
    {
        $this->get('/copie-suspendue')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '3600')
            ->assertSee('momentanément indisponible')
            ->assertDontSee('facture')
            ->assertDontSee('impayé');
    }
}
