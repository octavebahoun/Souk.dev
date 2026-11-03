<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimiteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_qui_suit_une_construction_n_est_pas_bloque(): void
    {
        $compte = User::factory()->create();

        // Deux minutes d'écran qui se relit toutes les 4 secondes, plus la navigation.
        for ($requete = 1; $requete <= 120; $requete++) {
            $this->actingAs($compte)->getJson('/api/me')->assertOk();
        }
    }

    public function test_au_dela_de_300_requetes_par_minute_une_adresse_est_freinee(): void
    {
        for ($requete = 1; $requete <= 300; $requete++) {
            $this->getJson('/api/canaux')->assertOk();
        }

        $this->getJson('/api/canaux')->assertStatus(429);
    }
}
