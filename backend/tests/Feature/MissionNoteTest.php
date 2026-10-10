<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionNoteTest extends TestCase
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

    public function test_le_client_termine_et_note(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 5, 'commentaire' => 'Travail propre'])
            ->assertOk()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('note.note', 5)
            ->assertJsonPath('note.commentaire', 'Travail propre');

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'statut' => 'terminee',
            'note' => 5,
            'note_commentaire' => 'Travail propre',
        ]);
    }

    public function test_l_auteur_et_un_autre_compte_ne_notent_pas(): void
    {
        [$client, $auteur, $intrus] = User::factory()->count(3)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($auteur)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 5])
            ->assertForbidden();

        $this->actingAs($intrus)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 5])
            ->assertForbidden();

        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'statut' => 'en_cours']);
    }

    public function test_une_deuxieme_note_est_refusee(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 4])
            ->assertOk();

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 1])
            ->assertConflict();

        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'note' => 4]);
    }

    public function test_une_note_hors_de_1_a_5_est_refusee(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        foreach ([0, 6] as $note) {
            $this->actingAs($client)
                ->postJson("/api/missions/{$mission->id}/note", ['note' => $note])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('note');
        }

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    public function test_un_commentaire_trop_long_est_refuse(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 3, 'commentaire' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('commentaire');
    }

    public function test_sans_connexion_401(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = $this->mission($client, $auteur);

        $this->postJson("/api/missions/{$mission->id}/note", ['note' => 5])->assertUnauthorized();
    }
}
