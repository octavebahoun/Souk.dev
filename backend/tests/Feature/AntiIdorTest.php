<?php

namespace Tests\Feature;

use App\Models\Deploiement;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Chaque ressource privée refuse un autre compte (403 ou 404), sans rien modifier.
 * Une ligne par route ; les phases suivantes y ajoutent factures, revenus et notifications.
 */
class AntiIdorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function routesDuDeploiement(): array
    {
        return [
            'lire' => ['GET', '/api/deployments/{id}', []],
            'arrêter' => ['DELETE', '/api/deployments/{id}', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $corps
     */
    #[DataProvider('routesDuDeploiement')]
    public function test_le_deploiement_d_un_autre_compte_est_refuse(string $methode, string $uri, array $corps): void
    {
        Queue::fake();
        $deploiement = Deploiement::factory()->create(['etat' => 'en_ligne']);

        $this->actingAs(User::factory()->create())
            ->json($methode, str_replace('{id}', (string) $deploiement->id, $uri), $corps)
            ->assertForbidden();

        $this->assertSame('en_ligne', $deploiement->refresh()->etat);
        Queue::assertNothingPushed();
    }

    public function test_la_liste_des_deploiements_ne_montre_que_les_siens(): void
    {
        $client = User::factory()->client()->create();
        $sien = Deploiement::factory()->create(['user_id' => $client->id]);
        Deploiement::factory()->create();

        $this->actingAs($client)
            ->getJson('/api/deployments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sien->id);
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function routesDeLaMission(): array
    {
        return [
            'lire' => ['GET', '/api/missions/{id}', []],
            'lire le fil' => ['GET', '/api/missions/{id}/messages', []],
            'écrire dans le fil' => ['POST', '/api/missions/{id}/messages', ['texte' => 'Je passe par là.']],
            'noter' => ['POST', '/api/missions/{id}/note', ['note' => 1]],
        ];
    }

    /**
     * @param  array<string, mixed>  $corps
     */
    #[DataProvider('routesDeLaMission')]
    public function test_la_mission_d_un_autre_compte_est_refusee(string $methode, string $uri, array $corps): void
    {
        $mission = Mission::factory()->create();

        $this->actingAs(User::factory()->create())
            ->json($methode, str_replace('{id}', (string) $mission->id, $uri), $corps)
            ->assertForbidden();

        $mission->refresh();
        $this->assertSame('en_cours', $mission->statut);
        $this->assertNull($mission->note);
        $this->assertSame(0, $mission->messages()->count());
    }

    public function test_la_liste_des_missions_ne_montre_que_les_siennes(): void
    {
        $client = User::factory()->client()->create();
        $sienne = Mission::factory()->create(['client_id' => $client->id]);
        Mission::factory()->create();

        $this->actingAs($client)
            ->getJson('/api/missions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sienne->id);
    }

    public function test_les_canaux_prives_ne_s_ouvrent_qu_aux_comptes_concernes(): void
    {
        $deploiement = Deploiement::factory()->create();
        $mission = Mission::factory()->create();
        $autre = User::factory()->create();

        $canalDeploiement = Broadcast::getChannels()->get('deploiement.{id}');
        $canalMission = Broadcast::getChannels()->get('mission.{id}');

        $this->assertTrue((bool) $canalDeploiement($deploiement->utilisateur, (string) $deploiement->id));
        $this->assertFalse((bool) $canalDeploiement($autre, (string) $deploiement->id));

        $this->assertTrue((bool) $canalMission($mission->client, (string) $mission->id));
        $this->assertTrue((bool) $canalMission($mission->auteur, (string) $mission->id));
        $this->assertFalse((bool) $canalMission($autre, (string) $mission->id));
    }

    public function test_publier_exige_un_github_lie(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->postJson('/api/apps/verification', ['depot_url' => 'https://github.com/acme/pharmacie'])
            ->assertForbidden()
            ->assertJsonPath('message', 'GitHub n\'est pas lié à ce compte.');
    }
}
