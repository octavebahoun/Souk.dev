<?php

namespace Tests\Feature;

use App\Events\DeploiementEtat;
use App\Models\Deploiement;
use App\Models\User;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NettoyageCopieTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_rate_deux_fois_passe_en_echec_et_repond_503(): void
    {
        Event::fake([DeploiementEtat::class]);
        $client = User::factory()->create();
        $deploiement = $this->copie($client, 'en_ligne');

        $this->mock(LanceurCompose::class, function ($mock) {
            $mock->shouldReceive('arreter')->twice()->andThrow(new LancementEchoue('daemon injoignable'));
        });

        $this->actingAs($client)->deleteJson('/api/deployments/'.$deploiement->id)->assertStatus(503);

        $this->assertSame('echec', $deploiement->refresh()->etat);
        Event::assertDispatched(DeploiementEtat::class);
    }

    public function test_delete_est_idempotent(): void
    {
        Event::fake([DeploiementEtat::class]);
        $client = User::factory()->create();
        $deploiement = $this->copie($client, 'arrete');

        $this->mock(LanceurCompose::class, fn ($mock) => $mock->shouldNotReceive('arreter'));

        $this->actingAs($client)->deleteJson('/api/deployments/'.$deploiement->id)->assertNoContent();
        $this->assertSame('arrete', $deploiement->refresh()->etat);
    }

    public function test_la_commande_arrete_seulement_les_copies_expirees(): void
    {
        Event::fake([DeploiementEtat::class]);
        $client = User::factory()->create();
        $expiree = $this->copie($client, 'en_ligne', now()->subMinute());
        $valide = $this->copie($client, 'en_ligne', now()->addHour());

        $this->mock(LanceurCompose::class, function ($mock) use ($expiree) {
            $mock->shouldReceive('arreter')->once()->with('/tmp/copie-'.$expiree->id, 'd'.$expiree->id);
        });

        $this->artisan('moteur:nettoyer', ['--sans-orphelins' => true])->assertSuccessful();

        $this->assertSame('arrete', $expiree->refresh()->etat);
        $this->assertSame('en_ligne', $valide->refresh()->etat);
        Event::assertDispatched(DeploiementEtat::class, fn (DeploiementEtat $evenement) => $evenement->deploiementId === $expiree->id);
    }

    private function copie(User $client, string $etat, mixed $expire = null): Deploiement
    {
        $deploiement = Deploiement::query()->create([
            'user_id' => $client->id,
            'appli_id' => null,
            'depot_url' => 'https://github.com/wasfade/momo',
            'type' => 'test',
            'taille' => 'petite',
            'etat' => $etat,
            'variables' => [],
            'expire_le' => $expire,
        ]);

        $deploiement->forceFill(['copie' => 'd'.$deploiement->id, 'repertoire' => '/tmp/copie-'.$deploiement->id])->save();

        return $deploiement;
    }
}
