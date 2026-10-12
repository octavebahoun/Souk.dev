<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionCreationTest extends TestCase
{
    use RefreshDatabase;

    private function dev(string $username = 'afi-dossou'): User
    {
        return User::factory()->create(['username' => $username]);
    }

    private function appli(User $auteur): Appli
    {
        return Appli::query()->create([
            'user_id' => $auteur->id,
            'nom' => 'Boutique MoMo',
            'depot_url' => 'https://github.com/afi-dossou/boutique-momo',
            'taille' => 'moyenne',
        ]);
    }

    public function test_un_client_recrute_un_dev_directement(): void
    {
        $dev = $this->dev();
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->postJson('/api/devs/afi-dossou/missions', [
                'message' => 'Une boutique en ligne avec paiement MoMo',
                'budget' => 150000,
                'delai' => '2026-11-30',
            ])
            ->assertCreated()
            ->assertJsonPath('app_id', null)
            ->assertJsonPath('statut', 'en_cours')
            ->assertJsonPath('note', null)
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('auteur.username', 'afi-dossou')
            ->assertJsonPath('budget', 150000)
            ->assertJsonPath('delai', '2026-11-30');

        $this->assertDatabaseHas('missions', [
            'client_id' => $client->id,
            'auteur_id' => $dev->id,
            'app_id' => null,
        ]);
    }

    public function test_un_client_demande_une_version_sur_mesure_d_une_appli(): void
    {
        $dev = $this->dev();
        $appli = $this->appli($dev);
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->postJson("/api/apps/{$appli->id}/missions", ['message' => 'Ajouter la livraison'])
            ->assertCreated()
            ->assertJsonPath('app_id', $appli->id)
            ->assertJsonPath('auteur.id', $dev->id)
            ->assertJsonPath('budget', null)
            ->assertJsonPath('delai', null);
    }

    public function test_le_client_et_l_auteur_viennent_de_la_session_et_de_l_url(): void
    {
        $dev = $this->dev();
        $client = User::factory()->client()->create();
        $autre = User::factory()->create();

        $this->actingAs($client)
            ->postJson('/api/devs/afi-dossou/missions', [
                'message' => 'Bonjour',
                'client_id' => $autre->id,
                'auteur_id' => $autre->id,
                'app_id' => 99,
                'statut' => 'terminee',
            ])
            ->assertCreated()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('auteur.id', $dev->id)
            ->assertJsonPath('app_id', null)
            ->assertJsonPath('statut', 'en_cours');
    }

    public function test_un_dev_ou_une_appli_introuvable_repond_404(): void
    {
        User::factory()->client()->create(['username' => 'un-client']);
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->postJson('/api/devs/personne/missions', ['message' => 'Bonjour'])
            ->assertNotFound();

        // Un compte client n'est pas un dev qu'on recrute.
        $this->actingAs($client)
            ->postJson('/api/devs/un-client/missions', ['message' => 'Bonjour'])
            ->assertNotFound();

        $this->actingAs($client)
            ->postJson('/api/apps/999/missions', ['message' => 'Bonjour'])
            ->assertNotFound();

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_on_ne_s_envoie_pas_de_mission(): void
    {
        $dev = $this->dev();
        $appli = $this->appli($dev);

        $this->actingAs($dev)
            ->postJson('/api/devs/afi-dossou/missions', ['message' => 'Bonjour'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('auteur');

        $this->actingAs($dev)
            ->postJson("/api/apps/{$appli->id}/missions", ['message' => 'Bonjour'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('auteur');

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_les_champs_invalides_sont_refuses(): void
    {
        $this->dev();
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->postJson('/api/devs/afi-dossou/missions', [
                'message' => '',
                'budget' => -1,
                'delai' => '30/11/2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'budget', 'delai']);

        $this->actingAs($client)
            ->postJson('/api/devs/afi-dossou/missions', ['message' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_dix_missions_par_heure_au_maximum(): void
    {
        $this->dev();
        $client = User::factory()->client()->create();

        foreach (range(1, 10) as $essai) {
            $this->actingAs($client)
                ->postJson('/api/devs/afi-dossou/missions', ['message' => "Demande {$essai}"])
                ->assertCreated();
        }

        $this->actingAs($client)
            ->postJson('/api/devs/afi-dossou/missions', ['message' => 'Une de trop'])
            ->assertTooManyRequests();
    }

    public function test_une_appli_supprimee_garde_ses_missions(): void
    {
        $dev = $this->dev();
        $appli = $this->appli($dev);
        $client = User::factory()->client()->create();

        $id = $this->actingAs($client)
            ->postJson("/api/apps/{$appli->id}/missions", ['message' => 'Ajouter la livraison'])
            ->assertCreated()
            ->json('id');

        $appli->delete();

        $this->assertDatabaseHas('missions', ['id' => $id, 'app_id' => null]);
    }

    public function test_sans_connexion_401(): void
    {
        $dev = $this->dev();
        $appli = $this->appli($dev);

        $this->postJson('/api/devs/afi-dossou/missions', ['message' => 'Bonjour'])->assertUnauthorized();
        $this->postJson("/api/apps/{$appli->id}/missions", ['message' => 'Bonjour'])->assertUnauthorized();
    }
}
