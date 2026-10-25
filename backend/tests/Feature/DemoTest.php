<?php

namespace Tests\Feature;

use App\Comptes\EmetteurLienMagique;
use App\Models\Appli;
use App\Models\Discussion;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\User;
use App\Paiement\GrandLivre;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_le_jeu_de_demo_est_identifie_et_relancable(): void
    {
        $vrai = User::factory()->github()->create();

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $demo = User::query()->where('est_demo', true);
        $this->assertSame(9, $demo->count());
        $this->assertSame(0, (clone $demo)->where('email', 'not like', '%@demo.souk.dev')->count());
        $this->assertSame(8, Appli::query()->count());
        $this->assertTrue(Appli::query()->where('depot_url', 'https://github.com/octavebahoun/marchilearnia')->exists());
        $this->assertTrue(Discussion::query()->where('resolue', true)->exists());
        $this->assertNotNull($vrai->fresh(), 'Un vrai compte ne doit jamais être touché.');
        // Facturation de démo relancée deux fois : grand livre équilibré, Awa garde 15 000 F.
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
        $this->assertSame(15000, app(GrandLivre::class)->soldeDev(User::query()->where('username', 'soukdemo-awa')->sole()->id));
        $this->assertSame(1, Facture::query()->where('statut', 'a_payer')->count());
        Storage::disk('public')->assertExists('captures/'.Appli::query()->first()->id.'/1.webp');
    }

    public function test_aucun_e_mail_ne_part_vers_un_compte_de_demo(): void
    {
        Mail::fake();
        $this->seed(DemoSeeder::class);
        $demo = User::query()->where('username', 'soukdemo-pharmacie')->sole();

        $this->assertNull($demo->routeNotificationFor('mail'));
        $reel = User::factory()->client()->create();
        $this->assertSame($reel->email, $reel->routeNotificationFor('mail'));
        app(EmetteurLienMagique::class)->demander($demo->email);

        Mail::assertNothingSent();
    }

    public function test_la_connexion_de_demo_est_introuvable_par_defaut(): void
    {
        $this->seed(DemoSeeder::class);

        $this->postJson('/auth/demo', ['compte' => 'soukdemo-awa'])->assertNotFound();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_la_connexion_de_demo_ouvre_seulement_un_compte_de_demo(): void
    {
        config(['app.demo_connexion' => true]);
        $this->seed(DemoSeeder::class);
        $vrai = User::factory()->github()->create();

        $this->postJson('/auth/demo', ['compte' => $vrai->username])->assertNotFound();

        $this->postJson('/auth/demo', ['compte' => 'soukdemo-awa'])->assertNoContent();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('username', 'soukdemo-awa');
    }

    public function test_le_compte_admin_de_demo_reste_ferme_hors_du_poste_de_dev(): void
    {
        config(['app.demo_connexion' => true]);
        $this->seed(DemoSeeder::class);
        $this->app->detectEnvironment(fn () => 'staging');

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/auth/demo', ['compte' => 'soukdemo-admin'])
            ->assertNotFound();
    }

    public function test_la_connexion_de_demo_reste_fermee_en_production(): void
    {
        config(['app.demo_connexion' => true]);
        $this->seed(DemoSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        // Hors de l'environnement de test, le CSRF s'applique : on l'écarte pour atteindre la garde.
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/auth/demo', ['compte' => 'soukdemo-awa'])
            ->assertNotFound();
    }
}
