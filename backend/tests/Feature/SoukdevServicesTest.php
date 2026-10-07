<?php

namespace Tests\Feature;

use App\Soukdev\EnvGenerator;
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
}
