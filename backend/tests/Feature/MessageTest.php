<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_repond_puis_on_lit_les_messages_du_plus_ancien_au_plus_recent(): void
    {
        $auteur = User::factory()->create(['username' => 'wasfade', 'name' => 'Wasfade']);
        $discussion = Discussion::factory()->for($auteur, 'auteur')->create();

        $premier = $this->actingAs($auteur)->postJson('/api/discussions/'.$discussion->id.'/messages', [
            'texte' => 'Premier',
        ])->assertCreated()->assertJsonPath('texte', 'Premier')->assertJsonPath('auteur.username', 'wasfade');

        $this->travel(1)->second();

        $this->actingAs($auteur)->postJson('/api/discussions/'.$discussion->id.'/messages', [
            'texte' => 'Second',
        ])->assertCreated();

        $this->getJson('/api/discussions/'.$discussion->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.texte', 'Premier')
            ->assertJsonPath('data.1.texte', 'Second')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/discussions/'.$discussion->id)
            ->assertOk()
            ->assertJsonPath('nb_messages', 2);

        $this->actingAs($auteur)
            ->patchJson('/api/messages/'.$premier->json('id'), ['texte' => 'Premier, corrigé'])
            ->assertOk()
            ->assertJsonPath('texte', 'Premier, corrigé');
    }

    public function test_un_autre_compte_ne_modifie_pas_le_message_mais_un_admin_le_supprime(): void
    {
        $auteur = User::factory()->create();
        $autre = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $message = Message::factory()->for($auteur, 'auteur')->create(['texte' => 'Le mien']);

        $this->actingAs($autre)
            ->patchJson('/api/messages/'.$message->id, ['texte' => 'Non'])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->deleteJson('/api/messages/'.$message->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_une_discussion_inconnue_repond_404(): void
    {
        $auteur = User::factory()->create();

        $this->actingAs($auteur)
            ->postJson('/api/discussions/999/messages', ['texte' => 'Où ?'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Introuvable.');
    }
}
