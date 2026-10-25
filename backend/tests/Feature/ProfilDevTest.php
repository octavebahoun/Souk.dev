<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Techno;
use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProfilDevTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_profil_public_montre_les_depots_et_les_applis(): void
    {
        Http::fake([
            'https://api.github.com/users/wasfade/repos*' => Http::response([[
                'name' => 'momo',
                'full_name' => 'wasfade/momo',
                'description' => 'Paiement',
                'html_url' => 'https://github.com/wasfade/momo',
                'stargazers_count' => 3,
                'language' => 'PHP',
            ]]),
            'https://api.github.com/repos/wasfade/momo/readme' => Http::response('## MoMo', 200),
        ]);

        $dev = User::factory()->create([
            'name' => 'Wasfade',
            'username' => 'wasfade',
            'bio' => 'Backend',
            'competences' => ['laravel'],
            'disponible' => true,
            'pays' => 'BJ',
        ]);

        Appli::query()->create([
            'user_id' => $dev->id,
            'nom' => 'Pharmacie',
            'description' => 'Gestion.',
            'categorie' => 'sante',
            'prix' => 0,
            'type_prix' => 'mensuel',
            'taille' => 'petite',
            'demo_url' => 'https://demo.example.com',
            'depot_url' => 'https://github.com/wasfade/pharmacie',
            'stack' => ['laravel'],
            'backend' => 'propre',
            'variables_client' => [],
        ]);

        $this->getJson('/api/devs/wasfade')
            ->assertOk()
            ->assertJsonPath('username', 'wasfade')
            ->assertJsonPath('bio', 'Backend')
            ->assertJsonPath('pays', 'BJ')
            ->assertJsonPath('disponible', true)
            ->assertJsonPath('depots.0.nom', 'momo')
            ->assertJsonPath('depots.0.etoiles', 3)
            ->assertJsonPath('depots.0.readme', '## MoMo')
            ->assertJsonPath('applis.0.nom', 'Pharmacie')
            ->assertJsonPath('stats.bugs_resolus', 0)
            ->assertJsonPath('stats.applis_publiees', 1)
            ->assertJsonPath('reputation.xp', 100)
            ->assertJsonPath('reputation.badges', ['publie'])
            // projets 50 + activité 15 (une appli publiée aujourd'hui)
            ->assertJsonPath('souk_score.total', 65);

        $this->getJson('/api/devs/inconnu')->assertNotFound();
    }

    public function test_un_correctif_accepte_fait_monter_le_dev_dans_la_recherche(): void
    {
        $auteur = User::factory()->github()->create(['name' => 'Yann']);
        $aidant = User::factory()->github()->create(['name' => 'Zoé']);
        $autre = User::factory()->github()->create(['name' => 'Aïcha']);
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id]);
        $correctif = Correctif::query()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $aidant->id,
            'depot_url' => 'https://github.com/acme/momo/tree/correctif',
            'accepte' => false,
        ]);

        $this->actingAs($auteur)->postJson('/api/correctifs/'.$correctif->id.'/accepter')->assertOk();

        // entraide 25 + activité 15
        $this->assertSame(40, $aidant->refresh()->souk_score);

        $this->getJson('/api/devs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $aidant->id)
            ->assertJsonPath('data.0.stats.bugs_resolus', 1)
            ->assertJsonPath('data.0.reputation.badges', ['premier_correctif'])
            ->assertJsonPath('data.1.id', $autre->id);
    }

    public function test_les_technos_sont_la_liste_fixe(): void
    {
        $this->getJson('/api/technos')
            ->assertOk()
            ->assertExactJson(Techno::valeurs());
    }
}
