<?php

namespace Tests\Feature;

use App\Events\DeploiementEtat;
use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CopieTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Event::fake([DeploiementEtat::class]);
    }

    public function test_l_auteur_lance_une_copie_de_test_depuis_le_depot_joint(): void
    {
        $auteur = User::factory()->create();
        $discussion = Discussion::factory()->create([
            'user_id' => $auteur->id,
            'app_id' => null,
            'depot_url' => 'https://github.com/wasfade/momo',
        ]);

        $this->travelTo(now()->startOfMinute());

        $reponse = $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test");

        $reponse->assertStatus(202)
            ->assertJsonPath('type', 'test')
            ->assertJsonPath('etat', 'en_file')
            ->assertJsonPath('app_id', null)
            ->assertJsonPath('app_nom', 'wasfade/momo')
            ->assertJsonPath('taille', 'petite');

        $deploiement = Deploiement::query()->sole();
        $this->assertSame($discussion->id, $deploiement->discussion_id);
        $this->assertSame('https://github.com/wasfade/momo', $deploiement->depotACloner());
        $this->assertTrue($deploiement->expire_le->equalTo(now()->addMinutes(config('moteur.copie_test_minutes'))));

        Queue::assertPushed(LancerDeploiement::class, fn (LancerDeploiement $job) => $job->deploiementId === $deploiement->id);
        Event::assertDispatched(DeploiementEtat::class);

        $this->getJson("/api/discussions/{$discussion->id}")
            ->assertJsonPath('data.copie_test.id', $deploiement->id);
    }

    public function test_l_appli_jointe_donne_son_depot_et_sa_formule(): void
    {
        $auteur = User::factory()->create();
        $appli = Appli::query()->forceCreate([
            'user_id' => $auteur->id,
            'nom' => 'Cobaye',
            'depot_url' => 'https://github.com/MourchidFOLARIN/cobaye',
            'taille' => 'moyenne',
        ]);
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => $appli->id, 'depot_url' => null]);

        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")
            ->assertStatus(202)
            ->assertJsonPath('app_id', $appli->id)
            ->assertJsonPath('taille', 'moyenne');
    }

    public function test_un_autre_compte_recoit_403_et_rien_ne_part(): void
    {
        $discussion = Discussion::factory()->create(['depot_url' => 'https://github.com/wasfade/momo']);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/discussions/{$discussion->id}/copie-test")
            ->assertForbidden();

        $this->assertSame(0, Deploiement::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_sans_session_401_et_discussion_inconnue_404(): void
    {
        $this->postJson('/api/discussions/999/copie-test')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/discussions/999/copie-test')
            ->assertNotFound();
    }

    public function test_sans_depot_ni_appli_422(): void
    {
        $auteur = User::factory()->create();
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => null, 'depot_url' => null]);

        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('depot_url');
    }

    public function test_une_deuxieme_copie_active_409(): void
    {
        $auteur = User::factory()->create();
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => null, 'depot_url' => 'https://github.com/wasfade/momo']);

        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(202);
        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(409);

        Deploiement::query()->update(['etat' => 'arrete']);
        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(202);
    }

    public function test_au_dela_de_3_par_heure_429(): void
    {
        $auteur = User::factory()->create();

        foreach (range(1, 3) as $essai) {
            $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => null, 'depot_url' => 'https://github.com/wasfade/momo']);
            $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(202);
        }

        $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => null, 'depot_url' => 'https://github.com/wasfade/momo']);
        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(429);
    }

    public function test_creer_une_discussion_avec_depot_ne_lance_rien(): void
    {
        Discussion::factory()->create(['depot_url' => 'https://github.com/wasfade/momo']);

        $this->assertSame(0, Deploiement::query()->count());
        Queue::assertNothingPushed();
    }
}
