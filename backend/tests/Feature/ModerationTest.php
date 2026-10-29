<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ReactiverCopieAbus;
use App\Jobs\SuspendreCopieAbus;
use App\Mail\LienMagiqueMail;
use App\Models\Abonnement;
use App\Models\Canal;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\Signalement;
use App\Models\User;
use App\Moteur\MoteurDeploiement;
use App\Notifications\AlerteAdmin;
use App\Notifications\CompteSuspendu;
use App\Notifications\CopieSuspendueAbus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UtilisateurGithub;
use Tests\TestCase;

/**
 * Modération (phase 6) : un membre signale un contenu, l'équipe traite, suspend une copie
 * pour abus ou un compte, et gère les canaux. Réservé à l'équipe, sauf le signalement.
 */
final class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_signale_une_discussion(): void
    {
        Notification::fake();
        $membre = User::factory()->create();
        $discussion = Discussion::factory()->create();

        $reponse = $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion',
            'cible_id' => $discussion->id,
            'motif' => 'spam',
            'details' => 'Contenu promotionnel hors sujet.',
        ])->assertCreated();

        $reponse->assertJsonPath('cible', 'discussion')
            ->assertJsonPath('statut', 'ouvert')
            ->assertJsonPath('signale_par.id', $membre->id);
        $this->assertStringContainsString('Discussion #'.$discussion->id, (string) $reponse->json('cible_resume'));
        $this->assertDatabaseHas('signalements', [
            'user_id' => $membre->id, 'cible' => 'discussion', 'cible_id' => $discussion->id, 'motif' => 'spam',
        ]);

        // Un spam ne réveille pas l'équipe : il attend dans la liste.
        Notification::assertNothingSent();
    }

    public function test_un_motif_grave_previent_l_equipe_aussitot(): void
    {
        Notification::fake();
        $copie = Deploiement::factory()->create();

        $this->actingAs(User::factory()->create())->postJson('/api/signalements', [
            'cible' => 'copie',
            'cible_id' => $copie->id,
            'motif' => 'minage',
            'details' => 'Le processeur tourne à plein régime depuis des jours.',
        ])->assertCreated();

        Notification::assertSentOnDemand(
            AlerteAdmin::class,
            fn (AlerteAdmin $alerte, array $canaux, object $destinataire): bool => str_contains($alerte->sujet, 'minage')
        );
    }

    public function test_signaler_exige_une_cible_existante_et_sans_doublon(): void
    {
        $membre = User::factory()->create();
        $discussion = Discussion::factory()->create();

        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion', 'cible_id' => 999999, 'motif' => 'spam',
        ])->assertStatus(422)->assertJsonValidationErrors('cible_id');

        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion', 'cible_id' => $discussion->id, 'motif' => 'spam',
        ])->assertCreated();

        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion', 'cible_id' => $discussion->id, 'motif' => 'abus',
        ])->assertStatus(409);
    }

    public function test_signaler_est_bride_a_vingt_par_heure(): void
    {
        $membre = User::factory()->create();
        $discussions = Discussion::factory()->count(21)->create();

        foreach ($discussions->take(20) as $discussion) {
            $this->actingAs($membre)->postJson('/api/signalements', [
                'cible' => 'discussion', 'cible_id' => $discussion->id, 'motif' => 'spam',
            ])->assertCreated();
        }

        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion', 'cible_id' => $discussions->last()->id, 'motif' => 'spam',
        ])->assertStatus(429);
    }

    public function test_l_admin_liste_lit_traite_et_rejette_les_signalements(): void
    {
        $admin = User::factory()->admin()->create();
        $ouvert = Signalement::factory()->create(['statut' => 'ouvert']);
        Signalement::factory()->create(['statut' => 'traite']);

        $this->actingAs($admin)->getJson('/api/admin/signalements?statut=ouvert')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ouvert->id);

        $this->actingAs($admin)->getJson('/api/admin/signalements/'.$ouvert->id)
            ->assertOk()
            ->assertJsonPath('cible_resume', $ouvert->cibleResume());

        $this->actingAs($admin)->getJson('/api/admin/signalements/999999')->assertNotFound();

        $this->actingAs($admin)->postJson('/api/admin/signalements/'.$ouvert->id.'/traiter', ['note' => 'Contenu supprimé.'])
            ->assertOk()
            ->assertJsonPath('statut', 'traite')
            ->assertJsonPath('note', 'Contenu supprimé.')
            ->assertJsonPath('traite_par.id', $admin->id);

        $this->actingAs($admin)->postJson('/api/admin/signalements/'.$ouvert->id.'/rejeter')->assertStatus(409);

        $second = Signalement::factory()->create(['statut' => 'ouvert']);
        $this->actingAs($admin)->postJson('/api/admin/signalements/'.$second->id.'/rejeter', ['note' => 'Rien d’anormal.'])
            ->assertOk()
            ->assertJsonPath('statut', 'rejete');
    }

    public function test_un_signalement_survit_a_sa_cible_supprimee(): void
    {
        $admin = User::factory()->admin()->create();
        $signalement = Signalement::factory()->create();
        Discussion::query()->whereKey($signalement->cible_id)->delete();

        $this->actingAs($admin)->getJson('/api/admin/signalements/'.$signalement->id)
            ->assertOk()
            ->assertJsonPath('cible_resume', 'Discussion #'.$signalement->cible_id.' (supprimée)');
    }

    public function test_l_admin_suspend_un_compte_qui_ne_peut_plus_agir(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $membre = User::factory()->github()->create();

        $this->actingAs($admin)->postJson('/api/admin/comptes/'.$membre->id.'/suspension', ['motif' => 'Harcèlement répété.'])
            ->assertOk()
            ->assertJsonPath('suspension_motif', 'Harcèlement répété.');

        Notification::assertSentTo($membre, CompteSuspendu::class);

        // L'instance du test est périmée : une vraie requête recharge le compte depuis la session.
        $membre = $membre->refresh();

        // Le compte lit son motif, mais ne peut plus rien faire.
        $this->actingAs($membre)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('suspension_motif', 'Harcèlement répété.');
        $this->actingAs($membre)->postJson('/api/discussions', ['titre' => 'x', 'texte' => 'y'])
            ->assertForbidden();
        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'discussion', 'cible_id' => 1, 'motif' => 'spam',
        ])->assertForbidden();

        // Déjà suspendu, puis levée : le compte agit de nouveau.
        $this->actingAs($admin)->postJson('/api/admin/comptes/'.$membre->id.'/suspension', ['motif' => 'Encore.'])
            ->assertStatus(409);
        $this->actingAs($admin)->deleteJson('/api/admin/comptes/'.$membre->id.'/suspension')
            ->assertOk()
            ->assertJsonPath('suspendu_le', null);
        $this->actingAs($admin)->deleteJson('/api/admin/comptes/'.$membre->id.'/suspension')->assertStatus(409);
    }

    public function test_l_equipe_ne_suspend_pas_son_propre_compte(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/comptes/'.$admin->id.'/suspension', ['motif' => 'Erreur.'])
            ->assertStatus(409);

        $this->actingAs($admin)->postJson('/api/admin/comptes/999999/suspension', ['motif' => 'x'])
            ->assertNotFound();
    }

    public function test_un_compte_suspendu_ne_se_connecte_plus(): void
    {
        Mail::fake();
        $client = User::factory()->client()->create([
            'email' => 'suspendu@example.com',
            'suspendu_le' => now(),
            'suspension_motif' => 'Spam.',
        ]);

        $this->postJson('/auth/lien-magique', ['email' => 'suspendu@example.com'])->assertStatus(202);

        $url = null;
        Mail::assertSent(LienMagiqueMail::class, function (LienMagiqueMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->getJson('/auth/lien-magique/'.$token)
            ->assertForbidden()
            ->assertJsonPath('message', 'Compte suspendu : Spam.');
        $this->assertGuest();
    }

    public function test_un_compte_suspendu_est_refuse_au_retour_github(): void
    {
        User::factory()->create([
            'github_id' => '99', 'username' => 'suspendu', 'github_lie' => true,
            'suspendu_le' => now(), 'suspension_motif' => 'Abus.',
        ]);

        $github = (new UtilisateurGithub)->map([
            'id' => '99', 'nickname' => 'suspendu', 'name' => 'Suspendu',
            'email' => 'suspendu@example.com', 'avatar' => 'https://avatars.githubusercontent.com/u/99',
        ]);
        $driver = \Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($github);
        Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver);

        $this->withSession(['profil_entree' => 'dev'])
            ->get('/auth/github/callback')
            ->assertRedirect('http://localhost:5173/connexion?erreur=compte_suspendu');
        $this->assertGuest();
    }

    public function test_l_admin_liste_les_comptes_et_les_suspendus(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'chef']);
        $suspendu = User::factory()->github()->create(['username' => 'fauteur', 'suspendu_le' => now(), 'suspension_motif' => 'Spam.']);

        $this->actingAs($admin)->getJson('/api/admin/comptes?recherche=fauteur')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'fauteur');

        $this->actingAs($admin)->getJson('/api/admin/comptes?suspendus=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $suspendu->id);
    }

    public function test_l_admin_suspend_une_copie_pour_abus_puis_la_reactive(): void
    {
        Notification::fake();
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $copie = Deploiement::factory()->create(['etat' => 'en_ligne', 'copie' => 'd12', 'repertoire' => '/tmp/x']);

        $this->actingAs($admin)->postJson('/api/admin/deployments/'.$copie->id.'/suspension', ['motif' => 'Minage détecté.'])
            ->assertOk()
            ->assertJsonPath('etat', 'suspendu')
            ->assertJsonPath('suspension_admin_motif', 'Minage détecté.');

        Queue::assertPushed(SuspendreCopieAbus::class, fn ($job) => $job->deploiementId === $copie->id);
        Notification::assertSentTo($copie->utilisateur, CopieSuspendueAbus::class);

        $this->actingAs($admin)->postJson('/api/admin/deployments/'.$copie->id.'/suspension', ['motif' => 'Encore.'])
            ->assertStatus(409);

        $this->actingAs($admin)->getJson('/api/admin/deployments?suspension_admin=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $copie->id);

        $this->actingAs($admin)->deleteJson('/api/admin/deployments/'.$copie->id.'/suspension')
            ->assertOk()
            ->assertJsonPath('suspension_admin_motif', null);

        Queue::assertPushed(ReactiverCopieAbus::class, fn ($job) => $job->deploiementId === $copie->id);

        $this->actingAs($admin)->deleteJson('/api/admin/deployments/'.$copie->id.'/suspension')->assertStatus(409);
    }

    public function test_seule_une_copie_en_ligne_est_suspendue_pour_abus(): void
    {
        $admin = User::factory()->admin()->create();
        $arretee = Deploiement::factory()->create(['etat' => 'arrete']);

        $this->actingAs($admin)->postJson('/api/admin/deployments/'.$arretee->id.'/suspension', ['motif' => 'x'])
            ->assertStatus(409);
    }

    public function test_une_copie_suspendue_pour_impaye_ne_repart_pas_par_l_abus(): void
    {
        $admin = User::factory()->admin()->create();
        $copie = Deploiement::factory()->create(['etat' => 'suspendu', 'suspension_admin_motif' => 'Minage.']);
        Abonnement::factory()->create(['deploiement_id' => $copie->id, 'statut' => 'suspendu']);

        $this->actingAs($admin)->deleteJson('/api/admin/deployments/'.$copie->id.'/suspension')
            ->assertStatus(409);
    }

    public function test_les_travaux_d_abus_ne_touchent_pas_une_copie_changee_entre_temps(): void
    {
        $moteur = \Mockery::mock(MoteurDeploiement::class);
        $moteur->shouldNotReceive('suspendre');
        $moteur->shouldNotReceive('reprendre');

        // Suspension levée entre-temps : le travail ne suspend plus rien.
        $levee = Deploiement::factory()->create(['etat' => 'en_ligne', 'suspension_admin_motif' => null]);
        (new SuspendreCopieAbus($levee->id))->handle($moteur);

        // Re-suspendue entre-temps : le travail ne relance plus rien.
        $resuspendue = Deploiement::factory()->create(['etat' => 'suspendu', 'suspension_admin_motif' => 'Minage.']);
        (new ReactiverCopieAbus($resuspendue->id))->handle($moteur);

        $this->assertTrue(true);
    }

    public function test_l_admin_renomme_et_supprime_un_canal_vide(): void
    {
        $admin = User::factory()->admin()->create();
        $canal = Canal::factory()->create(['nom' => 'Laravel']);

        $this->actingAs($admin)->patchJson('/api/admin/canaux/'.$canal->slug, [
            'nom' => 'Laravel francophone', 'description' => 'Entraide Laravel.',
        ])->assertOk()
            ->assertJsonPath('nom', 'Laravel francophone')
            ->assertJsonPath('slug', $canal->slug);

        $this->actingAs($admin)->deleteJson('/api/admin/canaux/'.$canal->slug)->assertNoContent();
        $this->assertDatabaseMissing('canaux', ['id' => $canal->id]);
    }

    public function test_un_canal_avec_des_discussions_n_est_pas_supprime(): void
    {
        $admin = User::factory()->admin()->create();
        $discussion = Discussion::factory()->create();

        $this->actingAs($admin)->deleteJson('/api/admin/canaux/'.$discussion->canal->slug)
            ->assertStatus(409);

        $this->actingAs($admin)->patchJson('/api/admin/canaux/canal-inconnu', ['nom' => 'x'])
            ->assertNotFound();
    }

    public function test_l_administration_est_interdite_hors_equipe(): void
    {
        // Invité d'abord : actingAs persiste pour le reste du test.
        $this->getJson('/api/admin/signalements')->assertUnauthorized();
        $this->postJson('/api/signalements', ['cible' => 'discussion', 'cible_id' => 1, 'motif' => 'spam'])
            ->assertUnauthorized();

        $membre = User::factory()->create();
        $signalement = Signalement::factory()->create();
        $canal = Canal::factory()->create();
        $copie = Deploiement::factory()->create();

        $routes = [
            ['get', '/api/admin/signalements'],
            ['get', '/api/admin/signalements/'.$signalement->id],
            ['post', '/api/admin/signalements/'.$signalement->id.'/traiter'],
            ['post', '/api/admin/signalements/'.$signalement->id.'/rejeter'],
            ['get', '/api/admin/comptes'],
            ['post', '/api/admin/comptes/'.$membre->id.'/suspension'],
            ['delete', '/api/admin/comptes/'.$membre->id.'/suspension'],
            ['get', '/api/admin/deployments'],
            ['post', '/api/admin/deployments/'.$copie->id.'/suspension'],
            ['delete', '/api/admin/deployments/'.$copie->id.'/suspension'],
            ['patch', '/api/admin/canaux/'.$canal->slug],
            ['delete', '/api/admin/canaux/'.$canal->slug],
        ];

        foreach ($routes as [$methode, $url]) {
            $corps = in_array($methode, ['post', 'patch'], true) ? ['motif' => 'x', 'nom' => 'x'] : [];
            $this->actingAs($membre)->json($methode, $url, $corps)->assertForbidden($url);
        }

        // Le contrôle d'admin passe avant le 404 : un identifiant inconnu ne se révèle pas.
        $this->actingAs($membre)->getJson('/api/admin/signalements/999999')->assertForbidden();
        $this->actingAs($membre)->getJson('/api/admin/comptes?suspendus=1')->assertForbidden();
    }
}
