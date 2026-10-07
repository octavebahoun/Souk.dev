<?php

namespace Tests\Feature;

use App\Soukdev\CopyLauncher;
use App\Soukdev\EnvGenerator;
use App\Soukdev\RepositoryReader;
use App\Soukdev\SchemaValidator;
use Tests\TestCase;

class SoukdevServicesTest extends TestCase
{
    public function test_les_services_lisent_le_schema_du_depot(): void
    {
        $manifest = $this->app->make(SchemaValidator::class)->validate([
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ]);

        $env = $this->app->make(EnvGenerator::class)->generate($manifest, [
            'APP_NOM' => 'Pharmacie',
        ]);

        $this->assertSame('front', $manifest->serviceWeb);
        $this->assertMatchesRegularExpression(
            '/\AAPP_NOM=Pharmacie\nDB_PASSWORD=[A-Za-z0-9]{32}\nDEVISE=FCFA\n\z/',
            $env,
        );
    }

    public function test_le_lecteur_de_depot_est_disponible(): void
    {
        $this->assertInstanceOf(RepositoryReader::class, $this->app->make(RepositoryReader::class));
    }

    public function test_le_lanceur_de_copie_est_disponible(): void
    {
        $this->assertInstanceOf(CopyLauncher::class, $this->app->make(CopyLauncher::class));
    }
}
