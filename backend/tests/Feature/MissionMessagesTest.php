<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function mission(User $client, User $auteur): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'auteur_id' => $auteur->id,
            'message' => 'Ajouter la prise de rendez-vous',
            'budget' => 150000,
        ]);
    }

    public function test_le_client_ecrit_et_l_auteur_lit(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/messages", ['texte' => 'Bonjour, voici les maquettes.'])
            ->assertCreated()
            ->assertJsonPath('texte', 'Bonjour, voici les maquettes.')
            ->assertJsonPath('auteur.id', $client->id)
            ->assertJsonStructure(['id', 'texte', 'auteur', 'cree_le']);

        $this->actingAs($auteur)->getJson("/api/missions/{$mission->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.texte', 'Bonjour, voici les maquettes.');
    }

    public function test_un_autre_compte_ne_lit_ni_n_ecrit(): void
    {
        [$client, $auteur, $intrus] = User::factory()->count(3)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($intrus)->getJson("/api/missions/{$mission->id}/messages")->assertForbidden();

        $this->actingAs($intrus)
            ->postJson("/api/missions/{$mission->id}/messages", ['texte' => 'Je passe par là'])
            ->assertForbidden();

        $this->assertDatabaseCount('mission_messages', 0);
    }

    public function test_un_texte_vide_est_refuse(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/messages", ['texte' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('texte');
    }

    public function test_les_messages_arrivent_dans_l_ordre_d_envoi(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        foreach (['un', 'deux', 'trois'] as $texte) {
            $this->actingAs($client)
                ->postJson("/api/missions/{$mission->id}/messages", ['texte' => $texte])
                ->assertCreated();
        }

        $this->actingAs($auteur)->getJson("/api/missions/{$mission->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.texte', 'un')
            ->assertJsonPath('data.1.texte', 'deux')
            ->assertJsonPath('data.2.texte', 'trois');
    }

    public function test_sans_connexion_401(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->getJson("/api/missions/{$mission->id}/messages")->assertUnauthorized();
        $this->postJson("/api/missions/{$mission->id}/messages", ['texte' => 'coucou'])->assertUnauthorized();
    }
}
