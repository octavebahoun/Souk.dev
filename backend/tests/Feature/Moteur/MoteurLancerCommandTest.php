<?php

namespace Tests\Feature\Moteur;

use App\Moteur\SondeServiceWeb;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MoteurLancerCommandTest extends TestCase
{
    public function test_dry_run_ecrit_le_env_depuis_un_chemin_local(): void
    {
        $repertoire = $this->depotCobaye();

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--copie' => 'cobaye-test',
            '--var' => ['APP_NOM=Pharmacie Test'],
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('cobaye-test')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $env = file_get_contents($repertoire.DIRECTORY_SEPARATOR.'.env');

        $this->assertIsString($env);
        $this->assertStringContainsString('APP_NOM="Pharmacie Test"', $env);
        $this->assertStringContainsString('DEVISE=FCFA', $env);
        $this->assertMatchesRegularExpression('/DB_PASSWORD=[a-f0-9]{48}/', $env);
    }

    public function test_refuse_un_manifeste_invalide(): void
    {
        $repertoire = $this->depotCobaye();
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'soukdev.json', '{"version": 2}');

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('version')
            ->assertFailed();
    }

    public function test_refuse_l_absence_de_compose(): void
    {
        $repertoire = $this->depotCobaye();
        unlink($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml');

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--var' => ['APP_NOM=Test'],
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('docker-compose.yml')
            ->assertFailed();
    }

    public function test_refuse_un_compose_dangereux(): void
    {
        $repertoire = $this->depotCobaye();
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', <<<'YAML'
services:
  front:
    image: nginx:alpine
    privileged: true
YAML);

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--var' => ['APP_NOM=Test'],
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('privileged')
            ->assertFailed();

        $this->assertFileDoesNotExist($repertoire.DIRECTORY_SEPARATOR.'.env');
    }

    public function test_declare_la_copie_en_ligne_quand_le_service_repond(): void
    {
        $repertoire = $this->depotCobaye();
        $this->app->instance(SondeServiceWeb::class, new SondeServiceWeb(
            fn (string $hote, int $port): bool => $hote === '172.18.0.2' && $port === 3000,
            2,
            0,
        ));
        $arretee = false;
        $this->simulerDocker($arretee);

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--copie' => 'cobaye-web',
            '--var' => ['APP_NOM=Test'],
        ])
            ->expectsOutputToContain('il répond')
            ->assertSuccessful();

        $this->assertFalse($arretee);
    }

    public function test_arrete_la_copie_si_le_service_ne_repond_pas(): void
    {
        $repertoire = $this->depotCobaye();
        $this->app->instance(SondeServiceWeb::class, new SondeServiceWeb(fn (): bool => false, 2, 0));
        $arretee = false;
        $this->simulerDocker($arretee);

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--copie' => 'cobaye-web',
            '--var' => ['APP_NOM=Test'],
        ])
            ->expectsOutputToContain('ne répond pas')
            ->assertFailed();

        $this->assertTrue($arretee);
    }

    public function test_refuse_un_service_web_absent_du_compose(): void
    {
        $repertoire = $this->depotCobaye();
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', "services:\n  api:\n    image: nginx:alpine\n");

        $this->artisan('moteur:lancer', [
            '--chemin' => $repertoire,
            '--var' => ['APP_NOM=Test'],
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('front')
            ->assertFailed();
    }

    public function test_clone_github_puis_prepare_sans_lancer_compose(): void
    {
        $repertoire = $this->depotCobaye();

        Process::preventStrayProcesses();
        Process::fake([
            'git clone *' => function (\Illuminate\Process\PendingProcess $process) use ($repertoire) {
                $destination = $process->command[array_key_last($process->command)];
                mkdir($destination, 0777, true);
                copy($repertoire.DIRECTORY_SEPARATOR.'soukdev.json', $destination.DIRECTORY_SEPARATOR.'soukdev.json');
                copy($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', $destination.DIRECTORY_SEPARATOR.'docker-compose.yml');

                return Process::result();
            },
        ]);

        $this->artisan('moteur:lancer', [
            'depot' => 'https://github.com/excellence-team/cobaye',
            '--copie' => 'cobaye-git',
            '--var' => ['APP_NOM=Test'],
            '--dry-run' => true,
        ])->assertSuccessful();

        Process::assertRan(function (\Illuminate\Process\PendingProcess $process) {
            return $process->command[0] === 'git'
                && $process->command[1] === 'clone'
                && in_array('https://github.com/excellence-team/cobaye.git', $process->command, true);
        });
    }

    public function test_exige_un_depot_ou_un_chemin(): void
    {
        $this->artisan('moteur:lancer')
            ->expectsOutputToContain('URL GitHub')
            ->assertFailed();
    }

    protected function tearDown(): void
    {
        $copies = storage_path('app/private/copies/cobaye-git');

        if (is_dir($copies)) {
            $this->supprimer($copies);
        }

        parent::tearDown();
    }

    private function simulerDocker(bool &$arretee): void
    {
        Process::fake(function (\Illuminate\Process\PendingProcess $process) use (&$arretee) {
            $commande = $process->command;

            if (($commande[0] ?? '') !== 'docker') {
                return Process::result(exitCode: 1);
            }

            if (($commande[1] ?? '') === 'inspect') {
                return Process::result(output: '{"bridge":{"IPAddress":"172.18.0.2"}}');
            }

            if (in_array('ps', $commande, true)) {
                return Process::result(output: "a1b2c3d4e5f67890abcd1234\n");
            }

            if (in_array('down', $commande, true)) {
                $arretee = true;
            }

            return Process::result();
        });
    }

    private function depotCobaye(): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-cobaye-'.bin2hex(random_bytes(4));
        mkdir($repertoire);

        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'soukdev.json', json_encode([
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', <<<'YAML'
services:
  front:
    image: nginx:alpine
    expose:
      - "3000"
YAML);

        $this->beforeApplicationDestroyed(function () use ($repertoire) {
            $this->supprimer($repertoire);
        });

        return $repertoire;
    }

    private function supprimer(string $repertoire): void
    {
        if (! is_dir($repertoire)) {
            return;
        }

        foreach (scandir($repertoire) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }

            $chemin = $repertoire.DIRECTORY_SEPARATOR.$entree;
            is_dir($chemin) ? $this->supprimer($chemin) : unlink($chemin);
        }

        rmdir($repertoire);
    }
}
