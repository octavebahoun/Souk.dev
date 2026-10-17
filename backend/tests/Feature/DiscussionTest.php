<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Canal;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_dev_cree_une_discussion_et_la_retrouve_dans_le_fil(): void
    {
        $auteur = User::factory()->create(['name' => 'Wasfade', 'username' => 'wasfade']);
        Canal::factory()->create(['slug' => 'laravel', 'nom' => 'Laravel']);

        $reponse = $this->actingAs($auteur)->postJson('/api/discussions', [
            'titre' => 'Le callback MoMo renvoie 500',
            'texte' => 'Le paiement plante.',
            'canal' => 'laravel',
            'etiquettes' => ['bug'],
            'depot_url' => 'https://github.com/wasfade/momo',
            'bug' => [
                'erreur_obtenue' => 'Le callback renvoie 500',
                'comportement_attendu' => 'La commande passe en payée',
                'techno' => 'laravel',
                'difficulte' => 'moyen',
            ],
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('titre', 'Le callback MoMo renvoie 500')
            ->assertJsonPath('canal', 'laravel')
            ->assertJsonPath('auteur.username', 'wasfade')
            ->assertJsonPath('etiquettes', ['bug'])
            ->assertJsonPath('bug.difficulte', 'moyen')
            ->assertJsonPath('copie_test', null)
            ->assertJsonPath('analyse_ia', null)
            ->assertJsonPath('resolue', false)
            ->assertJsonPath('nb_messages', 0)
            ->assertJsonStructure(['cree_le']);

        $this->getJson('/api/discussions?canal=laravel&etiquette=bug&statut=ouvertes&difficulte=moyen')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reponse->json('id'))
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_une_discussion_est_dans_un_canal_ou_sur_une_appli(): void
    {
        $auteur = User::factory()->create();
        Canal::factory()->create(['slug' => 'laravel', 'nom' => 'Laravel']);

        $this->actingAs($auteur)->postJson('/api/discussions', [
            'titre' => 'Sans lieu',
            'texte' => 'Rien.',
        ])->assertStatus(422);

        $this->actingAs($auteur)->postJson('/api/discussions', [
            'titre' => 'Les deux',
            'texte' => 'Rien.',
            'canal' => 'laravel',
            'app_id' => 1,
        ])->assertStatus(422);

        $this->actingAs($auteur)->postJson('/api/discussions', [
            'titre' => 'Appli absente',
            'texte' => 'Rien.',
            'app_id' => 1,
        ])->assertStatus(422)->assertJsonPath('errors.app_id.0', 'Cette appli n\'existe pas.');
    }

    public function test_un_bug_sans_fiche_est_refuse(): void
    {
        $auteur = User::factory()->create();
        Canal::factory()->create(['slug' => 'laravel', 'nom' => 'Laravel']);

        $this->actingAs($auteur)->postJson('/api/discussions', [
            'titre' => 'Bug',
            'texte' => 'Ça casse.',
            'canal' => 'laravel',
            'etiquettes' => ['bug'],
        ])->assertStatus(422)->assertJsonPath('errors.bug.0', 'La fiche du bug est obligatoire pour cette étiquette.');
    }

    public function test_seul_l_auteur_modifie_et_un_admin_peut_supprimer(): void
    {
        $auteur = User::factory()->create();
        $autre = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $discussion = Discussion::factory()->for($auteur, 'auteur')->create(['titre' => 'Avant']);

        $this->actingAs($autre)
            ->patchJson('/api/discussions/'.$discussion->id, ['titre' => 'Non'])
            ->assertStatus(403);

        $this->actingAs($auteur)
            ->patchJson('/api/discussions/'.$discussion->id, ['resolue' => true, 'titre' => 'Après'])
            ->assertOk()
            ->assertJsonPath('resolue', true)
            ->assertJsonPath('titre', 'Après');

        $this->actingAs($admin)
            ->deleteJson('/api/discussions/'.$discussion->id)
            ->assertNoContent();

        $this->getJson('/api/discussions/'.$discussion->id)->assertStatus(404);
    }

    public function test_un_visiteur_ne_peut_pas_poster(): void
    {
        $this->postJson('/api/discussions', [
            'titre' => 'Privé',
            'texte' => 'Non.',
            'canal' => 'laravel',
        ])->assertStatus(401);
    }

    public function test_la_creation_est_limitee_a_dix_par_heure(): void
    {
        $auteur = User::factory()->create();
        $canal = Canal::factory()->create(['slug' => 'laravel', 'nom' => 'Laravel']);

        $corps = [
            'titre' => 'Question',
            'texte' => 'Encore.',
            'canal' => $canal->slug,
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($auteur)->postJson('/api/discussions', $corps)->assertCreated();
        }

        $this->actingAs($auteur)->postJson('/api/discussions', $corps)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Limite de débit atteinte.');
    }
}
