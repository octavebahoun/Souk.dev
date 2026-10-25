<?php

namespace Tests\Feature\Paiement;

use App\Events\DeploiementEtat;
use App\Events\FactureModifiee;
use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\PaiementRecu;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Parcours d'un client, de la commande à la copie lancée, avec le simulateur de SasPay :
 * la copie ne démarre que lorsque la vérification par l'API renvoie SUCCESS.
 */
class ParcoursPaiementTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<RequeteHttp> */
    private array $webhooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([LancerDeploiement::class]);
        Event::fake([DeploiementEtat::class, FactureModifiee::class]);
        Notification::fake();
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Le simulateur envoie ses webhooks en HTTP : on les garde pour les rejouer sur l'API.
        Http::preventStrayRequests();
        Http::fake(['localhost:8000/api/webhooks/saspay' => function (RequeteHttp $requete) {
            $this->webhooks[] = $requete;

            return Http::response(['message' => 'Reçu.']);
        }]);
    }

    public function test_un_client_paie_et_sa_copie_demarre(): void
    {
        [$client, $appli] = $this->commande();
        $facture = Facture::query()->sole();

        $liste = $this->actingAs($client)->getJson('/api/factures');
        $this->assertConformeAuContrat($liste, 'GET', '/api/factures');
        $liste->assertOk()
            ->assertJsonPath('data.0.statut', 'a_payer')
            ->assertJsonPath('data.0.montant_total', 18500)
            ->assertJsonPath('data.0.lignes.1.montant', 15000);

        $ouverture = $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement", [], ['Idempotency-Key' => 'commande-'.$facture->id]);
        $this->assertConformeAuContrat($ouverture, 'POST', "/api/factures/{$facture->id}/paiement");
        $session = $ouverture->assertCreated()->json();
        $this->assertStringContainsString('/simulateur/saspay/', $session['checkout_url']);

        // Double clic : la même session revient, rien n'est recréé.
        $this->actingAs($client)
            ->postJson("/api/factures/{$facture->id}/paiement", [], ['Idempotency-Key' => 'commande-'.$facture->id])
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, Paiement::query()->count());

        $this->payerSurLeSimulateur($session['checkout_url'], 'succes')->assertRedirectContains('/paiement/retour?facture='.$facture->id);
        $this->rejouerLesWebhooks()->assertOk();

        $facture->refresh();
        $this->assertSame('payee', $facture->statut);
        $this->assertSame('reussi', Paiement::query()->sole()->statut);
        // Frais en ADD_ON : le client paie 3,5 % en plus, Souk.dev reçoit le montant plein.
        $this->assertSame(648, Paiement::query()->sole()->frais_client);

        $deploiement = Deploiement::query()->sole();
        $this->assertSame('en_file', $deploiement->etat);
        Queue::assertPushed(LancerDeploiement::class, fn (LancerDeploiement $job) => $job->deploiementId === $deploiement->id);
        Notification::assertSentTo($client, PaiementRecu::class);

        // Grand livre : 18 500 F chez SasPay, 3 500 F d'hébergement à Souk.dev, 15 000 F dus au dev.
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
        $this->assertSame(18500, (int) Ecriture::query()->where('compte', 'saspay')->sum('montant'));
        $this->assertSame(-3500, (int) Ecriture::query()->where('compte', 'revenu_hebergement')->sum('montant'));
        $this->assertSame(-15000, (int) Ecriture::query()->where('compte', 'dev')->where('dev_id', $appli->user_id)->sum('montant'));

        $lue = $this->actingAs($client)->getJson("/api/factures/{$facture->id}");
        $this->assertConformeAuContrat($lue, 'GET', "/api/factures/{$facture->id}");
        $lue->assertOk()
            ->assertJsonPath('statut', 'payee')
            ->assertJsonPath('paiements.0.statut', 'reussi');
    }

    public function test_un_paiement_refuse_ne_lance_rien_et_le_client_peut_reessayer(): void
    {
        [$client] = $this->commande();
        $facture = Facture::query()->sole();
        $session = $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement")->assertCreated()->json();

        $this->payerSurLeSimulateur($session['checkout_url'], 'echec')->assertOk()->assertSee('Paiement refusé');
        $this->rejouerLesWebhooks()->assertOk();

        $this->assertSame('echoue', Paiement::query()->sole()->statut);
        $this->assertSame('a_payer', $facture->refresh()->statut);
        $this->assertSame('attente_paiement', Deploiement::query()->sole()->etat);
        Queue::assertNotPushed(LancerDeploiement::class);
        $this->assertSame(0, Ecriture::query()->count());

        // Même session, nouvel essai réussi.
        $this->payerSurLeSimulateur($session['checkout_url'], 'succes');
        $this->rejouerLesWebhooks();

        $this->assertSame('payee', $facture->refresh()->statut);
        Queue::assertPushed(LancerDeploiement::class, 1);
    }

    public function test_sans_webhook_le_retour_du_navigateur_reverifie_par_l_api(): void
    {
        [$client] = $this->commande();
        $facture = Facture::query()->sole();
        $session = $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement")->assertCreated()->json();
        $this->payerSurLeSimulateur($session['checkout_url'], 'succes');
        $this->webhooks = [];

        $this->actingAs($client)->postJson("/api/factures/{$facture->id}/verification")
            ->assertOk()
            ->assertJsonPath('statut', 'payee');

        Queue::assertPushed(LancerDeploiement::class, 1);
    }

    public function test_un_paiement_en_attente_ne_change_rien(): void
    {
        [$client] = $this->commande();
        $facture = Facture::query()->sole();
        $session = $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement")->assertCreated()->json();

        $this->payerSurLeSimulateur($session['checkout_url'], 'attente')->assertOk();
        $this->actingAs($client)->postJson("/api/factures/{$facture->id}/verification")->assertOk()->assertJsonPath('statut', 'a_payer');

        $this->assertSame('en_attente', Paiement::query()->sole()->statut);
        Queue::assertNotPushed(LancerDeploiement::class);
    }

    public function test_une_facture_payee_ne_se_paie_pas_deux_fois(): void
    {
        [$client] = $this->commande();
        $facture = Facture::query()->sole();
        $session = $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement")->json();
        $this->payerSurLeSimulateur($session['checkout_url'], 'succes');
        $this->rejouerLesWebhooks();

        $this->actingAs($client)->postJson("/api/factures/{$facture->id}/paiement")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette facture est déjà payée.');
    }

    /**
     * @return array{0: User, 1: Appli}
     */
    private function commande(): array
    {
        $client = User::factory()->client()->create();
        $appli = Appli::factory()->create(['prix' => 15000, 'type_prix' => 'mensuel', 'taille' => 'petite']);

        $commande = $this->actingAs($client)->postJson("/api/apps/{$appli->id}/deployments", ['variables' => []]);
        $this->assertConformeAuContrat($commande, 'POST', "/api/apps/{$appli->id}/deployments");
        $commande->assertAccepted()
            ->assertJsonPath('etat', 'attente_paiement')
            ->assertJsonPath('facture_a_payer.montant_total', 18500);

        return [$client, $appli];
    }

    private function payerSurLeSimulateur(string $url, string $issue): TestResponse
    {
        $chemin = (string) parse_url($url, PHP_URL_PATH);
        $this->get($chemin)->assertOk()->assertSee('Simulateur SasPay');

        return $this->post($chemin, ['issue' => $issue, 'reseau' => 'mtn_bj']);
    }

    /** Rejoue sur l'API, octet pour octet, les webhooks signés que le simulateur a envoyés. */
    private function rejouerLesWebhooks(): TestResponse
    {
        $this->assertNotEmpty($this->webhooks, 'Le simulateur n\'a envoyé aucun webhook.');
        $this->app['auth']->forgetGuards();
        $reponse = null;

        foreach ($this->webhooks as $requete) {
            $reponse = $this->call('POST', '/api/webhooks/saspay', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $requete->header('X-Webhook-Signature')[0],
                'HTTP_X_WEBHOOK_TIMESTAMP' => $requete->header('X-Webhook-Timestamp')[0],
                'HTTP_X_WEBHOOK_EVENT' => $requete->header('X-Webhook-Event')[0],
            ], $requete->body());
        }

        $this->webhooks = [];

        return $reponse;
    }
}
