<?php

namespace Tests\Feature\Moteur;

use App\Events\DeploiementEtat;
use App\Jobs\ReactiverCopie;
use App\Jobs\SuspendreCopie;
use App\Models\Abonnement;
use App\Models\Deploiement;
use App\Moteur\DossierCopie;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use App\Moteur\MoteurDeploiement;
use App\Moteur\NettoyageCopies;
use App\Moteur\NettoyeurCopie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Mockery\MockInterface;
use Tests\TestCase;

/** Suspension pour impayé et réactivation : mêmes conteneurs, mêmes données, sur le moteur. */
class SuspensionCopieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([DeploiementEtat::class]);
    }

    public function test_la_suspension_arrete_les_conteneurs_sans_toucher_aux_volumes(): void
    {
        $commandes = [];
        Process::fake(function (PendingProcess $process) use (&$commandes) {
            $commandes[] = $process->command;

            return Process::result();
        });

        (new LanceurCompose)->suspendre('/tmp/inexistant', 'd7');
        (new LanceurCompose)->reprendre('/tmp/inexistant', 'd7');

        $this->assertSame([['docker', 'compose', '-p', 'd7', 'stop'], ['docker', 'compose', '-p', 'd7', 'start']], $commandes);
    }

    public function test_le_job_suspend_une_copie_impayee(): void
    {
        $abonnement = Abonnement::factory()->create(['statut' => 'suspendu', 'suspendu_le' => now()]);
        $deploiement = $abonnement->deploiement;
        $deploiement->forceFill(['copie' => 'd'.$deploiement->id, 'repertoire' => '/tmp/copie', 'url' => 'http://d'.$deploiement->id.'.localhost'])->save();
        $this->mock(MoteurDeploiement::class, fn (MockInterface $mock) => $mock->shouldReceive('suspendre')->once()->with('/tmp/copie', 'd'.$deploiement->id));

        (new SuspendreCopie($deploiement->id))->handle(app(MoteurDeploiement::class));

        $deploiement->refresh();
        $this->assertSame('suspendu', $deploiement->etat);
        $this->assertSame('http://d'.$deploiement->id.'.localhost', $deploiement->url, 'L\'adresse reste : elle affiche la page « suspendue ».');
    }

    public function test_une_copie_payee_entre_temps_n_est_pas_suspendue(): void
    {
        $abonnement = Abonnement::factory()->create(['statut' => 'actif']);
        $this->mock(MoteurDeploiement::class, fn (MockInterface $mock) => $mock->shouldNotReceive('suspendre'));

        (new SuspendreCopie($abonnement->deploiement_id))->handle(app(MoteurDeploiement::class));

        $this->assertSame('en_ligne', $abonnement->deploiement->refresh()->etat);
    }

    public function test_la_reactivation_remet_la_copie_en_ligne(): void
    {
        $abonnement = Abonnement::factory()->create(['statut' => 'actif']);
        $deploiement = $abonnement->deploiement;
        $deploiement->forceFill(['etat' => 'suspendu', 'copie' => 'd'.$deploiement->id, 'repertoire' => '/tmp/copie', 'url' => 'http://d'.$deploiement->id.'.localhost'])->save();
        $this->mock(MoteurDeploiement::class, fn (MockInterface $mock) => $mock->shouldReceive('reprendre')->once()->with('/tmp/copie', 'd'.$deploiement->id));

        (new ReactiverCopie($deploiement->id))->handle(app(MoteurDeploiement::class));

        $this->assertSame('en_ligne', $deploiement->refresh()->etat);
        $this->assertSame('http://d'.$deploiement->id.'.localhost', $deploiement->url);
    }

    public function test_une_reactivation_ratee_est_montree_au_client(): void
    {
        $abonnement = Abonnement::factory()->create(['statut' => 'actif']);
        $deploiement = $abonnement->deploiement;
        $deploiement->forceFill(['etat' => 'suspendu', 'copie' => 'd'.$deploiement->id, 'repertoire' => '/tmp/copie'])->save();

        (new ReactiverCopie($deploiement->id))->failed(new LancementEchoue('Le service web ne répond pas.'));

        $deploiement->refresh();
        $this->assertSame('echec', $deploiement->etat);
        $this->assertStringContainsString('support est prévenu', (string) $deploiement->erreur);
    }

    public function test_le_nettoyage_ne_prend_pas_une_copie_suspendue_pour_une_orpheline(): void
    {
        $racine = sys_get_temp_dir().'/soukdev-suspension-'.bin2hex(random_bytes(4));
        $deploiement = Deploiement::factory()->create(['etat' => 'suspendu']);
        mkdir($racine.'/d'.$deploiement->id, 0777, true);
        $this->app->instance(DossierCopie::class, new DossierCopie($racine));
        $this->mock(NettoyeurCopie::class, fn (MockInterface $mock) => $mock->shouldNotReceive('liberer'));

        try {
            $resultat = app(NettoyageCopies::class)->executer();
        } finally {
            exec('rm -rf '.escapeshellarg($racine));
        }

        $this->assertSame(0, $resultat['orphelins']);
    }
}
