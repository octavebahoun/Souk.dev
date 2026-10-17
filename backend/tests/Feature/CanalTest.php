<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Canal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CanalTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_etiquettes_sont_la_liste_fixe(): void
    {
        $this->getJson('/api/etiquettes')
            ->assertOk()
            ->assertExactJson(['bug', 'question', 'tuto', 'projet', 'entraide']);
    }

    public function test_un_compte_cree_un_canal_et_le_filtre_par_pays(): void
    {
        $compte = User::factory()->create();

        $this->actingAs($compte)->postJson('/api/canaux', [
            'nom' => 'Laravel Bénin',
            'description' => 'Entraide Laravel',
            'pays' => 'BJ',
        ])->assertCreated()
            ->assertJsonPath('slug', 'laravel-benin')
            ->assertJsonPath('pays', 'BJ');

        Canal::factory()->create(['slug' => 'laravel', 'nom' => 'Laravel', 'pays' => null]);

        $this->getJson('/api/canaux?pays=BJ')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.slug', 'laravel-benin');
    }

    public function test_un_visiteur_ne_cree_pas_de_canal(): void
    {
        $this->postJson('/api/canaux', ['nom' => 'Laravel'])->assertStatus(401);
    }
}
