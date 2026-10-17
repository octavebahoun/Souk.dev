<?php

namespace Tests\Feature;

use App\Events\DeploiementEtat;
use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\User;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\LanceurCompose;
use App\Moteur\MoteurDeploiement;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploiementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_le_post_repond_en_file_et_met_le_travail_en_attente(): void
    {
        Queue::fake();
        Event::fake([DeploiementEtat::class]);
        $utilisateur = User::factory()->create();
        $appli = $this->appli($utilisateur, 'moyenne');

        $reponse = $this->actingAs($utilisateur)->postJson('/api/apps/'.$appli->id.'/deployments', [
            'variables' => ['APP_NOM' => 'Pharmacie du Port'],
        ]);

        $reponse->assertStatus(202)
            ->assertJsonPath('etat', 'en_file')
            ->assertJsonPath('app_id', $appli->id)
            ->assertJsonPath('app_nom', 'Cobaye')
            ->assertJsonPath('taille', 'moyenne')
            ->assertJsonPath('url', null);

        Queue::assertPushed(LancerDeploiement::class);
        Event::assertDispatched(DeploiementEtat::class, function (DeploiementEtat $event) {
            return $event->payload['etat'] === 'en_file'
                && $event->broadcastAs() === 'deploiement.etat'
                && $event->broadcastOn()[0]->name === 'private-deploiement.'.$event->deploiementId;
        });
    }

    public function test_un_invite_recoit_401(): void
    {
        $appli = $this->appli(User::factory()->create());

        $this->postJson('/api/apps/'.$appli->id.'/deployments', [
            'variables' => [],
        ])->assertUnauthorized();
    }

    public function test_une_appli_inconnue_repond_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/999/deployments', ['variables' => []])
            ->assertNotFound();
    }

    public function test_la_lecture_est_reservee_a_celui_qui_a_lance(): void
    {
        $client = User::factory()->create();
        $autre = User::factory()->create();
        $deploiement = $this->deploiement($client);

        $this->actingAs($client)
            ->getJson('/api/deployments/'.$deploiement->id)
            ->assertOk()
            ->assertJsonPath('etat', 'en_file');

        $this->actingAs($autre)
            ->getJson('/api/deployments/'.$deploiement->id)
            ->assertForbidden();
    }

    public function test_le_canal_reverb_est_reserve_a_celui_qui_a_lance(): void
    {
        $client = User::factory()->create();
        $autre = User::factory()->create();
        $deploiement = $this->deploiement($client);
        $autorisation = Broadcast::getChannels()->get('deploiement.{id}');

        $this->assertIsCallable($autorisation);
        $this->assertTrue((bool) $autorisation($client, (string) $deploiement->id));
        $this->assertFalse((bool) $autorisation($autre, (string) $deploiement->id));
        $this->assertFalse((bool) $autorisation($client, '999999'));
    }

    public function test_le_proprietaire_peut_s_abonner_au_canal_reverb(): void
    {
        $this->configurerReverb();
        $client = User::factory()->create();
        $deploiement = $this->deploiement($client);

        $this->assertTrue(Broadcast::getChannels()->has('deploiement.{id}'));

        $this->actingAs($client)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-deploiement.'.$deploiement->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_un_autre_compte_ne_peut_pas_s_abonner_au_canal_reverb(): void
    {
        $this->configurerReverb();
        $client = User::factory()->create();
        $autre = User::factory()->create();
        $deploiement = $this->deploiement($client);

        $this->actingAs($autre)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-deploiement.'.$deploiement->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_un_changement_d_etat_est_pousse_vers_reverb(): void
    {
        $http = new MockHandler([
            new Response(200, [], '{}'),
        ]);
        $this->configurerReverb($http);
        $deploiement = $this->deploiement(User::factory()->create());

        $deploiement->changerEtat('en_ligne', url: 'http://d1.localhost');

        $requete = $http->getLastRequest();
        $this->assertNotNull($requete);
        $this->assertStringContainsString('/apps/soukdev/events', (string) $requete->getUri());

        $corps = json_decode((string) $requete->getBody(), true);
        $this->assertSame('deploiement.etat', $corps['name']);
        $this->assertSame('private-deploiement.'.$deploiement->id, $corps['channel']);

        $payload = json_decode($corps['data'], true);
        $this->assertSame('en_ligne', $payload['etat']);
        $this->assertSame('http://d1.localhost', $payload['url']);
        $this->assertSame($deploiement->id, $payload['id']);
    }

    public function test_la_liste_ne_montre_que_mes_copies_du_type_demande(): void
    {
        $client = User::factory()->create();
        $autre = User::factory()->create();
        $this->deploiement($client, 'client');
        $this->deploiement($client, 'test');
        $this->deploiement($autre, 'client');

        $reponse = $this->actingAs($client)->getJson('/api/deployments?type=client');

        $reponse->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('client', $reponse->json('data.0.type'));
    }

    public function test_arreter_passe_a_arrete_et_coupe_la_copie(): void
    {
        Event::fake([DeploiementEtat::class]);
        $client = User::factory()->create();
        $deploiement = $this->deploiement($client);
        $deploiement->forceFill([
            'etat' => 'en_ligne',
            'url' => 'http://d1.localhost',
            'copie' => 'd'.$deploiement->id,
            'repertoire' => '/tmp/copie',
        ])->save();

        $this->mock(LanceurCompose::class, function ($mock) use ($deploiement) {
            $mock->shouldReceive('arreter')
                ->once()
                ->with('/tmp/copie', 'd'.$deploiement->id);
        });

        $this->actingAs($client)
            ->deleteJson('/api/deployments/'.$deploiement->id)
            ->assertNoContent();

        $this->assertSame('arrete', $deploiement->refresh()->etat);
        $this->assertNull($deploiement->url);
        Event::assertDispatched(DeploiementEtat::class, function (DeploiementEtat $event) {
            return $event->payload['etat'] === 'arrete' && $event->payload['url'] === null;
        });
    }

    public function test_le_job_passe_par_construction_demarrage_puis_en_ligne(): void
    {
        Event::fake([DeploiementEtat::class]);
        $deploiement = $this->deploiement(User::factory()->create());
        $copie = new CopieLocale(
            'd'.$deploiement->id,
            '/tmp/copie',
            ['service_web' => ['nom' => 'web', 'port' => 80]],
            [],
            true,
        );

        $this->mock(MoteurDeploiement::class, function ($mock) use ($copie, $deploiement) {
            $mock->shouldReceive('depuisGithub')
                ->once()
                ->with($deploiement->appli->depot_url, [], 'd'.$deploiement->id, true)
                ->andReturn($copie);
            $mock->shouldReceive('mettreEnLigne')->once()->with($copie, 'petite')->andReturn($copie);
        });

        app()->call([new LancerDeploiement($deploiement->id), 'handle']);

        $deploiement->refresh();
        $this->assertSame('en_ligne', $deploiement->etat);
        $this->assertSame('http://d'.$deploiement->id.'.localhost', $deploiement->url);

        $this->assertSame(['construction', 'demarrage', 'en_ligne'], $this->etatsDiffuses());
    }

    public function test_le_job_passe_a_echec_si_le_depot_est_refuse(): void
    {
        Event::fake([DeploiementEtat::class]);
        $deploiement = $this->deploiement(User::factory()->create());

        $this->mock(MoteurDeploiement::class, function ($mock) {
            $mock->shouldReceive('depuisGithub')->once()->andThrow(new DepotInvalide('Dépôt privé.'));
            $mock->shouldNotReceive('mettreEnLigne');
        });

        app()->call([new LancerDeploiement($deploiement->id), 'handle']);

        $deploiement->refresh();
        $this->assertSame('echec', $deploiement->etat);
        $this->assertSame('Dépôt privé.', $deploiement->erreur);

        $this->assertSame(['construction', 'echec'], $this->etatsDiffuses());
    }

    public function test_le_job_coupe_la_copie_si_on_arrete_pendant_le_demarrage(): void
    {
        Event::fake([DeploiementEtat::class]);
        $deploiement = $this->deploiement(User::factory()->create());
        $copie = new CopieLocale('d'.$deploiement->id, '/tmp/copie', ['service_web' => ['nom' => 'web', 'port' => 80]], [], true);

        $this->mock(MoteurDeploiement::class, function ($mock) use ($copie, $deploiement) {
            $mock->shouldReceive('depuisGithub')->once()->andReturn($copie);
            $mock->shouldReceive('mettreEnLigne')->once()->andReturnUsing(function () use ($deploiement, $copie) {
                $deploiement->forceFill(['etat' => 'arrete', 'url' => null])->save();

                return $copie;
            });
            $mock->shouldReceive('arreter')->once()->with($copie);
        });

        app()->call([new LancerDeploiement($deploiement->id), 'handle']);

        $this->assertSame('arrete', $deploiement->refresh()->etat);
        $this->assertNotContains('en_ligne', $this->etatsDiffuses());
    }

    public function test_le_quatrieme_lancement_dans_l_heure_repond_429(): void
    {
        Queue::fake();
        Event::fake([DeploiementEtat::class]);
        $utilisateur = User::factory()->create();
        $appli = $this->appli($utilisateur);

        for ($fois = 0; $fois < 3; $fois++) {
            $this->actingAs($utilisateur)
                ->postJson('/api/apps/'.$appli->id.'/deployments', ['variables' => []])
                ->assertStatus(202);
        }

        $this->actingAs($utilisateur)
            ->postJson('/api/apps/'.$appli->id.'/deployments', ['variables' => []])
            ->assertStatus(429);
    }

    public function test_une_liste_de_variables_est_refusee(): void
    {
        Queue::fake();
        $utilisateur = User::factory()->create();
        $appli = $this->appli($utilisateur);

        $this->actingAs($utilisateur)
            ->postJson('/api/apps/'.$appli->id.'/deployments', [
                'variables' => ['Pharmacie du Port'],
            ])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_la_copie_garde_la_taille_du_lancement(): void
    {
        Queue::fake();
        Event::fake([DeploiementEtat::class]);
        $utilisateur = User::factory()->create();
        $appli = $this->appli($utilisateur, 'moyenne');

        $reponse = $this->actingAs($utilisateur)->postJson('/api/apps/'.$appli->id.'/deployments', [
            'variables' => [],
        ]);

        $reponse->assertStatus(202)->assertJsonPath('taille', 'moyenne');
        $appli->update(['taille' => 'grande']);

        $this->actingAs($utilisateur)
            ->getJson('/api/deployments/'.$reponse->json('id'))
            ->assertOk()
            ->assertJsonPath('taille', 'moyenne');
    }

    public function test_une_taille_inconnue_est_refusee(): void
    {
        Queue::fake();
        $utilisateur = User::factory()->create();
        $appli = $this->appli($utilisateur, 'enorme');

        $this->actingAs($utilisateur)
            ->postJson('/api/apps/'.$appli->id.'/deployments', ['variables' => []])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_arreter_une_copie_encore_en_file_ne_lance_pas_docker(): void
    {
        Event::fake([DeploiementEtat::class]);
        $client = User::factory()->create();
        $deploiement = $this->deploiement($client);

        $this->mock(LanceurCompose::class, function ($mock) {
            $mock->shouldNotReceive('arreter');
        });

        $this->actingAs($client)
            ->deleteJson('/api/deployments/'.$deploiement->id)
            ->assertNoContent();

        $this->assertSame('arrete', $deploiement->refresh()->etat);
    }

    public function test_une_copie_de_test_sans_appli_clone_le_depot_joint(): void
    {
        Event::fake([DeploiementEtat::class]);
        $deploiement = Deploiement::query()->create([
            'user_id' => User::factory()->create()->id,
            'appli_id' => null,
            'depot_url' => 'https://github.com/wasfade/momo',
            'type' => 'test',
            'taille' => 'petite',
            'etat' => 'en_file',
            'variables' => [],
        ]);
        $copie = new CopieLocale('d'.$deploiement->id, '/tmp/copie', ['service_web' => ['nom' => 'web', 'port' => 80]], [], true);

        $this->mock(MoteurDeploiement::class, function ($mock) use ($copie, $deploiement) {
            $mock->shouldReceive('depuisGithub')
                ->once()
                ->with('https://github.com/wasfade/momo', [], 'd'.$deploiement->id, true)
                ->andReturn($copie);
            $mock->shouldReceive('mettreEnLigne')->once()->with($copie, 'petite')->andReturn($copie);
        });

        app()->call([new LancerDeploiement($deploiement->id), 'handle']);

        $this->assertSame('en_ligne', $deploiement->refresh()->etat);
        $this->assertSame(['construction', 'demarrage', 'en_ligne'], $this->etatsDiffuses());

        $api = $deploiement->versApi();
        $this->assertNull($api['app_id']);
        $this->assertSame('wasfade/momo', $api['app_nom']);
        $this->assertSame('test', $api['type']);
    }

    public function test_une_copie_d_appli_garde_le_depot_de_l_appli(): void
    {
        $deploiement = $this->deploiement(User::factory()->create());
        $deploiement->update(['depot_url' => 'https://github.com/autre/depot']);

        $this->assertSame('https://github.com/MourchidFOLARIN/cobaye', $deploiement->depotACloner());
        $this->assertSame('Cobaye', $deploiement->versApi()['app_nom']);
    }

    private function configurerReverb(?MockHandler $http = null): void
    {
        $client = [];

        if ($http instanceof MockHandler) {
            $client['handler'] = HandlerStack::create($http);
        }

        $canaux = Broadcast::getChannels();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'valeur-de-test',
                'secret' => 'valeur-de-test',
                'app_id' => 'soukdev',
                'options' => [
                    'host' => 'localhost',
                    'port' => 8080,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
                'client_options' => $client,
            ],
        ]);

        // phpunit.xml met BROADCAST_CONNECTION=null ; la CI copie .env.example
        // (reverb). Dans les deux cas les canaux sont sur le driver du boot.
        // forgetDrivers() jette cette instance : on recopie les callbacks.
        Broadcast::forgetDrivers();
        Broadcast::setDefaultDriver('reverb');

        if ($canaux->isEmpty()) {
            require base_path('routes/channels.php');

            return;
        }

        foreach ($canaux as $nom => $callback) {
            Broadcast::channel($nom, $callback);
        }
    }

    /**
     * @return list<string>
     */
    private function etatsDiffuses(): array
    {
        return Event::dispatched(DeploiementEtat::class)
            ->map(fn (array $envoi) => $envoi[0]->payload['etat'])
            ->values()
            ->all();
    }

    private function appli(User $auteur, string $taille = 'petite'): Appli
    {
        return Appli::query()->create([
            'user_id' => $auteur->id,
            'nom' => 'Cobaye',
            'depot_url' => 'https://github.com/MourchidFOLARIN/cobaye',
            'taille' => $taille,
        ]);
    }

    private function deploiement(User $client, string $type = 'client', string $taille = 'petite'): Deploiement
    {
        return Deploiement::query()->create([
            'user_id' => $client->id,
            'appli_id' => $this->appli(User::factory()->create(), $taille)->id,
            'type' => $type,
            'taille' => $taille,
            'etat' => 'en_file',
            'variables' => [],
        ]);
    }
}
