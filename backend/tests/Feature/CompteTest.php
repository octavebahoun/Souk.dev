<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompteTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_compte_connecte_respecte_le_contrat(): void
    {
        $compte = User::factory()->create([
            'name' => 'Wasfade',
            'email' => 'wasfade@example.com',
            'username' => 'wasfade',
            'pays' => 'BJ',
            'bio' => 'Backend',
            'competences' => ['laravel', 'php'],
            'specialites' => ['backend'],
            'disponible' => true,
            'github_lie' => true,
            'github_id' => '42',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/42',
        ]);

        $this->actingAs($compte)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('nom', 'Wasfade')
            ->assertJsonPath('username', 'wasfade')
            ->assertJsonPath('github_url', 'https://github.com/wasfade')
            ->assertJsonPath('email', 'wasfade@example.com')
            ->assertJsonPath('github_lie', true)
            ->assertJsonPath('est_admin', false)
            ->assertJsonPath('profil', 'dev')
            ->assertJsonPath('stats.bugs_resolus', 0)
            ->assertJsonPath('reputation.niveau', 'debutant')
            ->assertJsonPath('souk_score.total', 0)
            ->assertJsonMissingPath('password');
    }

    public function test_un_visiteur_recoit_401(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Non connecté.');
    }

    public function test_le_tableau_de_bord_calcule_les_copies_et_revenus_de_ses_applis(): void
    {
        $compte = User::factory()->create();
        $mensuelle = Appli::query()->create([
            'user_id' => $compte->id,
            'nom' => 'Boutique',
            'depot_url' => 'https://github.com/equipe/boutique',
            'taille' => 'moyenne',
            'prix' => 15000,
            'type_prix' => 'mensuel',
        ]);
        $unique = Appli::query()->create([
            'user_id' => $compte->id,
            'nom' => 'Stock',
            'depot_url' => 'https://github.com/equipe/stock',
            'taille' => 'petite',
            'prix' => 90000,
            'type_prix' => 'unique',
        ]);

        foreach ([[$mensuelle, 'en_ligne'], [$mensuelle, 'arrete'], [$unique, 'echec']] as [$appli, $etat]) {
            Deploiement::query()->create([
                'user_id' => $compte->id,
                'appli_id' => $appli->id,
                'type' => 'client',
                'taille' => 'moyenne',
                'etat' => $etat,
                'variables' => [],
            ]);
        }

        $this->actingAs($compte)
            ->getJson('/api/me/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('totaux.copies_actives', 1)
            ->assertJsonPath('totaux.revenus_estimes_mensuels', 15000)
            ->assertJsonPath('totaux.revenus_estimes_uniques', 90000)
            ->assertJsonPath('applis.0.nom', 'Stock')
            ->assertJsonPath('applis.0.deploiements_total', 1)
            ->assertJsonPath('applis.0.revenu_estime', 90000);
    }

    public function test_le_tableau_de_bord_est_reserve_aux_comptes_connectes(): void
    {
        $this->getJson('/api/me/tableau-de-bord')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Non connecté.');
    }

    public function test_la_recherche_publique_filtre_les_devs_par_competence_pays_et_disponibilite(): void
    {
        User::factory()->create([
            'name' => 'Afi Dossou',
            'username' => 'afi-dossou',
            'pays' => 'BJ',
            'competences' => ['laravel', 'php'],
            'specialites' => ['backend'],
            'disponible' => true,
        ]);
        User::factory()->create([
            'name' => 'Mariam Sow',
            'username' => 'mariam-sow',
            'pays' => 'SN',
            'competences' => ['react'],
            'specialites' => ['frontend'],
            'disponible' => true,
        ]);

        $this->getJson('/api/devs?disponible=true&competence=laravel&specialite=backend&pays=BJ')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'afi-dossou')
            ->assertJsonPath('data.0.stats.bugs_resolus', 0);
    }

    public function test_le_profil_se_modifie_sans_ecraser_les_champs_absents(): void
    {
        $compte = User::factory()->create([
            'bio' => 'Ancienne bio',
            'pays' => 'SN',
        ]);

        $this->actingAs($compte)
            ->patchJson('/api/me', [
                'bio' => null,
                'disponible' => true,
                'competences' => ['laravel'],
            ])
            ->assertOk()
            ->assertJsonPath('bio', null)
            ->assertJsonPath('pays', 'SN')
            ->assertJsonPath('disponible', true)
            ->assertJsonPath('competences', ['laravel']);
    }

    public function test_la_deconnexion_ferme_la_session(): void
    {
        $compte = User::factory()->create();

        $this->actingAs($compte)->post('/logout')->assertNoContent();

        $this->assertGuest();
    }
}
