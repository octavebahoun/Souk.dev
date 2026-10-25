<?php

namespace Tests\Unit\Moteur;

use App\Moteur\DossierCopie;
use PHPUnit\Framework\TestCase;

class DossierCopieTest extends TestCase
{
    private string $base;

    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/soukdev-dossier-'.bin2hex(random_bytes(4));
        $this->racine = $this->base.'/copies';
        mkdir($this->racine, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->base));

        parent::tearDown();
    }

    public function test_supprime_le_dossier_d_une_copie_et_son_env(): void
    {
        $copie = $this->racine.'/d12';
        mkdir($copie.'/src', 0777, true);
        file_put_contents($copie.'/.env', "SECRET=x\n");
        file_put_contents($copie.'/src/index.php', '<?php');

        $this->assertTrue((new DossierCopie($this->racine))->supprimer($copie));
        $this->assertDirectoryDoesNotExist($copie);
    }

    public function test_un_dossier_absent_compte_comme_supprime(): void
    {
        $this->assertTrue((new DossierCopie($this->racine))->supprimer($this->racine.'/d99'));
        $this->assertTrue((new DossierCopie($this->racine))->supprimer(null));
    }

    public function test_ne_touche_jamais_hors_de_la_racine(): void
    {
        $dehors = $this->base.'/dehors';
        mkdir($dehors);
        file_put_contents($dehors.'/garder.txt', 'x');
        $dossier = new DossierCopie($this->racine);

        $this->assertFalse($dossier->supprimer($dehors));
        $this->assertFalse($dossier->supprimer($this->racine.'/../dehors'));
        $this->assertFalse($dossier->supprimer($this->racine));
        $this->assertFileExists($dehors.'/garder.txt');
        $this->assertDirectoryExists($this->racine);
    }

    public function test_un_lien_vers_l_exterieur_n_est_pas_suivi(): void
    {
        $dehors = $this->base.'/dehors';
        mkdir($dehors);
        file_put_contents($dehors.'/garder.txt', 'x');
        $copie = $this->racine.'/d3';
        mkdir($copie);
        symlink($dehors, $copie.'/lien');
        symlink($dehors, $this->racine.'/d4');
        $dossier = new DossierCopie($this->racine);

        $this->assertTrue($dossier->supprimer($copie));
        $this->assertFalse($dossier->supprimer($this->racine.'/d4'));
        $this->assertFileExists($dehors.'/garder.txt');
    }

    public function test_liste_seulement_les_dossiers_d_id(): void
    {
        mkdir($this->racine.'/d7');
        mkdir($this->racine.'/cobaye-git');
        file_put_contents($this->racine.'/d8', 'fichier');

        $this->assertSame([7 => $this->racine.'/d7'], (new DossierCopie($this->racine))->dossiersDeDeploiement());
    }

    public function test_sans_racine_valide_rien_n_est_supprime(): void
    {
        $this->assertNull((new DossierCopie($this->base.'/absente'))->racine());
        $this->assertFalse((new DossierCopie('/'))->estDansLaRacine('/tmp'));
    }
}
