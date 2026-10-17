<?php

namespace Tests\Feature\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ConfigComposeFinaleTest extends TestCase
{
    public function test_compose_config_passe_avant_up_et_accepte_un_json_resolu(): void
    {
        $repertoire = $this->depot();
        $commandes = [];

        Process::fake(function (PendingProcess $process) use (&$commandes, $repertoire) {
            $commandes[] = $process->command;

            if (in_array('config', $process->command, true)) {
                return Process::result(output: json_encode([
                    'name' => 'copie-test',
                    'services' => [
                        'front' => [
                            'image' => 'nginx:alpine',
                            'build' => [
                                'context' => realpath($repertoire) ?: $repertoire,
                                'dockerfile' => 'Dockerfile',
                            ],
                        ],
                    ],
                    'networks' => [
                        'default' => [
                            'name' => 'copie-test_default',
                            'ipam' => [],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if (in_array('up', $process->command, true)) {
                return Process::result();
            }

            return Process::result(errorOutput: 'inattendu', exitCode: 1);
        });

        try {
            (new LanceurCompose)->lancer($repertoire, 'copie-test', 'petite');

            $this->assertCount(2, $commandes);
            $this->assertContains('config', $commandes[0]);
            $this->assertContains('--format', $commandes[0]);
            $this->assertContains('json', $commandes[0]);
            $this->assertContains('docker-compose.soukdev.yml', $commandes[0]);
            $this->assertContains('up', $commandes[1]);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_un_json_dangereux_coupe_avant_up(): void
    {
        $repertoire = $this->depot();
        $up = false;

        Process::fake(function (PendingProcess $process) use (&$up) {
            if (in_array('config', $process->command, true)) {
                return Process::result(output: json_encode([
                    'services' => [
                        'front' => [
                            'image' => 'nginx:alpine',
                            'privileged' => true,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if (in_array('up', $process->command, true)) {
                $up = true;
            }

            return Process::result();
        });

        try {
            try {
                (new LanceurCompose)->lancer($repertoire, 'copie-test', 'petite');
                $this->fail('Un compose privileged après fusion aurait dû être refusé.');
            } catch (LancementEchoue $exception) {
                $this->assertStringContainsString('privileged', $exception->getMessage());
            }

            $this->assertFalse($up);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_un_volume_hote_resolu_est_refuse_sans_lancer(): void
    {
        $repertoire = $this->depot();
        $up = false;

        Process::fake(function (PendingProcess $process) use (&$up) {
            if (in_array('config', $process->command, true)) {
                return Process::result(output: json_encode([
                    'services' => [
                        'front' => [
                            'image' => 'nginx:alpine',
                            'volumes' => ['/etc/passwd:/data'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if (in_array('up', $process->command, true)) {
                $up = true;
            }

            return Process::result();
        });

        try {
            try {
                (new LanceurCompose)->lancer($repertoire, 'copie-test', 'petite');
                $this->fail('Un volume de l\'hôte après interpolation aurait dû être refusé.');
            } catch (LancementEchoue $exception) {
                $this->assertStringContainsString('volume de l\'hôte', $exception->getMessage());
            }

            $this->assertFalse($up);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_une_variable_dans_un_volume_coupe_avant_up(): void
    {
        $repertoire = $this->depot();
        $up = false;

        Process::fake(function (PendingProcess $process) use (&$up) {
            if (in_array('config', $process->command, true)) {
                return Process::result(output: json_encode([
                    'services' => [
                        'front' => [
                            'image' => 'nginx:alpine',
                            'volumes' => ['${SOURCE}:/data'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if (in_array('up', $process->command, true)) {
                $up = true;
            }

            return Process::result();
        });

        try {
            try {
                (new LanceurCompose)->lancer($repertoire, 'copie-test', 'petite');
                $this->fail('Une variable dans un volume après fusion aurait dû être refusée.');
            } catch (LancementEchoue $exception) {
                $this->assertStringContainsString('variable', $exception->getMessage());
            }

            $this->assertFalse($up);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    private function depot(): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-config-'.bin2hex(random_bytes(4));
        mkdir($repertoire);
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', "services:\n  front:\n    image: nginx:alpine\n");
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'.env', "APP_NOM=Test\n");

        return $repertoire;
    }

    private function nettoyer(string $repertoire): void
    {
        foreach (['docker-compose.yml', 'docker-compose.soukdev.yml', '.env'] as $fichier) {
            @unlink($repertoire.DIRECTORY_SEPARATOR.$fichier);
        }
        @rmdir($repertoire);
    }
}
