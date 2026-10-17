<?php

declare(strict_types=1);

namespace Tests\Feature;

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
