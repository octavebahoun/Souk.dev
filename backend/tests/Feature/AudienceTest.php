<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AudienceTest extends TestCase
{
    use RefreshDatabase;

    private const NAVIGATEUR = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function signaler(array $donnees, string $navigateur = self::NAVIGATEUR, string $ip = '41.85.160.10'): TestResponse
    {
        return $this->call('POST', '/api/visites', [], [], [], [
            'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
            'HTTP_USER_AGENT' => $navigateur,
            'REMOTE_ADDR' => $ip,
        ], (string) json_encode($donnees));
    }

    public function test_une_page_vue_est_comptee_sans_donnee_personnelle(): void
    {
        $this->signaler([
            'chemin' => '/store/12?jeton=secret#avis',
            'referent' => 'https://www.facebook.com/groups/devs-benin',
            'source' => 'WhatsApp',
            'fuseau' => 'Africa/Porto-Novo',
            'langue' => 'fr-FR',
            'largeur' => 390,
        ])->assertNoContent();

        $visite = Visite::query()->sole();
        $this->assertSame('/store/12', $visite->chemin, 'Les paramètres d’adresse (un jeton, un e-mail) ne sont jamais gardés.');
        $this->assertSame('facebook.com', $visite->referent);
        $this->assertSame('whatsapp', $visite->source);
        $this->assertSame('BJ', $visite->pays);
        $this->assertSame('mobile', $visite->appareil);
        $this->assertSame(64, strlen($visite->visiteur));
        $this->assertStringNotContainsString('41.85.160.10', json_encode(Visite::query()->first()->getAttributes()));
    }

    public function test_un_meme_visiteur_compte_une_fois_par_jour_et_change_d_empreinte_le_lendemain(): void
    {
        $this->signaler(['chemin' => '/']);
        $this->signaler(['chemin' => '/echange']);
        $this->travel(1)->day();
        $this->signaler(['chemin' => '/']);

        $this->assertSame(3, Visite::query()->count());
        $this->assertSame(2, Visite::query()->distinct()->count('visiteur'));
    }

    public function test_les_robots_l_equipe_et_l_administration_ne_sont_pas_comptes(): void
    {
        $this->signaler(['chemin' => '/'], 'Mozilla/5.0 (compatible; Googlebot/2.1)')->assertNoContent();
        $this->signaler(['chemin' => '/'], 'Mozilla/5.0 HeadlessChrome/130.0')->assertNoContent();
        $this->signaler(['chemin' => '/admin/finances'])->assertNoContent();
        $this->signaler(['chemin' => 'https://ailleurs.example/'])->assertNoContent();

        $admin = User::factory()->create();
        $admin->forceFill(['est_admin' => true])->save();
        $this->actingAs($admin);
        $this->signaler(['chemin' => '/store'])->assertNoContent();

        $this->assertSame(0, Visite::query()->count());
    }

    public function test_seule_l_equipe_lit_le_resume(): void
    {
        $this->signaler(['chemin' => '/', 'fuseau' => 'Africa/Dakar', 'largeur' => 1440, 'referent' => 'https://www.linkedin.com/feed/']);
        $this->signaler(['chemin' => '/store', 'fuseau' => 'Africa/Dakar', 'largeur' => 1440]);
        $this->signaler(['chemin' => '/', 'fuseau' => 'Africa/Lome', 'largeur' => 390], self::NAVIGATEUR, '41.207.1.1');

        $this->getJson('/api/admin/visites')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/visites')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['est_admin' => true])->save();

        $this->actingAs($admin)->getJson('/api/admin/visites?jours=7')
            ->assertOk()
            ->assertJsonPath('jours', 7)
            ->assertJsonCount(7, 'par_jour')
            ->assertJsonPath('aujourdhui.visiteurs', 2)
            ->assertJsonPath('aujourdhui.pages', 3)
            ->assertJsonPath('pages.0.nom', '/')
            ->assertJsonPath('pages.0.visiteurs', 2)
            ->assertJsonPath('sources.0.nom', 'linkedin.com')
            ->assertJsonPath('pays.0.nom', 'SN')
            ->assertJsonPath('appareils.0.nom', 'ordinateur');

        $this->actingAs($admin)->getJson('/api/admin/visites?jours=12')->assertUnprocessable();
    }
}
