<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModificationAppliTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_auteur_modifie_seulement_les_champs_envoyes(): void
    {
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);

        $this->actingAs($auteur)
            ->patchJson('/api/apps/'.$appli->id, [
                'nom' => 'Clinique',
                'prix' => 20000,
            ])
            ->assertOk()
            ->assertJsonPath('nom', 'Clinique')
            ->assertJsonPath('prix', 20000)
            ->assertJsonPath('description', 'Gestion d\'une officine.')
            ->assertJsonPath('categorie', 'sante')
            ->assertJsonPath('taille', 'petite')
            ->assertJsonPath('depot_url', 'https://github.com/acme/cobaye');
    }

    public function test_un_changement_de_taille_ne_modifie_pas_les_copies_existantes(): void
    {
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);
        $copie = Deploiement::query()->create([
            'user_id' => $auteur->id,
            'appli_id' => $appli->id,
            'type' => 'client',
            'taille' => 'petite',
            'etat' => 'en_file',
            'variables' => [],
        ]);

        $this->actingAs($auteur)
            ->patchJson('/api/apps/'.$appli->id, ['taille' => 'grande'])
            ->assertOk()
            ->assertJsonPath('taille', 'grande');

        $this->assertSame('petite', $copie->refresh()->taille);
    }

    public function test_un_autre_compte_recoit_403(): void
    {
        $appli = $this->appli(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/apps/'.$appli->id, ['nom' => 'Clinique'])
            ->assertForbidden();

        $this->assertSame('Pharmacie', $appli->refresh()->nom);
    }

    public function test_un_invite_recoit_401(): void
    {
        $appli = $this->appli(User::factory()->create());

        $this->patchJson('/api/apps/'.$appli->id, ['nom' => 'Clinique'])
            ->assertUnauthorized();
    }

    public function test_une_appli_inconnue_repond_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson('/api/apps/999', ['nom' => 'Clinique'])
            ->assertNotFound();
    }

    public function test_une_stack_avec_doublon_est_refusee(): void
    {
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);

        $this->actingAs($auteur)
            ->patchJson('/api/apps/'.$appli->id, [
                'stack' => ['laravel', 'laravel'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stack');
    }

    public function test_l_auteur_retire_l_appli_et_ses_captures(): void
    {
        Storage::fake('public');
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);
        Storage::disk('public')->put('captures/'.$appli->id.'/1.png', 'ancienne');

        $this->actingAs($auteur)
            ->deleteJson('/api/apps/'.$appli->id)
            ->assertNoContent();

        $this->assertSame(0, Appli::query()->count());
        Storage::disk('public')->assertMissing('captures/'.$appli->id.'/1.png');
    }

    public function test_un_autre_compte_ne_peut_pas_retirer(): void
    {
        $appli = $this->appli(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->deleteJson('/api/apps/'.$appli->id)
            ->assertForbidden();

        $this->assertSame(1, Appli::query()->count());
    }

    public function test_l_auteur_remplace_toutes_les_captures(): void
    {
        Storage::fake('public');
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);
        Storage::disk('public')->put('captures/'.$appli->id.'/1.png', 'ancienne');

        $reponse = $this->actingAs($auteur)->post('/api/apps/'.$appli->id.'/captures', [
            'captures' => [$this->png('couverture.png'), $this->png('ecran.png')],
        ], ['Accept' => 'application/json']);

        $reponse->assertOk()
            ->assertJsonPath('captures.0.url', url('/storage/captures/'.$appli->id.'/1.png'))
            ->assertJsonCount(2, 'captures');

        Storage::disk('public')->assertMissing('captures/'.$appli->id.'/3.png');
        $this->assertNotSame('ancienne', Storage::disk('public')->get('captures/'.$appli->id.'/1.png'));
    }

    public function test_un_autre_compte_ne_peut_pas_remplacer_les_captures(): void
    {
        Storage::fake('public');
        $appli = $this->appli(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post('/api/apps/'.$appli->id.'/captures', [
                'captures' => [$this->png()],
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_des_captures_vides_sont_refusees(): void
    {
        Storage::fake('public');
        $auteur = User::factory()->create();
        $appli = $this->appli($auteur);

        $this->actingAs($auteur)
            ->post('/api/apps/'.$appli->id.'/captures', [
                'captures' => [],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('captures');
    }

    private function appli(User $auteur): Appli
    {
        return Appli::query()->create([
            'user_id' => $auteur->id,
            'nom' => 'Pharmacie',
            'description' => 'Gestion d\'une officine.',
            'categorie' => 'sante',
            'prix' => 15000,
            'type_prix' => 'mensuel',
            'taille' => 'petite',
            'demo_url' => 'https://demo.example.com',
            'depot_url' => 'https://github.com/acme/cobaye',
            'stack' => ['laravel', 'react'],
            'captures' => [['url' => 'http://localhost/storage/captures/1/1.png']],
            'backend' => 'propre',
            'variables_client' => [],
        ]);
    }

    private function png(string $nom = 'couverture.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        return UploadedFile::fake()->createWithContent($nom, $png);
    }
}
