<?php

namespace Tests\Feature\Moteur;

use App\Moteur\DossierCopie;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use App\Moteur\NettoyeurCopie;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ArretCopieTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->racine = sys_get_temp_dir().'/soukdev-arret-'.bin2hex(random_bytes(4));
        mkdir($this->racine.'/d5', 0777, true);
        file_put_contents($this->racine.'/d5/.env', "SECRET=x\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->racine));

        parent::tearDown();
    }

    public function test_down_supprime_volumes_orphelins_et_images_locales(): void
    {
        $commandes = [];
        Process::fake(function (PendingProcess $process) use (&$commandes) {
            $commandes[] = $process->command;

            return Process::result();
        });

        (new LanceurCompose)->arreter($this->racine.'/d5', 'd5');

        $this->assertSame(['docker', 'compose', '-p', 'd5', 'down', '-v', '--remove-orphans', '--rmi', 'local'], $commandes[0]);
    }

    public function test_un_down_rate_leve_une_erreur(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'daemon injoignable', exitCode: 1));

        $this->expectException(LancementEchoue::class);

        (new LanceurCompose)->arreter($this->racine.'/d5', 'd5');
    }

    public function test_liberer_descend_puis_supprime_le_dossier(): void
    {
        Process::fake(fn () => Process::result());

        $this->assertTrue($this->nettoyeur()->liberer('d5', $this->racine.'/d5', 5));
        $this->assertDirectoryDoesNotExist($this->racine.'/d5');
    }

    public function test_deux_down_rates_gardent_le_dossier_et_renvoient_faux(): void
    {
        $essais = 0;
        Process::fake(function () use (&$essais) {
            $essais++;

            return Process::result(errorOutput: 'daemon injoignable', exitCode: 1);
        });

        $this->assertFalse($this->nettoyeur()->liberer('d5', $this->racine.'/d5', 5));
        $this->assertSame(2, $essais);
        $this->assertDirectoryExists($this->racine.'/d5');
    }

    public function test_un_deuxieme_essai_reussi_suffit(): void
    {
        $essais = 0;
        Process::fake(function () use (&$essais) {
            $essais++;

            return $essais === 1 ? Process::result(exitCode: 1) : Process::result();
        });

        $this->assertTrue($this->nettoyeur()->liberer('d5', $this->racine.'/d5', 5));
        $this->assertSame(2, $essais);
    }

    public function test_sans_copie_seul_le_dossier_par_defaut_est_supprime(): void
    {
        Process::fake();

        $this->assertTrue($this->nettoyeur()->liberer(null, null, 5));
        $this->assertDirectoryDoesNotExist($this->racine.'/d5');
        Process::assertNothingRan();
    }

    private function nettoyeur(): NettoyeurCopie
    {
        return new NettoyeurCopie(new LanceurCompose, new DossierCopie($this->racine));
    }
}
