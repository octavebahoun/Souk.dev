<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\VariablesManquantes;
use App\Moteur\GenerateurEnv;
use PHPUnit\Framework\TestCase;

class GenerateurEnvTest extends TestCase
{
    private GenerateurEnv $generateur;

    /**
     * @var array<string, mixed>
     */
    private array $manifeste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateur = new GenerateurEnv;
        $this->manifeste = [
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'APP_VILLE', 'source' => 'client', 'libelle' => 'Ville', 'requis' => false],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ];
    }

    public function test_remplit_les_trois_sources(): void
    {
        $env = $this->generateur->generer($this->manifeste, ['APP_NOM' => 'Pharmacie Test']);

        $this->assertSame('Pharmacie Test', $env['APP_NOM']);
        $this->assertSame('FCFA', $env['DEVISE']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $env['DB_PASSWORD']);
        $this->assertArrayNotHasKey('APP_VILLE', $env);
    }

    public function test_inclut_une_variable_client_optionnelle(): void
    {
        $env = $this->generateur->generer($this->manifeste, [
            'APP_NOM' => 'Clinique',
            'APP_VILLE' => 'Cotonou',
        ]);

        $this->assertSame('Cotonou', $env['APP_VILLE']);
    }

    public function test_refuse_une_variable_client_obligatoire_manquante(): void
    {
        $this->expectException(VariablesManquantes::class);
        $this->expectExceptionMessage('APP_NOM');

        $this->generateur->generer($this->manifeste, []);
    }

    public function test_ecrit_un_fichier_env_avec_echappement(): void
    {
        $chemin = sys_get_temp_dir().'/soukdev-'.bin2hex(random_bytes(4)).'.env';
        $this->generateur->ecrire($chemin, [
            'DEVISE' => 'FCFA',
            'APP_NOM' => 'Pharmacie "Centre"',
        ]);

        $contenu = file_get_contents($chemin);
        @unlink($chemin);

        $this->assertIsString($contenu);
        $this->assertStringContainsString('DEVISE=FCFA', $contenu);
        $this->assertStringContainsString('APP_NOM="Pharmacie \\"Centre\\""', $contenu);
    }

    public function test_deux_copies_ont_des_secrets_differents(): void
    {
        $a = $this->generateur->generer($this->manifeste, ['APP_NOM' => 'A']);
        $b = $this->generateur->generer($this->manifeste, ['APP_NOM' => 'B']);

        $this->assertNotSame($a['DB_PASSWORD'], $b['DB_PASSWORD']);
    }
}
