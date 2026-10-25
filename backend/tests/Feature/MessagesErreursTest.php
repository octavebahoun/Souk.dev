<?php

namespace Tests\Feature;

use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Les refus de l'API parlent français et gardent leur raison. */
class MessagesErreursTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_refus_par_defaut_est_traduit(): void
    {
        $deploiement = Deploiement::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/deployments/'.$deploiement->id)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Action non autorisée.']);
    }

    public function test_un_refus_qui_cache_la_ressource_repond_introuvable(): void
    {
        Gate::define('voir-en-secret', fn () => Response::denyAsNotFound());
        Route::middleware('api')->get('/api/essai-secret', fn () => Gate::authorize('voir-en-secret'));

        $this->actingAs(User::factory()->create())
            ->getJson('/api/essai-secret')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Introuvable.']);
    }

    public function test_la_raison_d_un_refus_est_gardee(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->postJson('/api/apps/verification', ['depot_url' => 'https://github.com/acme/pharmacie'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'GitHub n\'est pas lié à ce compte.']);
    }
}
