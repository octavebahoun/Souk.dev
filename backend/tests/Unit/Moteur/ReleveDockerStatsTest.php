<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\ReleveDockerStats;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ReleveDockerStatsTest extends TestCase
{
    public function test_convertit_les_unites_docker_en_mo(): void
    {
        $releve = new ReleveDockerStats;

        $this->assertSame(450, $releve->moDepuisUsage('450MiB / 2GiB'));
        $this->assertSame(1229, $releve->moDepuisUsage('1.2GiB / 2GiB'));
        $this->assertSame(1, $releve->moDepuisUsage('512KiB / 512MiB'));
        $this->assertSame(0, $releve->moDepuisUsage('0B / 2GiB'));
    }

    public function test_retient_le_conteneur_le_plus_gourmand(): void
    {
        $this->simulerStats("abc123def4567890\n", "80MiB / 2GiB\n450MiB / 2GiB\n");

        $pic = (new ReleveDockerStats)->pic(sys_get_temp_dir(), 'mesure-1', 0, 5);

        $this->assertSame(450, $pic);
        Process::assertRan(function (PendingProcess $process) {
            return in_array('stats', $process->command, true)
                && in_array('abc123def4567890', $process->command, true)
                && in_array('--no-stream', $process->command, true);
        });
    }

    public function test_echoue_sans_conteneur(): void
    {
        $this->simulerStats('', '');

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Aucun conteneur');

        (new ReleveDockerStats)->pic(sys_get_temp_dir(), 'mesure-1', 0, 5);
    }

    public function test_refuse_un_identifiant_de_conteneur_inattendu(): void
    {
        $this->simulerStats("../etc/passwd\n", '');

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Identifiant');

        (new ReleveDockerStats)->pic(sys_get_temp_dir(), 'mesure-1', 0, 5);
    }

    public function test_refuse_une_mesure_illisible(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('illisible');

        (new ReleveDockerStats)->moDepuisUsage('beaucoup');
    }

    private function simulerStats(string $identifiants, string $usages): void
    {
        Process::fake(function (PendingProcess $process) use ($identifiants, $usages) {
            if (in_array('ps', $process->command, true)) {
                return Process::result(output: $identifiants);
            }

            if (in_array('stats', $process->command, true)) {
                return Process::result(output: $usages);
            }

            return Process::result(errorOutput: 'inattendu', exitCode: 1);
        });
    }
}
