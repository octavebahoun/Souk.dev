<?php

namespace Tests\Unit\Moteur;

use App\Moteur\BackendIntegre;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\FichiersMigration;
use App\Moteur\LanceurCompose;
use PHPUnit\Framework\TestCase;

class BackendIntegreTest extends TestCase
{
    public function test_prepare_l_url_la_cle_et_le_compose_sans_port_publie(): void
    {
        $repertoire = $this->depot();

        try {
            $env = $this->backend()->completerEnv($repertoire, $this->manifeste(), [
                'APP_NOM' => 'Pharmacie',
                'DEVISE' => 'FCFA',
            ]);
            $yaml = file_get_contents($repertoire.'/docker-compose.backend.yml');

            $this->assertSame('http://soukdev-api:3000', $env['SOUKDEV_URL']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $env['SOUKDEV_CLE']);
            $this->assertSame('Pharmacie', $env['APP_NOM']);
            $this->assertIsString($yaml);
            $this->assertStringContainsString('image: postgres:16-alpine', $yaml);
            $this->assertStringContainsString('image: postgrest/postgrest:v12.2.3', $yaml);
            $this->assertStringContainsString('soukdev_anon', $yaml);
            $this->assertDoesNotMatchRegularExpression('/^ports:/m', $yaml);
            $this->assertStringNotContainsString('privileged:', $yaml);
            $this->assertStringNotContainsString('volumes:', $yaml);
            $this->assertStringContainsString("  front:\n    environment:\n      SOUKDEV_URL: \"http://soukdev-api:3000\"", $yaml);
            $this->assertStringContainsString('SOUKDEV_CLE: "'.$env['SOUKDEV_CLE'].'"', $yaml);
        } finally {
            $this->supprimer($repertoire);
        }
    }

    public function test_deux_copies_ont_une_cle_differente(): void
    {
        $a = $this->depot();
        $b = $this->depot();

        try {
            $backend = $this->backend();
            $premiere = $backend->completerEnv($a, $this->manifeste(), []);
            $seconde = $backend->completerEnv($b, $this->manifeste(), []);

            $this->assertNotSame($premiere['SOUKDEV_CLE'], $seconde['SOUKDEV_CLE']);
        } finally {
            $this->supprimer($a);
            $this->supprimer($b);
        }
    }

    public function test_un_backend_propre_n_ajoute_rien(): void
    {
        $repertoire = $this->depot();

        try {
            $env = $this->backend()->completerEnv($repertoire, ['backend' => 'propre'], ['DEVISE' => 'FCFA']);

            $this->assertSame(['DEVISE' => 'FCFA'], $env);
            $this->assertFileDoesNotExist($repertoire.'/docker-compose.backend.yml');
        } finally {
            $this->supprimer($repertoire);
        }
    }

    public function test_refuse_un_service_reserve(): void
    {
        $repertoire = $this->depot("services:\n  soukdev-db:\n    image: postgres:16-alpine\n");

        try {
            $this->expectException(LancementEchoue::class);
            $this->expectExceptionMessage('soukdev-db');

            $this->backend()->completerEnv($repertoire, $this->manifeste(), []);
        } finally {
            $this->supprimer($repertoire);
        }
    }

    private function backend(): BackendIntegre
    {
        return new BackendIntegre(new FichiersMigration, new LanceurCompose);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifeste(): array
    {
        return [
            'backend' => 'integre',
            'migrations' => 'db',
        ];
    }

    private function depot(string $compose = "services:\n  front:\n    image: nginx:alpine\n"): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-backend-'.bin2hex(random_bytes(4));
        mkdir($repertoire.'/db', 0777, true);
        file_put_contents($repertoire.'/docker-compose.yml', $compose);
        file_put_contents($repertoire.'/db/001_init.sql', "CREATE TABLE pharmacies (id int);\n");

        return $repertoire;
    }

    private function supprimer(string $chemin): void
    {
        if (! is_dir($chemin)) {
            return;
        }

        $elements = scandir($chemin) ?: [];

        foreach ($elements as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }

            $suivant = $chemin.DIRECTORY_SEPARATOR.$entree;
            is_dir($suivant) ? $this->supprimer($suivant) : unlink($suivant);
        }

        rmdir($chemin);
    }
}
