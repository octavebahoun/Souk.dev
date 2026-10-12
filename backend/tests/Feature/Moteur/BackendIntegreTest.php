<?php

namespace Tests\Feature\Moteur;

use App\Moteur\BackendIntegre;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\MoteurDeploiement;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackendIntegreTest extends TestCase
{
    public function test_applique_les_sql_dans_l_ordre_puis_recharge_l_api(): void
    {
        $repertoire = $this->depot();
        file_put_contents($repertoire.'/db/002_suite.sql', "CREATE TABLE ventes (id int);\n");
        config(['moteur.backend_tentatives' => 1]);

        $scripts = [];
        $relance = false;
        Process::fake(function (PendingProcess $process) use (&$scripts, &$relance) {
            $commande = $process->command;

            if (in_array('pg_isready', $commande, true)) {
                return Process::result();
            }

            if (in_array('psql', $commande, true)) {
                $scripts[] = (string) $process->input;

                return Process::result();
            }

            if (in_array('restart', $commande, true) && in_array('soukdev-api', $commande, true)) {
                $relance = true;

                return Process::result();
            }

            return Process::result(errorOutput: 'inattendu', exitCode: 1);
        });

        try {
            $backend = app(BackendIntegre::class);
            $backend->completerEnv($repertoire, [
                'backend' => 'integre',
                'migrations' => 'db',
            ], []);
            $backend->appliquer(new CopieLocale(
                'copie-integre',
                $repertoire,
                ['backend' => 'integre', 'migrations' => 'db', 'service_web' => ['nom' => 'front', 'port' => 3000]],
                [],
                true,
            ));

            $this->assertCount(4, $scripts);
            $this->assertStringContainsString('soukdev_anon', $scripts[0]);
            $this->assertStringContainsString('CREATE TABLE pharmacies', $scripts[1]);
            $this->assertStringContainsString('CREATE TABLE ventes', $scripts[2]);
            $this->assertStringContainsString('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES', $scripts[3]);
            $this->assertTrue($relance);
        } finally {
            $this->supprimer($repertoire);
        }
    }

    public function test_une_base_muette_coupe_la_copie(): void
    {
        $repertoire = $this->depot();
        config(['moteur.backend_tentatives' => 1]);
        $coupe = false;

        Process::fake(function (PendingProcess $process) use (&$coupe) {
            $commande = $process->command;

            if (in_array('up', $commande, true)) {
                return Process::result();
            }

            if (in_array('pg_isready', $commande, true)) {
                return Process::result(exitCode: 1);
            }

            if (in_array('down', $commande, true)) {
                $coupe = true;

                return Process::result();
            }

            return Process::result(errorOutput: 'inattendu', exitCode: 1);
        });

        try {
            app(MoteurDeploiement::class)->mettreEnLigne(new CopieLocale(
                'copie-integre',
                $repertoire,
                ['backend' => 'integre', 'migrations' => 'db', 'service_web' => ['nom' => 'front', 'port' => 3000]],
                [],
                false,
            ), 'petite');
            $this->fail('Une base muette aurait dû arrêter le lancement.');
        } catch (LancementEchoue $exception) {
            $this->assertStringContainsString('ne répond pas', $exception->getMessage());
            $this->assertTrue($coupe);
        } finally {
            $this->supprimer($repertoire);
        }
    }

    private function depot(): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-backend-'.bin2hex(random_bytes(4));
        mkdir($repertoire.'/db', 0777, true);
        file_put_contents($repertoire.'/docker-compose.yml', "services:\n  front:\n    image: nginx:alpine\n");
        file_put_contents($repertoire.'/db/001_init.sql', "CREATE TABLE pharmacies (id int);\n");

        return $repertoire;
    }

    private function supprimer(string $chemin): void
    {
        if (! is_dir($chemin)) {
            return;
        }

        foreach (scandir($chemin) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }

            $suivant = $chemin.DIRECTORY_SEPARATOR.$entree;
            is_dir($suivant) ? $this->supprimer($suivant) : unlink($suivant);
        }

        rmdir($chemin);
    }
}
