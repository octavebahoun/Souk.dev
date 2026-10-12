<?php

namespace Tests\Feature;

use App\Jobs\MesurerMemoire;
use App\Models\Appli;
use App\Models\User;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\MesureMemoire;
use App\Moteur\MoteurDeploiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MesurerMemoireTest extends TestCase
{
    use RefreshDatabase;

    public function test_demander_mesure_remet_le_champ_a_en_cours_et_envoie_le_job(): void
    {
        Queue::fake();
        $appli = $this->appli();
        $appli->terminerMesure(100, 'petite');

        $appli->demanderMesure();
        $appli->refresh();

        $this->assertSame('en_cours', $appli->mesure_statut);
        $this->assertNull($appli->memoire_max_mo);
        $this->assertNull($appli->taille_recommandee);
        $this->assertNull($appli->mesure_le);
        $this->assertSame(1, $appli->mesure_jeton);
        $this->assertSame('petite', $appli->taille);
        $this->assertSame([
            'statut' => 'en_cours',
            'memoire_max_mo' => null,
            'taille_recommandee' => null,
            'mesure_le' => null,
        ], $appli->mesureVersApi());

        Queue::assertPushed(MesurerMemoire::class, function (MesurerMemoire $job) use ($appli) {
            return $job->appliId === $appli->id && $job->jeton === 1;
        });
    }

    public function test_le_job_ecrit_le_pic_et_recommande_la_moyenne_pour_450_mo(): void
    {
        $appli = $this->appli();
        $appli->forceFill(['mesure_statut' => 'en_cours', 'mesure_jeton' => 1])->save();
        $job = new MesurerMemoire($appli->id, 1, 'm1-abcd1234');

        $this->mock(MesureMemoire::class, function ($mock) use ($appli, $job) {
            $mock->shouldReceive('pic')
                ->once()
                ->with($appli->depot_url, $job->copie)
                ->andReturn(450);
        });

        app()->call([$job, 'handle']);

        $appli->refresh();
        $this->assertSame('terminee', $appli->mesure_statut);
        $this->assertSame(450, $appli->memoire_max_mo);
        $this->assertSame('moyenne', $appli->taille_recommandee);
        $this->assertNotNull($appli->mesure_le);
        $this->assertSame('petite', $appli->taille);
        $this->assertSame(450, $appli->mesureVersApi()['memoire_max_mo']);
    }

    public function test_un_echec_de_lancement_marque_la_mesure_en_echec(): void
    {
        $appli = $this->appli();
        $appli->forceFill(['mesure_statut' => 'en_cours', 'mesure_jeton' => 2])->save();
        $job = new MesurerMemoire($appli->id, 2, 'm1-abcd1234');

        $this->mock(MesureMemoire::class, function ($mock) {
            $mock->shouldReceive('pic')->once()->andThrow(new LancementEchoue('Le service web ne répond pas.'));
        });

        app()->call([$job, 'handle']);

        $appli->refresh();
        $this->assertSame('echec', $appli->mesure_statut);
        $this->assertNull($appli->memoire_max_mo);
        $this->assertNull($appli->taille_recommandee);
        $this->assertNotNull($appli->mesure_le);
    }

    public function test_un_job_depasse_ne_recouvre_pas_la_mesure_en_cours(): void
    {
        $appli = $this->appli();
        $appli->forceFill(['mesure_statut' => 'en_cours', 'mesure_jeton' => 4])->save();
        $job = new MesurerMemoire($appli->id, 3, 'm1-ancien');

        $this->mock(MesureMemoire::class, function ($mock) {
            $mock->shouldNotReceive('pic');
        });

        app()->call([$job, 'handle']);
        $job->failed(new LancementEchoue('trop tard'));

        $appli->refresh();
        $this->assertSame('en_cours', $appli->mesure_statut);
        $this->assertNull($appli->mesure_le);
    }

    public function test_le_timeout_marque_la_mesure_en_echec(): void
    {
        $appli = $this->appli();
        $appli->forceFill(['mesure_statut' => 'en_cours', 'mesure_jeton' => 1])->save();

        (new MesurerMemoire($appli->id, 1, 'm1-abcd1234'))->failed(null);

        $this->assertSame('echec', $appli->refresh()->mesure_statut);
    }

    public function test_une_seule_copie_tourne_a_la_fois_pour_l_appli(): void
    {
        $middleware = (new MesurerMemoire(7, 1, 'm7-abcd1234'))->middleware();

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame(1200, $middleware[0]->expiresAfter);
        $this->assertSame(30, $middleware[0]->releaseAfter);
    }

    public function test_la_copie_de_mesure_est_lancee_en_grande_puis_coupee(): void
    {
        $copies = sys_get_temp_dir().'/soukdev-copies-'.bin2hex(random_bytes(4));
        mkdir($copies);
        config([
            'moteur.copies' => $copies,
            'moteur.mesure_duree' => 0,
            'moteur.mesure_intervalle' => 5,
        ]);
        $coupe = false;

        try {
            Process::fake(function (PendingProcess $process) use (&$coupe) {
                $commande = $process->command;

                if (($commande[0] ?? '') === 'git') {
                    $destination = $commande[array_key_last($commande)];
                    File::ensureDirectoryExists($destination);
                    file_put_contents($destination.DIRECTORY_SEPARATOR.'soukdev.json', json_encode($this->manifeste(), JSON_THROW_ON_ERROR));

                    return Process::result();
                }

                if (in_array('down', $commande, true)) {
                    $coupe = true;

                    return Process::result();
                }

                if (in_array('ps', $commande, true)) {
                    return Process::result(output: "abc123def4567890\n");
                }

                if (in_array('stats', $commande, true)) {
                    return Process::result(output: "450MiB / 2GiB\n");
                }

                return Process::result(errorOutput: 'inattendu', exitCode: 1);
            });

            $copieLocale = new CopieLocale('m1-abcd1234', $copies, ['service_web' => ['nom' => 'front', 'port' => 3000]], [], false);
            $this->mock(MoteurDeploiement::class, function ($mock) use ($copieLocale) {
                $mock->shouldReceive('preparer')
                    ->once()
                    ->withArgs(function (string $repertoire, array $variables, string $copie, bool $dryRun, string $taille) {
                        return $variables === ['APP_NOM' => 'soukdev']
                            && $copie === 'm1-abcd1234'
                            && $dryRun === true
                            && $taille === 'grande'
                            && is_file($repertoire.DIRECTORY_SEPARATOR.'soukdev.json');
                    })
                    ->andReturn($copieLocale);
                $mock->shouldReceive('mettreEnLigne')
                    ->once()
                    ->with($copieLocale, 'grande')
                    ->andReturn($copieLocale);
            });

            $pic = app(MesureMemoire::class)->pic('https://github.com/acme/cobaye', 'm1-abcd1234');

            $this->assertSame(450, $pic);
            $this->assertTrue($coupe);
            $this->assertDirectoryDoesNotExist($copies.DIRECTORY_SEPARATOR.'m1-abcd1234');
        } finally {
            File::deleteDirectory($copies);
        }
    }

    public function test_un_clone_impossible_n_arrete_pas_de_copie(): void
    {
        $copies = sys_get_temp_dir().'/soukdev-copies-'.bin2hex(random_bytes(4));
        mkdir($copies);
        config(['moteur.copies' => $copies]);
        $coupe = false;

        try {
            Process::fake(function (PendingProcess $process) use (&$coupe) {
                if (in_array('down', $process->command, true)) {
                    $coupe = true;
                }

                return Process::result(errorOutput: 'depot prive', exitCode: 128);
            });

            $this->mock(MoteurDeploiement::class, function ($mock) {
                $mock->shouldNotReceive('preparer');
                $mock->shouldNotReceive('mettreEnLigne');
            });

            try {
                app(MesureMemoire::class)->pic('https://github.com/acme/cobaye', 'm1-abcd1234');
                $this->fail('Un clone refusé aurait dû lever une exception.');
            } catch (DepotInvalide) {
                $this->assertFalse($coupe);
            }
        } finally {
            File::deleteDirectory($copies);
        }
    }

    public function test_la_copie_est_coupee_si_le_demarrage_echoue(): void
    {
        $copies = sys_get_temp_dir().'/soukdev-copies-'.bin2hex(random_bytes(4));
        mkdir($copies);
        config(['moteur.copies' => $copies, 'moteur.mesure_duree' => 0]);
        $coupe = false;

        try {
            Process::fake(function (PendingProcess $process) use (&$coupe) {
                $commande = $process->command;

                if (($commande[0] ?? '') === 'git') {
                    $destination = $commande[array_key_last($commande)];
                    File::ensureDirectoryExists($destination);
                    file_put_contents($destination.DIRECTORY_SEPARATOR.'soukdev.json', json_encode($this->manifeste(), JSON_THROW_ON_ERROR));

                    return Process::result();
                }

                if (in_array('down', $commande, true)) {
                    $coupe = true;

                    return Process::result();
                }

                return Process::result();
            });

            $this->mock(MoteurDeploiement::class, function ($mock) {
                $mock->shouldReceive('preparer')->once()->andReturn(new CopieLocale(
                    'm1-abcd1234',
                    '/tmp/copie',
                    ['service_web' => ['nom' => 'front', 'port' => 3000]],
                    [],
                    false,
                ));
                $mock->shouldReceive('mettreEnLigne')->once()->andThrow(new LancementEchoue('Le service web ne répond pas.'));
            });

            try {
                app(MesureMemoire::class)->pic('https://github.com/acme/cobaye', 'm1-abcd1234');
                $this->fail('Un démarrage refusé aurait dû lever une exception.');
            } catch (LancementEchoue) {
                $this->assertTrue($coupe);
                $this->assertDirectoryDoesNotExist($copies.DIRECTORY_SEPARATOR.'m1-abcd1234');
            }
        } finally {
            File::deleteDirectory($copies);
        }
    }

    private function appli(): Appli
    {
        return Appli::query()->create([
            'user_id' => User::factory()->create()->id,
            'nom' => 'Cobaye',
            'depot_url' => 'https://github.com/acme/cobaye',
            'taille' => 'petite',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifeste(): array
    {
        return [
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'APP_VILLE', 'source' => 'client', 'libelle' => 'Ville', 'requis' => false],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ];
    }
}
