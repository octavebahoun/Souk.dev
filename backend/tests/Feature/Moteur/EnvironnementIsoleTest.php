<?php

namespace Tests\Feature\Moteur;

use App\Moteur\EnvironnementProcessus;
use App\Moteur\LanceurCompose;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Faille corrigée : docker compose héritait de l'environnement du moteur, et un
 * docker-compose.yml qui écrivait « ${SASPAY_SECRET_KEY} » recevait la vraie clé.
 */
class EnvironnementIsoleTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SOUKDEV_SECRET_DE_TEST');
        unset($_ENV['SOUKDEV_SECRET_DE_TEST'], $_SERVER['SOUKDEV_SECRET_DE_TEST']);

        parent::tearDown();
    }

    public function test_aucune_variable_heritee_ne_passe_aux_commandes(): void
    {
        putenv('SOUKDEV_SECRET_DE_TEST=valeur-de-test');
        $_ENV['SOUKDEV_SECRET_DE_TEST'] = 'valeur-de-test';
        $_SERVER['SOUKDEV_SECRET_DE_TEST'] = 'valeur-de-test';

        $env = EnvironnementProcessus::minimal();

        $this->assertFalse($env['SOUKDEV_SECRET_DE_TEST']);
        $this->assertFalse($env['APP_KEY'] ?? false);
        $this->assertIsString($env['PATH']);
        $this->assertSame('C', $env['LC_ALL']);
    }

    public function test_l_arret_d_une_copie_ne_transmet_aucun_secret_a_docker(): void
    {
        putenv('SOUKDEV_SECRET_DE_TEST=valeur-de-test');
        $_ENV['SOUKDEV_SECRET_DE_TEST'] = 'valeur-de-test';
        Process::preventStrayProcesses();
        Process::fake();

        (new LanceurCompose)->arreter(sys_get_temp_dir(), 'd42');

        Process::assertRan(fn (PendingProcess $process): bool => ($process->command[0] ?? null) === 'docker'
            && ($process->environment['SOUKDEV_SECRET_DE_TEST'] ?? null) === false
            && is_string($process->environment['PATH'] ?? null));
    }
}
