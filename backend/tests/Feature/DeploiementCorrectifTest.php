<?php

namespace Tests\Feature;

use App\Events\CopieTestEtat;
use App\Events\CorrectifEtat;
use App\Events\DeploiementEtat;
use App\Jobs\LancerDeploiement;
use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploiementCorrectifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Event::fake([DeploiementEtat::class, CorrectifEtat::class, CopieTestEtat::class]);
    }

    private function correctif(User $dev, ?Discussion $discussion = null): Correctif
    {
        $discussion ??= Discussion::factory()->create();

        return $discussion->correctifs()->create([
            'user_id' => $dev->id,
            'depot_url' => 'https://github.com/wasfade/momo/tree/correctif',
            'accepte' => false,
        ]);
    }

    public function test_l_auteur_du_correctif_deploie_sa_version_corrigee(): void
    {
        $dev = User::factory()->create(['github_lie' => true]);
        $correctif = $this->correctif($dev);

        $reponse = $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement");

        $reponse->assertStatus(202)
            ->assertJsonPath('type', 'correctif')
            ->assertJsonPath('etat', 'en_file')
            ->assertJsonPath('app_id', null)
            ->assertJsonPath('app_nom', 'wasfade/momo')
            ->assertJsonPath('taille', 'petite');

        $deploiement = Deploiement::query()->sole();
        $this->assertSame($correctif->id, $deploiement->correctif_id);
        $this->assertSame($correctif->discussion_id, $deploiement->discussion_id);
        $this->assertSame('https://github.com/wasfade/momo/tree/correctif', $deploiement->depotACloner());
        $this->assertNotNull($deploiement->expire_le);

        Queue::assertPushed(LancerDeploiement::class, fn (LancerDeploiement $job) => $job->deploiementId === $deploiement->id);
        Event::assertDispatched(CorrectifEtat::class, fn (CorrectifEtat $evenement) => $evenement->broadcastOn()[0]->name === 'discussion.'.$correctif->discussion_id
            && $evenement->broadcastWith()['deploiement']['id'] === $deploiement->id);

        $this->getJson("/api/discussions/{$correctif->discussion_id}/correctifs")
            ->assertJsonPath('data.0.deploiement.id', $deploiement->id);
    }

    public function test_la_formule_de_l_appli_jointe_est_reprise(): void
    {
        $dev = User::factory()->create(['github_lie' => true]);
        $appli = Appli::query()->forceCreate([
            'user_id' => User::factory()->create()->id,
            'nom' => 'Cobaye',
            'depot_url' => 'https://github.com/MourchidFOLARIN/cobaye',
            'taille' => 'moyenne',
        ]);
        $correctif = $this->correctif($dev, Discussion::factory()->create(['app_id' => $appli->id]));

        $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")
            ->assertStatus(202)
            ->assertJsonPath('app_id', null)
            ->assertJsonPath('taille', 'moyenne');

        $this->assertSame('https://github.com/wasfade/momo/tree/correctif', Deploiement::query()->sole()->depotACloner());
    }

    public function test_un_autre_compte_recoit_403_et_rien_ne_part(): void
    {
        $correctif = $this->correctif(User::factory()->create(['github_lie' => true]));
        $auteurDiscussion = User::query()->findOrFail($correctif->discussion->user_id);

        $this->actingAs($auteurDiscussion)
            ->postJson("/api/correctifs/{$correctif->id}/deploiement")
            ->assertForbidden();

        $this->assertSame(0, Deploiement::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_sans_session_401_et_correctif_inconnu_404(): void
    {
        $this->postJson('/api/correctifs/999/deploiement')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/correctifs/999/deploiement')
            ->assertNotFound();
    }

    public function test_un_deuxieme_deploiement_actif_409(): void
    {
        $dev = User::factory()->create(['github_lie' => true]);
        $correctif = $this->correctif($dev);

        $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")->assertStatus(202);
        $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")->assertStatus(409);

        Deploiement::query()->update(['etat' => 'echec']);
        $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")->assertStatus(202);
    }

    public function test_au_dela_de_3_par_heure_429(): void
    {
        $dev = User::factory()->create(['github_lie' => true]);

        foreach (range(1, 3) as $essai) {
            $correctif = $this->correctif($dev);
            $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")->assertStatus(202);
        }

        $correctif = $this->correctif($dev);
        $this->actingAs($dev)->postJson("/api/correctifs/{$correctif->id}/deploiement")->assertStatus(429);
    }

    public function test_l_etat_de_la_copie_de_test_part_sur_la_discussion(): void
    {
        $auteur = User::factory()->create();
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id, 'app_id' => null, 'depot_url' => 'https://github.com/wasfade/momo']);

        $this->actingAs($auteur)->postJson("/api/discussions/{$discussion->id}/copie-test")->assertStatus(202);

        Deploiement::query()->sole()->changerEtat('en_ligne', url: 'https://d1.souk.dev');

        Event::assertDispatched(CopieTestEtat::class, fn (CopieTestEtat $evenement) => $evenement->broadcastAs() === 'copie_test.etat'
            && $evenement->broadcastOn()[0]->name === 'discussion.'.$discussion->id
            && $evenement->payload['etat'] === 'en_ligne');
        Event::assertNotDispatched(CorrectifEtat::class);
    }

    public function test_un_deploiement_client_ne_diffuse_rien_sur_les_discussions(): void
    {
        $appli = Appli::query()->forceCreate([
            'user_id' => User::factory()->create()->id,
            'nom' => 'Cobaye',
            'depot_url' => 'https://github.com/MourchidFOLARIN/cobaye',
            'taille' => 'petite',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/apps/{$appli->id}/deployments", ['variables' => (object) []])
            ->assertStatus(202);

        Event::assertDispatched(DeploiementEtat::class);
        Event::assertNotDispatched(CopieTestEtat::class);
        Event::assertNotDispatched(CorrectifEtat::class);
    }
}
