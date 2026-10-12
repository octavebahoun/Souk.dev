<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\FichiersMigration;
use PHPUnit\Framework\TestCase;

class FichiersMigrationTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->racine = sys_get_temp_dir().'/soukdev-migrations-'.bin2hex(random_bytes(4));
        mkdir($this->racine.'/db', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->supprimer($this->racine);

        parent::tearDown();
    }

    public function test_liste_les_sql_dans_l_ordre_des_noms(): void
    {
        file_put_contents($this->racine.'/db/002_suite.sql', "CREATE TABLE b (id int);\n");
        file_put_contents($this->racine.'/db/001_init.sql', "CREATE TABLE a (id int);\n");
        file_put_contents($this->racine.'/db/notes.txt', 'pas du sql');

        $fichiers = (new FichiersMigration)->lister($this->racine, 'db');

        $this->assertSame(
            ['001_init.sql', '002_suite.sql'],
            array_map('basename', $fichiers),
        );
    }

    public function test_refuse_un_dossier_vide(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Aucune migration');

        (new FichiersMigration)->lister($this->racine, 'db');
    }

    public function test_refuse_une_commande_psql(): void
    {
        file_put_contents($this->racine.'/db/001.sql', "\\i /etc/passwd\n");

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('commande psql');

        (new FichiersMigration)->lister($this->racine, 'db');
    }

    public function test_refuse_un_lien_qui_sort_du_depot(): void
    {
        $exterieur = sys_get_temp_dir().'/soukdev-dehors-'.bin2hex(random_bytes(4));
        mkdir($exterieur);
        file_put_contents($exterieur.'/secret.sql', "CREATE TABLE secret (id int);\n");

        try {
            symlink($exterieur.'/secret.sql', $this->racine.'/db/001.sql');

            $this->expectException(LancementEchoue::class);
            $this->expectExceptionMessage('sort du dossier');

            (new FichiersMigration)->lister($this->racine, 'db');
        } finally {
            $this->supprimer($exterieur);
        }
    }

    public function test_refuse_un_dossier_en_dehors_du_depot(): void
    {
        $exterieur = sys_get_temp_dir().'/soukdev-dehors-'.bin2hex(random_bytes(4));
        mkdir($exterieur);
        file_put_contents($exterieur.'/001.sql', "CREATE TABLE secret (id int);\n");
        rmdir($this->racine.'/db');
        symlink($exterieur, $this->racine.'/db');

        try {
            $this->expectException(LancementEchoue::class);
            $this->expectExceptionMessage('sort du dépôt');

            (new FichiersMigration)->lister($this->racine, 'db');
        } finally {
            $this->supprimer($exterieur);
        }
    }

    private function supprimer(string $chemin): void
    {
        if (is_link($chemin)) {
            unlink($chemin);

            return;
        }

        if (! is_dir($chemin)) {
            if (is_file($chemin)) {
                unlink($chemin);
            }

            return;
        }

        foreach (scandir($chemin) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }

            $this->supprimer($chemin.DIRECTORY_SEPARATOR.$entree);
        }

        @rmdir($chemin);
    }
}
