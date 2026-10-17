<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\CorrectifAccepte;
use App\Events\CorrectifPropose;
use App\Events\DiscussionModifiee;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CorrectifTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_dev_propose_un_correctif_et_l_auteur_l_accepte(): void
    {
        Event::fake([CorrectifPropose::class, CorrectifAccepte::class, DiscussionModifiee::class]);

        $auteur = User::factory()->create();
        $dev = User::factory()->create(['github_lie' => true, 'username' => 'wasfade']);
        $autre = User::factory()->create(['github_lie' => true, 'username' => 'autre']);
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id]);

        $retenu = $this->actingAs($dev)->postJson('/api/discussions/'.$discussion->id.'/correctifs', [
            'depot_url' => 'https://github.com/wasfade/momo/tree/correctif',
            'description' => 'Le callback renvoie 200.',
        ]);

        $retenu->assertCreated()
            ->assertJsonPath('accepte', false)
            ->assertJsonPath('deploiement', null)
            ->assertJsonPath('auteur.username', 'wasfade')
            ->assertJsonPath('discussion_id', $discussion->id);

        $visible = $this->actingAs($autre)->postJson('/api/discussions/'.$discussion->id.'/correctifs', [
            'depot_url' => 'https://github.com/autre/momo',
        ])->assertCreated();

        $this->actingAs($auteur)->postJson('/api/correctifs/'.$retenu->json('id').'/accepter')
            ->assertOk()
            ->assertJsonPath('accepte', true);

        $this->getJson('/api/discussions/'.$discussion->id.'/correctifs')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.accepte', true)
            ->assertJsonPath('data.1.id', $visible->json('id'))
            ->assertJsonPath('data.1.accepte', false);

        $this->getJson('/api/discussions/'.$discussion->id)
            ->assertOk()
            ->assertJsonPath('resolue', true);

        $this->actingAs($auteur)->postJson('/api/correctifs/'.$visible->json('id').'/accepter')
            ->assertStatus(409);

        Event::assertDispatched(CorrectifPropose::class);
        Event::assertDispatched(CorrectifAccepte::class);
        Event::assertDispatched(DiscussionModifiee::class);
    }

    public function test_proposer_exige_github_et_l_auteur_seul_accepte(): void
    {
        $auteur = User::factory()->create();
        $sansGithub = User::factory()->create(['github_lie' => false]);
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id]);

        $this->postJson('/api/discussions/'.$discussion->id.'/correctifs', [
            'depot_url' => 'https://github.com/wasfade/momo',
        ])->assertUnauthorized();

        $this->actingAs($sansGithub)->postJson('/api/discussions/'.$discussion->id.'/correctifs', [
            'depot_url' => 'https://github.com/wasfade/momo',
        ])->assertForbidden()->assertJsonPath('message', 'GitHub n\'est pas lié à ce compte.');

        $dev = User::factory()->create(['github_lie' => true]);
        $correctif = $this->actingAs($dev)->postJson('/api/discussions/'.$discussion->id.'/correctifs', [
            'depot_url' => 'https://github.com/wasfade/momo',
        ])->assertCreated();

        $this->actingAs($dev)->postJson('/api/correctifs/'.$correctif->json('id').'/accepter')
            ->assertForbidden();
    }
}
