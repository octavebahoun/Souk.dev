<?php

namespace Tests\Feature;

use App\Jobs\LancerDeploiement;
use App\Jobs\SonderMachine;
use App\Models\Deploiement;
use App\Models\Machine;
use App\Models\User;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use App\Moteur\MachineCible;
use App\Moteur\MachineDocker;
use App\Moteur\MoteurDeploiement;
use App\Moteur\PlacementMachines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plusieurs serveurs (phase 5) : liste des machines, placement sur la plus libre, mise en
 * maintenance (sans nouvelle copie), état et santé sondés sur le moteur. Réservé à l'équipe.
 */
class MachinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_admin_liste_les_machines_avec_leur_etat(): void
    {
        Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375', 'capacite_mo' => 8192]);

        $reponse = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/admin/machines')
            ->assertOk();

        $noms = collect($reponse->json('data'))->pluck('nom')->all();
        $this->assertContains('locale', $noms);
        $this->assertContains('aux', $noms);
        $reponse->assertJsonPath('data.0.memoire_reservee_mo', 0);
    }

    public function test_l_etat_compte_la_memoire_des_copies_qui_tournent(): void
    {
        $machine = Machine::factory()->create(['nom' => 'aux', 'capacite_mo' => 2048]);
        Deploiement::factory()->create(['machine_id' => $machine->id, 'taille' => 'grande', 'etat' => 'en_ligne']);
        Deploiement::factory()->create(['machine_id' => $machine->id, 'taille' => 'petite', 'etat' => 'arrete']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/admin/machines/'.$machine->id)
            ->assertOk()
            ->assertJsonPath('memoire_reservee_mo', 2048)
            ->assertJsonPath('memoire_libre_mo', 0)
            ->assertJsonPath('copies', 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function routesMachines(): array
    {
        return [
            'liste' => ['GET', '/api/admin/machines'],
            'création' => ['POST', '/api/admin/machines'],
            'lecture' => ['GET', '/api/admin/machines/1'],
            'modification' => ['PATCH', '/api/admin/machines/1'],
            'suppression' => ['DELETE', '/api/admin/machines/1'],
            'sonde' => ['POST', '/api/admin/machines/1/sonde'],
        ];
    }

    #[DataProvider('routesMachines')]
    public function test_un_compte_ordinaire_est_refuse_sans_rien_reveler(string $methode, string $uri): void
    {
        Queue::fake();

        // Invité d'abord : actingAs persiste pour le reste du test.
        $this->getJson('/api/admin/machines')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->json($methode, $uri)
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_le_controle_admin_passe_avant_le_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/machines/999999')
            ->assertForbidden();
    }

    public function test_l_admin_enregistre_une_machine_puis_la_sonde(): void
    {
        Queue::fake();

        $id = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/admin/machines', [
                'nom' => 'aux',
                'hote_docker' => 'tcp://10.0.0.2:2375',
                'capacite_mo' => 8192,
            ])
            ->assertCreated()
            ->assertJsonPath('nom', 'aux')
            ->assertJsonPath('etat', 'active')
            ->json('id');

        $this->assertDatabaseHas('machines', ['id' => $id, 'hote_docker' => 'tcp://10.0.0.2:2375']);
        // Sur sa propre file : une sonde n'attend pas derrière une construction de copie.
        Queue::assertPushed(SonderMachine::class, fn (SonderMachine $job) => $job->machineId === $id && $job->queue === 'sondes');
    }

    public function test_l_enregistrement_refuse_un_hote_ou_un_nom_invalide(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['http://10.0.0.2:2375', 'tcp://10.0.0.2', 'ssh://root@10.0.0.2', 'tcp://10.0.0.2:2375; rm -rf /'] as $hote) {
            $this->actingAs($admin)
                ->postJson('/api/admin/machines', ['nom' => 'aux', 'hote_docker' => $hote])
                ->assertUnprocessable()
                ->assertJsonPath('errors.hote_docker.0', 'Le démon Docker doit être unix:///… ou tcp://hôte:port.');
        }

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'locale'])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'aux', 'capacite_mo' => 100])
            ->assertUnprocessable();
    }

    public function test_l_admin_bascule_une_machine_en_maintenance(): void
    {
        $machine = Machine::factory()->create(['nom' => 'aux']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/api/admin/machines/'.$machine->id, ['etat' => 'maintenance', 'capacite_mo' => 4096])
            ->assertOk()
            ->assertJsonPath('etat', 'maintenance')
            ->assertJsonPath('capacite_mo', 4096);

        $this->assertSame('maintenance', $machine->refresh()->etat);
    }

    public function test_le_demon_ne_change_pas_tant_que_des_copies_tournent(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375']);
        $copie = Deploiement::factory()->create(['machine_id' => $machine->id, 'etat' => 'en_ligne']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/machines/'.$machine->id, ['hote_docker' => 'tcp://10.0.0.3:2375'])
            ->assertConflict();

        $this->assertSame('tcp://10.0.0.2:2375', $machine->refresh()->hote_docker);

        $copie->forceFill(['etat' => 'arrete'])->save();

        $this->actingAs($admin)
            ->patchJson('/api/admin/machines/'.$machine->id, ['hote_docker' => 'tcp://10.0.0.3:2375'])
            ->assertOk();
    }

    public function test_la_suppression_est_refusee_tant_que_des_copies_tournent(): void
    {
        $admin = User::factory()->admin()->create();
        $machine = Machine::factory()->create(['nom' => 'aux']);
        $copie = Deploiement::factory()->create(['machine_id' => $machine->id, 'etat' => 'en_ligne']);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/machines/'.$machine->id)
            ->assertConflict();

        $copie->forceFill(['etat' => 'arrete'])->save();

        $this->actingAs($admin)
            ->deleteJson('/api/admin/machines/'.$machine->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);

        $this->actingAs($admin)->deleteJson('/api/admin/machines/999999')->assertNotFound();
    }

    public function test_la_sonde_se_relance_a_la_demande(): void
    {
        Queue::fake();
        $machine = Machine::factory()->create(['nom' => 'aux']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/admin/machines/'.$machine->id.'/sonde')
            ->assertAccepted()
            ->assertJsonPath('id', $machine->id);

        Queue::assertPushed(SonderMachine::class, fn (SonderMachine $job) => $job->machineId === $machine->id);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/admin/machines/999999/sonde')
            ->assertNotFound();
    }

    public function test_la_sonde_lit_la_memoire_du_demon(): void
    {
        Process::fake(fn () => Process::result((string) json_encode([
            'MemTotal' => 17_179_869_184, 'ServerVersion' => '28.0.1',
        ])));
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375']);

        (new SonderMachine($machine->id))->handle();

        $machine->refresh();
        $this->assertTrue($machine->joignable);
        $this->assertSame(16_384, $machine->memoire_totale_mo);
        $this->assertSame('28.0.1', $machine->version_docker);
        $this->assertNull($machine->sonde_erreur);
        $this->assertNotNull($machine->sondee_le);
    }

    public function test_un_demon_injoignable_est_marquee_hors_service(): void
    {
        Process::fake(fn () => Process::result('', 'Cannot connect to the Docker daemon', 1));
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375']);

        (new SonderMachine($machine->id))->handle();

        $machine->refresh();
        $this->assertFalse($machine->joignable);
        $this->assertNotNull($machine->sonde_erreur);
        $this->assertNotNull($machine->sondee_le);
    }

    public function test_la_cause_d_une_sonde_ratee_est_dite_en_francais_sans_le_json_du_client(): void
    {
        Process::fake(fn () => Process::result('{"ID":"","Containers":0}', 'failed to connect to the docker API at unix:///run/dind/docker.sock; check if the path is correct and if the daemon is running: dial unix /run/dind/docker.sock: connect: permission denied', 1));
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'unix:///run/dind/docker.sock']);

        (new SonderMachine($machine->id))->handle();

        $this->assertSame("Le démon Docker ne répond pas\u{00A0}: accès refusé au socket du démon.", $machine->refresh()->sonde_erreur);
        $this->assertSame('Le démon Docker ne répond pas.', MachineDocker::cause(''));
    }

    public function test_une_sonde_ciblee_ratee_se_relance_deux_fois_puis_attend_le_passage_planifie(): void
    {
        Queue::fake();
        Process::fake(fn () => Process::result('', 'dial unix /run/dind/docker.sock: connect: connection refused', 1));
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'unix:///run/dind/docker.sock']);

        (new SonderMachine($machine->id))->handle();
        Queue::assertPushed(SonderMachine::class, fn (SonderMachine $job) => $job->machineId === $machine->id && $job->essai === 2 && $job->delay !== null);

        (new SonderMachine($machine->id, SonderMachine::ESSAIS))->handle();
        Queue::assertPushed(SonderMachine::class, 1);

        // Le passage planifié (toutes les machines) ne se relance pas : il revient dans cinq minutes.
        (new SonderMachine)->handle();
        Queue::assertPushed(SonderMachine::class, 1);
    }

    public function test_le_placement_choisit_la_machine_la_plus_libre(): void
    {
        $locale = Machine::where('nom', 'locale')->firstOrFail();
        $locale->forceFill(['capacite_mo' => 2048])->save();
        Deploiement::factory()->create(['machine_id' => $locale->id, 'taille' => 'grande', 'etat' => 'en_ligne']);
        $aux = Machine::factory()->create(['nom' => 'aux', 'capacite_mo' => 8192]);
        Machine::factory()->create(['nom' => 'vieille', 'etat' => 'maintenance', 'capacite_mo' => 65_536]);
        Machine::factory()->create(['nom' => 'panne', 'joignable' => false, 'capacite_mo' => 65_536]);

        // La machine locale n'a plus un mégaoctet de libre : la copie part sur « aux ».
        $this->assertSame($aux->id, app(PlacementMachines::class)->choisir('petite')->id);

        // Une fois pleine à son tour, plus aucune machine ne convient.
        Deploiement::factory()->create(['machine_id' => $aux->id, 'taille' => 'grande', 'etat' => 'en_ligne']);
        $aux->forceFill(['capacite_mo' => 2048])->save();

        $this->expectException(LancementEchoue::class);
        app(PlacementMachines::class)->choisir('petite');
    }

    public function test_l_enregistrement_refuse_un_demon_deja_enregistre(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $aux = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375']);

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'bis', 'hote_docker' => 'tcp://10.0.0.2:2375'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hote_docker.0', 'Ce démon est déjà enregistré sur un autre serveur.');

        $autre = Machine::factory()->create(['nom' => 'autre', 'hote_docker' => 'tcp://10.0.0.3:2375']);

        // Reprendre le démon d'un autre serveur : 422, pas une erreur 500 de base.
        $this->actingAs($admin)
            ->patchJson('/api/admin/machines/'.$autre->id, ['hote_docker' => 'tcp://10.0.0.2:2375'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hote_docker.0', 'Ce démon est déjà enregistré sur un autre serveur.');

        // Garder son propre démon en modifiant autre chose reste accepté.
        $this->actingAs($admin)
            ->patchJson('/api/admin/machines/'.$aux->id, ['hote_docker' => 'tcp://10.0.0.2:2375', 'capacite_mo' => 4096])
            ->assertOk();
    }

    public function test_un_demon_distant_sans_tls_est_refuse_en_production(): void
    {
        Queue::fake();
        $this->app->detectEnvironment(fn () => 'production');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'distant', 'hote_docker' => 'tcp://10.0.0.2:2375'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hote_docker.0', 'En production, un démon distant exige TLS : seuls le démon local et ceux du poste de développement sont acceptés.');

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'dev', 'hote_docker' => 'tcp://host.docker.internal:2376'])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson('/api/admin/machines', ['nom' => 'dev-local'])
            ->assertCreated();
    }

    public function test_le_demon_local_veut_dire_le_socket_ou_cette_machine(): void
    {
        $this->assertTrue(MachineDocker::hoteLocal(null));
        $this->assertTrue(MachineDocker::hoteLocal('unix:///var/run/docker.sock'));
        $this->assertTrue(MachineDocker::hoteLocal('tcp://localhost:2375'));
        $this->assertTrue(MachineDocker::hoteLocal('tcp://127.0.0.1:2375'));
        $this->assertTrue(MachineDocker::hoteLocal('tcp://host.docker.internal:2376'));
        $this->assertFalse(MachineDocker::hoteLocal('tcp://10.0.0.2:2375'));
        $this->assertFalse(MachineDocker::hoteLocal('tcp://docker.exemple.com:2376'));
    }

    public function test_le_placement_reserve_la_machine_dans_la_meme_transaction(): void
    {
        $locale = Machine::where('nom', 'locale')->firstOrFail();
        $locale->forceFill(['capacite_mo' => 512])->save();
        $premiere = Deploiement::factory()->create(['taille' => 'petite', 'etat' => 'en_file']);
        $seconde = Deploiement::factory()->create(['taille' => 'petite', 'etat' => 'en_file']);

        $this->assertSame($locale->id, app(PlacementMachines::class)->choisir('petite', $premiere)->id);
        $this->assertSame($locale->id, $premiere->refresh()->machine_id);

        // La place est prise : le placement suivant le voit et refuse, au lieu d'élire pareil.
        try {
            app(PlacementMachines::class)->choisir('petite', $seconde);
            $this->fail('Le second placement devait refuser : la machine est pleine.');
        } catch (LancementEchoue) {
            $this->assertNull($seconde->refresh()->machine_id);
        }
    }

    public function test_le_libre_repose_sur_la_memoire_sondee_sans_limite_declaree(): void
    {
        $machine = Machine::factory()->create(['nom' => 'aux', 'capacite_mo' => null]);
        $machine->forceFill(['memoire_totale_mo' => 8192])->save();
        Deploiement::factory()->create(['machine_id' => $machine->id, 'taille' => 'grande', 'etat' => 'en_ligne']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/admin/machines/'.$machine->id)
            ->assertOk()
            ->assertJsonPath('memoire_libre_mo', 6144);
    }

    public function test_le_lancement_enregistre_la_machine_la_plus_libre(): void
    {
        $aux = Machine::factory()->create(['nom' => 'aux', 'capacite_mo' => 8192]);
        Machine::where('nom', 'locale')->firstOrFail()->forceFill(['capacite_mo' => 512])->save();
        Deploiement::factory()->create([
            'machine_id' => Machine::where('nom', 'locale')->firstOrFail()->id,
            'taille' => 'petite',
            'etat' => 'en_ligne',
        ]);

        $client = User::factory()->create();
        $deploiement = Deploiement::factory()->create([
            'user_id' => $client->id, 'type' => 'test', 'taille' => 'petite', 'etat' => 'en_file',
        ]);
        $copie = new CopieLocale('d'.$deploiement->id, '/tmp/copie', ['service_web' => ['nom' => 'web', 'port' => 80]], [], true);

        $this->mock(MoteurDeploiement::class, function ($mock) use ($copie): void {
            $mock->shouldReceive('depuisGithub')->once()->andReturn($copie);
            $mock->shouldReceive('mettreEnLigne')->once()->andReturn($copie);
        });

        app()->call([new LancerDeploiement($deploiement->id), 'handle']);

        $this->assertSame($aux->id, $deploiement->refresh()->machine_id);
        $this->actingAs($client)
            ->getJson('/api/deployments/'.$deploiement->id)
            ->assertOk()
            ->assertJsonPath('machine_nom', 'aux');
    }

    public function test_l_arret_passe_par_le_demon_de_la_copie(): void
    {
        $machine = Machine::factory()->create(['nom' => 'aux', 'hote_docker' => 'tcp://10.0.0.2:2375']);
        $client = User::factory()->create();
        $deploiement = Deploiement::factory()->create([
            'user_id' => $client->id, 'machine_id' => $machine->id, 'type' => 'test',
            'etat' => 'en_ligne', 'copie' => 'd999', 'repertoire' => '/tmp/copie',
        ]);
        $deploiement->forceFill(['copie' => 'd'.$deploiement->id])->save();

        $this->mock(LanceurCompose::class, function ($mock) use ($deploiement): void {
            $mock->shouldReceive('arreter')->once()->with(
                '/tmp/copie',
                'd'.$deploiement->id,
                Mockery::on(fn (mixed $cible) => $cible instanceof MachineCible
                    && $cible->environnement() === ['DOCKER_HOST' => 'tcp://10.0.0.2:2375']),
            );
        });

        $this->actingAs($client)
            ->deleteJson('/api/deployments/'.$deploiement->id)
            ->assertAccepted();

        $this->assertSame('arrete', $deploiement->refresh()->etat);
    }

    public function test_la_cible_docker_vise_son_demon_sans_fuite(): void
    {
        $this->assertSame([], (new MachineDocker(null))->environnement());
        $this->assertSame(
            ['DOCKER_HOST' => 'tcp://10.0.0.2:2375'],
            (new MachineDocker('tcp://10.0.0.2:2375'))->environnement(),
        );

        $this->expectException(\InvalidArgumentException::class);
        new MachineDocker('http://10.0.0.2:2375');
    }
}
