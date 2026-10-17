<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StoreAppliTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_le_store_est_public_et_pagine(): void
    {
        $this->appli(User::factory()->create(), 'Pharmacie');
        $this->appli(User::factory()->create(), 'Clinique');

        $reponse = $this->getJson('/api/apps');

        $reponse->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
        $this->assertCount(2, $reponse->json('data'));
        $this->assertSame(2, $reponse->json('meta.total'));
        $this->assertEqualsCanonicalizing(
            ['Pharmacie', 'Clinique'],
            array_column($reponse->json('data'), 'nom'),
        );
    }

    public function test_la_recherche_filtre_nom_et_description(): void
    {
        $auteur = User::factory()->create();
        $this->appli($auteur, 'Pharmacie du Port');
        $this->appli($auteur, 'École', ['description' => 'Gestion des notes.']);

        $reponse = $this->getJson('/api/apps?q=pharmacie');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('Pharmacie du Port', $reponse->json('data.0.nom'));
    }

    public function test_gratuit_techno_categorie_et_prix_max(): void
    {
        $auteur = User::factory()->create();
        $this->appli($auteur, 'Libre', [
            'prix' => 0,
            'categorie' => 'education',
            'stack' => ['react'],
        ]);
        $this->appli($auteur, 'Payante', [
            'prix' => 15000,
            'categorie' => 'sante',
            'stack' => ['laravel'],
        ]);

        $this->getJson('/api/apps?gratuit=true')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Libre');

        $this->getJson('/api/apps?techno=laravel')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Payante');

        $this->getJson('/api/apps?categorie=education')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Libre');

        $this->getJson('/api/apps?prix_max=0')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Libre');
    }

    public function test_securite_et_tri_par_prix(): void
    {
        $auteur = User::factory()->create();
        $chere = $this->appli($auteur, 'Chère', ['prix' => 20000]);
        $this->appli($auteur, 'Bon marché', ['prix' => 5000]);
        $chere->forceFill(['securite_statut' => 'verifie'])->save();

        $this->getJson('/api/apps?securite=true')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Chère');

        $croissant = $this->getJson('/api/apps?tri=prix_croissant');
        $croissant->assertOk();
        $this->assertSame(['Bon marché', 'Chère'], array_column($croissant->json('data'), 'nom'));

        $decroissant = $this->getJson('/api/apps?tri=prix_decroissant');
        $this->assertSame(['Chère', 'Bon marché'], array_column($decroissant->json('data'), 'nom'));
    }

    public function test_la_fiche_est_publique_et_compte_une_vue_par_jour(): void
    {
        $appli = $this->appli(User::factory()->create(), 'Pharmacie');

        $this->getJson('/api/apps/'.$appli->id)
            ->assertOk()
            ->assertJsonPath('nom', 'Pharmacie')
            ->assertJsonPath('auteur.nom', $appli->auteur->name)
            ->assertJsonPath('readme', null);

        $this->assertSame(1, $appli->refresh()->vues);

        $this->getJson('/api/apps/'.$appli->id)->assertOk();
        $this->assertSame(1, $appli->refresh()->vues);
    }

    public function test_une_appli_inconnue_repond_404(): void
    {
        $this->getJson('/api/apps/999')->assertNotFound();
    }

    public function test_les_categories_sont_la_liste_fixe(): void
    {
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertExactJson(Appli::CATEGORIES);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function appli(User $auteur, string $nom, array $attributs = []): Appli
    {
        return Appli::query()->create([
            'user_id' => $auteur->id,
            'nom' => $nom,
            'description' => $attributs['description'] ?? 'Une appli du store.',
            'categorie' => $attributs['categorie'] ?? 'sante',
            'prix' => $attributs['prix'] ?? 15000,
            'type_prix' => $attributs['type_prix'] ?? 'mensuel',
            'taille' => 'petite',
            'demo_url' => 'https://demo.example.com',
            'depot_url' => 'https://github.com/acme/'.$nom,
            'stack' => $attributs['stack'] ?? ['laravel', 'react'],
            'backend' => 'propre',
            'variables_client' => [],
        ]);
    }
}
