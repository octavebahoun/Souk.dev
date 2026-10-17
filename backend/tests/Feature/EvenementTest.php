<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EvenementAnnule;
use App\Events\InscriptionsMaj;
use App\Mail\EvenementAnnuleMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class EvenementTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_dev_cree_un_evenement_d_autres_s_inscrivent_puis_laissent_un_avis(): void
    {
        Event::fake([InscriptionsMaj::class, EvenementAnnule::class]);
        Mail::fake();

        $organisateur = User::factory()->create(['username' => 'wasfade', 'email' => 'orga@exemple.test']);
        $inscrit = User::factory()->create(['username' => 'hope', 'email' => 'hope@exemple.test']);
        $visiteur = User::factory()->create(['username' => 'client']);

        $cree = $this->actingAs($organisateur)->postJson('/api/events', [
            'titre' => 'Atelier Laravel',
            'description' => 'On corrige un callback.',
            'date' => now()->subHour()->toIso8601String(),
            'type' => 'en_ligne',
            'lien' => 'https://meet.example.com/atelier',
        ]);

        $cree->assertCreated()
            ->assertJsonPath('titre', 'Atelier Laravel')
            ->assertJsonPath('lien', 'https://meet.example.com/atelier')
            ->assertJsonPath('nb_inscrits', 0)
            ->assertJsonPath('inscrit', false)
            ->assertJsonPath('organisateur.username', 'wasfade');

        $id = $cree->json('id');

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/events/'.$id)
            ->assertOk()
            ->assertJsonPath('lien', null);

        $this->actingAs($inscrit)->postJson('/api/events/'.$id.'/inscription')->assertNoContent();
        $this->actingAs($inscrit)->postJson('/api/events/'.$id.'/inscription')->assertStatus(409);

        $this->actingAs($inscrit)->getJson('/api/events/'.$id)
            ->assertOk()
            ->assertJsonPath('lien', 'https://meet.example.com/atelier')
            ->assertJsonPath('inscrit', true)
            ->assertJsonPath('nb_inscrits', 1);

        $this->actingAs($visiteur)->getJson('/api/events/'.$id.'/participants')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/events/'.$id.'/participants')->assertForbidden();

        $this->actingAs($inscrit)->getJson('/api/events/'.$id.'/participants')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'hope');

        $this->actingAs($organisateur)->getJson('/api/events/'.$id.'/participants')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $futur = $this->actingAs($organisateur)->postJson('/api/events', [
            'titre' => 'Plus tard',
            'description' => 'Pas encore.',
            'date' => now()->addDay()->toIso8601String(),
            'type' => 'presentiel',
            'lieu' => 'Cotonou',
        ])->assertCreated();

        $this->actingAs($inscrit)->postJson('/api/events/'.$futur->json('id').'/inscription')->assertNoContent();
        $this->actingAs($inscrit)->postJson('/api/events/'.$futur->json('id').'/avis', [
            'note' => 5,
        ])->assertForbidden();

        $avis = $this->actingAs($inscrit)->postJson('/api/events/'.$id.'/avis', [
            'note' => 4,
            'commentaire' => 'Clair.',
        ]);

        $avis->assertCreated()->assertJsonPath('note', 4)->assertJsonPath('auteur.username', 'hope');

        $this->actingAs($inscrit)->postJson('/api/events/'.$id.'/avis', ['note' => 3])->assertStatus(409);
        $this->actingAs($visiteur)->postJson('/api/events/'.$id.'/avis', ['note' => 5])->assertForbidden();

        $this->getJson('/api/events/'.$id.'/avis')
            ->assertOk()
            ->assertJsonPath('data.0.commentaire', 'Clair.');

        $this->getJson('/api/events/'.$id)
            ->assertOk()
            ->assertJsonPath('note_moyenne', 4);

        $this->actingAs($visiteur)->deleteJson('/api/events/'.$id)->assertForbidden();

        $this->actingAs($organisateur)->deleteJson('/api/events/'.$id)->assertNoContent();

        Mail::assertSent(EvenementAnnuleMail::class, function (EvenementAnnuleMail $mail): bool {
            return $mail->hasTo('hope@exemple.test') && $mail->titre === 'Atelier Laravel';
        });
        Event::assertDispatched(InscriptionsMaj::class);
        Event::assertDispatched(EvenementAnnule::class);
        $this->getJson('/api/events/'.$id)->assertNotFound();
    }
}
