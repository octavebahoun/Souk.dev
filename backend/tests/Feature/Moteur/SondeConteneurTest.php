<?php

namespace Tests\Feature\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\SondeServiceWeb;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SondeConteneurTest extends TestCase
{
    public function test_la_sonde_part_d_un_conteneur_jetable_sur_le_reseau_de_la_copie(): void
    {
        Process::preventStrayProcesses();
        Process::fake(fn () => Process::result(output: '404'));

        (new SondeServiceWeb(tentatives: 3))->attendre('172.23.0.2', 80, 'd12_soukdev-copie');

        Process::assertRan(function (PendingProcess $process): bool {
            $commande = $process->command;

            return is_array($commande)
                && array_slice($commande, 0, 3) === ['docker', 'run', '--rm']
                && $this->suivi($commande, '--network') === 'd12_soukdev-copie'
                && $this->suivi($commande, '--cap-drop') === 'ALL'
                && in_array('--read-only', $commande, true)
                && $commande[array_key_last($commande)] === 'http://172.23.0.2:80/';
        });
    }

    public function test_un_service_qui_ne_repond_pas_fait_echouer_le_lancement(): void
    {
        Process::preventStrayProcesses();
        Process::fake(fn () => Process::result(output: '000', exitCode: 7));

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Le service web ne répond pas sur le port 3000.');

        (new SondeServiceWeb(tentatives: 2))->attendre('172.23.0.2', 3000, 'd12_soukdev-copie');
    }

    public function test_une_adresse_publique_n_est_jamais_sondee(): void
    {
        Process::preventStrayProcesses();
        Process::fake();

        try {
            (new SondeServiceWeb)->attendre('8.8.8.8', 80, 'd12_soukdev-copie');
            $this->fail('Une adresse publique aurait dû être refusée.');
        } catch (LancementEchoue $exception) {
            $this->assertSame('L\'adresse du service web est refusée.', $exception->getMessage());
        }

        Process::assertNothingRan();
    }

    /**
     * @param  list<string>  $commande
     */
    private function suivi(array $commande, string $option): ?string
    {
        $position = array_search($option, $commande, true);

        return $position === false ? null : ($commande[$position + 1] ?? null);
    }
}
