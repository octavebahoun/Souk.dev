<?php

namespace Tests\Feature;

use App\Events\ConversationModifiee;
use App\Events\MessagePriveCree;
use App\Models\Canal;
use App\Models\Conversation;
use App\Models\Discussion;
use App\Models\User;
use App\Notifications\InvitationAcceptee;
use App\Notifications\InvitationRecue;
use App\Notifications\NouveauMessagePrive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Échange privé entre deux devs : personne n'écrit sans invitation acceptée, une conversation
 * n'existe que pour ses deux devs, et le fil « Pour moi » fait remonter ce qui concerne chacun.
 */
class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $awa;

    private User $kofi;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Notification::fake();
        Event::fake([ConversationModifiee::class, MessagePriveCree::class]);
        $this->awa = User::factory()->create(['username' => 'awa-dev']);
        $this->kofi = User::factory()->create(['username' => 'kofi-dev']);
    }

    private function inviter(string $message = 'Bonjour Kofi, j’ai vu ton correctif MoMo : on en parle ?'): Conversation
    {
        $this->actingAs($this->awa)
            ->postJson('/api/devs/kofi-dev/invitation', ['message' => $message])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente')
            ->assertJsonPath('sens', 'envoyee')
            ->assertJsonPath('avec.username', 'kofi-dev');

        return Conversation::query()->sole();
    }

    public function test_un_dev_invite_un_autre_dev_qui_accepte_puis_ils_s_ecrivent(): void
    {
        $conversation = $this->inviter();
        Notification::assertSentTo($this->kofi, InvitationRecue::class);
        Event::assertDispatched(ConversationModifiee::class, fn (ConversationModifiee $evenement) => $evenement->pour->is($this->kofi));

        $this->actingAs($this->kofi)->getJson('/api/me/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.sens', 'recue')
            ->assertJsonPath('data.0.avec.username', 'awa-dev');

        $this->actingAs($this->kofi)
            ->postJson("/api/conversations/{$conversation->id}/reponse", ['accepter' => true])
            ->assertOk()
            ->assertJsonPath('statut', 'acceptee');
        Notification::assertSentTo($this->awa, InvitationAcceptee::class);

        $this->actingAs($this->awa)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['texte' => 'Merci ! Voici le dépôt.'])
            ->assertCreated()
            ->assertJsonPath('auteur.username', 'awa-dev');
        Notification::assertSentTo($this->kofi, NouveauMessagePrive::class);
        Event::assertDispatched(MessagePriveCree::class, fn (MessagePriveCree $evenement) => $evenement->pour->is($this->kofi));

        $this->actingAs($this->kofi)->getJson('/api/me/conversations')->assertJsonPath('data.0.non_lus', 1);
        $this->actingAs($this->kofi)->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.texte', 'Merci ! Voici le dépôt.');
        $this->actingAs($this->kofi)->getJson('/api/me/conversations')->assertJsonPath('data.0.non_lus', 0);
    }

    public function test_personne_n_ecrit_tant_que_l_invitation_n_est_pas_acceptee(): void
    {
        $conversation = $this->inviter();

        foreach ([$this->awa, $this->kofi] as $compte) {
            $this->actingAs($compte)
                ->postJson("/api/conversations/{$conversation->id}/messages", ['texte' => 'Je force le passage.'])
                ->assertForbidden();
        }

        $this->assertSame(0, $conversation->messages()->count());
        Notification::assertNotSentTo($this->kofi, NouveauMessagePrive::class);
    }

    public function test_seul_l_invite_repond_et_un_refus_ferme_la_porte_pour_trente_jours(): void
    {
        $conversation = $this->inviter();

        $this->actingAs($this->awa)
            ->postJson("/api/conversations/{$conversation->id}/reponse", ['accepter' => true])
            ->assertForbidden();

        $this->actingAs($this->kofi)
            ->postJson("/api/conversations/{$conversation->id}/reponse", ['accepter' => false])
            ->assertOk()
            ->assertJsonPath('statut', 'refusee');
        Notification::assertNotSentTo($this->awa, InvitationAcceptee::class);

        // L'invité ne voit plus l'invitation déclinée ; celui qui a invité ne peut pas insister.
        $this->actingAs($this->kofi)->getJson('/api/me/conversations')->assertJsonCount(0, 'data');
        $this->actingAs($this->awa)
            ->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Je retente ma chance tout de suite.'])
            ->assertStatus(409);

        $this->travel(31)->days();
        $this->actingAs($this->awa)
            ->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Un mois plus tard, une autre question.'])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente');
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_une_seule_conversation_par_paire_dans_les_deux_sens(): void
    {
        $this->inviter();

        $this->actingAs($this->awa)
            ->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Une deuxième invitation, au cas où.'])
            ->assertStatus(409);
        $this->actingAs($this->kofi)
            ->postJson('/api/devs/awa-dev/invitation', ['message' => 'Et dans l’autre sens, pour voir.'])
            ->assertStatus(409);

        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_une_invitation_exige_un_mot_d_introduction_et_un_autre_compte(): void
    {
        $this->actingAs($this->awa)->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Salut'])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->actingAs($this->awa)->postJson('/api/devs/awa-dev/invitation', ['message' => 'Je me parle à moi-même, voilà.'])
            ->assertUnprocessable();
        $this->actingAs($this->awa)->postJson('/api/devs/personne/invitation', ['message' => 'Bonjour, il y a quelqu’un ?'])
            ->assertNotFound();

        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_un_visiteur_ne_peut_pas_inviter(): void
    {
        $this->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Bonjour, je ne suis pas connecté.'])
            ->assertUnauthorized();
    }

    public function test_un_troisieme_compte_ne_voit_ni_la_conversation_ni_ses_messages(): void
    {
        $conversation = $this->inviter();
        $conversation->forceFill(['statut' => Conversation::ACCEPTEE])->save();
        $conversation->messages()->create(['auteur_id' => $this->awa->id, 'texte' => 'Entre nous deux.']);
        $curieux = User::factory()->create();

        $this->actingAs($curieux)->getJson("/api/conversations/{$conversation->id}/messages")->assertNotFound();
        $this->actingAs($curieux)->postJson("/api/conversations/{$conversation->id}/messages", ['texte' => 'Coucou'])->assertNotFound();
        $this->actingAs($curieux)->postJson("/api/conversations/{$conversation->id}/reponse", ['accepter' => true])->assertNotFound();
        $this->actingAs($curieux)->getJson('/api/me/conversations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_une_invitation_peut_demander_de_l_aide_sur_un_bug(): void
    {
        $discussion = Discussion::factory()->create(['user_id' => $this->awa->id, 'titre' => 'Le webhook Wave ne répond pas']);

        $this->actingAs($this->awa)
            ->postJson('/api/devs/kofi-dev/invitation', ['message' => 'Tu connais Wave : un coup d’œil à mon bug ?', 'discussion_id' => $discussion->id])
            ->assertCreated()
            ->assertJsonPath('discussion.id', $discussion->id)
            ->assertJsonPath('discussion.titre', 'Le webhook Wave ne répond pas');
    }

    public function test_le_fil_pour_moi_fait_remonter_canaux_suivis_technos_et_pays(): void
    {
        $general = Canal::factory()->create(['slug' => 'general', 'pays' => null]);
        $suivi = Canal::factory()->create(['slug' => 'laravel', 'pays' => null]);
        $benin = Canal::factory()->create(['slug' => 'benin', 'pays' => 'BJ']);
        $this->awa->forceFill(['pays' => 'BJ', 'competences' => ['react']])->save();

        $ancienneSuivie = Discussion::factory()->create(['canal_id' => $suivi->id, 'created_at' => now()->subDays(5)]);
        $bugReact = Discussion::factory()->create(['canal_id' => $general->id, 'etiquettes' => ['bug'], 'bug' => ['erreur_obtenue' => 'x', 'comportement_attendu' => 'y', 'techno' => 'react'], 'created_at' => now()->subDays(4)]);
        $duPays = Discussion::factory()->create(['canal_id' => $benin->id, 'created_at' => now()->subDays(3)]);
        $recente = Discussion::factory()->create(['canal_id' => $general->id, 'created_at' => now()]);

        $this->actingAs($this->awa)->putJson('/api/canaux/laravel/abonnement')
            ->assertOk()->assertJsonPath('suivi', true)->assertJsonPath('abonnes', 1);
        $reponse = $this->actingAs($this->awa)->getJson('/api/canaux')->assertOk();
        $canaux = collect($reponse->json())->keyBy('slug');
        $this->assertTrue($canaux['laravel']['suivi']);
        $this->assertFalse($canaux['general']['suivi']);

        $ordre = $this->actingAs($this->awa)->getJson('/api/discussions?fil=pour_moi')->assertOk()->json('data.*.id');
        $this->assertSame([$ancienneSuivie->id, $bugReact->id, $duPays->id, $recente->id], $ordre);

        // Sans le fil personnalisé : par date, comme pour un visiteur.
        $this->assertSame($recente->id, $this->actingAs($this->awa)->getJson('/api/discussions')->json('data.0.id'));

        $this->actingAs($this->awa)->deleteJson('/api/canaux/laravel/abonnement')->assertOk()->assertJsonPath('suivi', false);
        $this->assertSame($bugReact->id, $this->actingAs($this->awa)->getJson('/api/discussions?fil=pour_moi')->json('data.0.id'));
    }
}
