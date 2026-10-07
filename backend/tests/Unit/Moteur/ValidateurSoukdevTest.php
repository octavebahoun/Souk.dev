<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\ValidateurSoukdev;
use PHPUnit\Framework\TestCase;

class ValidateurSoukdevTest extends TestCase
{
    private ValidateurSoukdev $validateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validateur = new ValidateurSoukdev;
    }

    public function test_valide_l_exemple_du_contrat(): void
    {
        $manifeste = $this->validateur->valider($this->exemple());

        $this->assertSame(1, $manifeste['version']);
        $this->assertSame('front', $manifeste['service_web']['nom']);
        $this->assertSame('propre', $manifeste['backend']);
    }

    public function test_valide_un_fichier_json(): void
    {
        $chemin = sys_get_temp_dir().'/soukdev-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($chemin, json_encode($this->exemple(), JSON_THROW_ON_ERROR));

        try {
            $manifeste = $this->validateur->validerFichier($chemin);
            $this->assertSame('propre', $manifeste['backend']);
        } finally {
            @unlink($chemin);
        }
    }

    public function test_refuse_un_fichier_absent(): void
    {
        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('soukdev.json est absent');

        $this->validateur->validerFichier('/tmp/soukdev-inexistant.json');
    }

    public function test_refuse_un_json_invalide(): void
    {
        $chemin = sys_get_temp_dir().'/soukdev-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($chemin, '{pas du json');

        try {
            $this->expectException(ManifestInvalide::class);
            $this->validateur->validerFichier($chemin);
        } finally {
            @unlink($chemin);
        }
    }

    public function test_refuse_une_propriete_inconnue(): void
    {
        $donnees = $this->exemple();
        $donnees['secret'] = true;

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('Propriété inconnue');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_integre_sans_migrations(): void
    {
        $donnees = $this->exemple();
        $donnees['backend'] = 'integre';

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('migrations est obligatoire');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_propre_avec_migrations(): void
    {
        $donnees = $this->exemple();
        $donnees['migrations'] = 'db/migrations';

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('migrations doit être absent');

        $this->validateur->valider($donnees);
    }

    public function test_accepte_integre_avec_migrations(): void
    {
        $donnees = $this->exemple();
        $donnees['backend'] = 'integre';
        $donnees['migrations'] = 'db/migrations';
        $donnees['variables'] = [
            ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom', 'requis' => true],
        ];

        $manifeste = $this->validateur->valider($donnees);

        $this->assertSame('db/migrations', $manifeste['migrations']);
    }

    public function test_refuse_un_chemin_de_migrations_absolu(): void
    {
        $donnees = $this->exemple();
        $donnees['backend'] = 'integre';
        $donnees['migrations'] = '/etc/passwd';

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('chemin relatif');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_un_port_hors_limites(): void
    {
        $donnees = $this->exemple();
        $donnees['service_web']['port'] = 0;

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('port');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_une_variable_client_incomplete(): void
    {
        $donnees = $this->exemple();
        $donnees['variables'][] = ['nom' => 'APP_VILLE', 'source' => 'client'];

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('libelle et requis');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_une_variable_plateforme_avec_valeur(): void
    {
        $donnees = $this->exemple();
        $donnees['variables'][1]['valeur'] = 'secret';

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('interdite');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_un_nom_de_variable_minuscule(): void
    {
        $donnees = $this->exemple();
        $donnees['variables'][0]['nom'] = 'app_nom';

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('nom est invalide');

        $this->validateur->valider($donnees);
    }

    public function test_refuse_un_nom_de_variable_en_double(): void
    {
        $donnees = $this->exemple();
        $donnees['variables'][] = ['nom' => 'APP_NOM', 'source' => 'fixe', 'valeur' => 'x'];

        $this->expectException(ManifestInvalide::class);
        $this->expectExceptionMessage('Variable en double');

        $this->validateur->valider($donnees);
    }

    /**
     * @return array<string, mixed>
     */
    private function exemple(): array
    {
        return [
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
                ['nom' => 'DEVISE', 'source' => 'fixe', 'valeur' => 'FCFA'],
            ],
        ];
    }
}
