<?php

namespace Tests\Feature\Moteur;

use App\Moteur\CloneurGithub;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CloneurGithubTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->racine = sys_get_temp_dir().'/soukdev-clone-'.bin2hex(random_bytes(4));
        config(['moteur.copies' => $this->racine]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->racine));

        parent::tearDown();
    }

    /**
     * Garde-fou de sécurité : sans core.symlinks=false, un lien du dépôt vers backend/.env
     * serait lu par le validateur ou envoyé à docker compose. Aucune requête ne peut
     * le vérifier sans cloner depuis GitHub : on contrôle donc la commande lancée.
     */
    public function test_le_clone_neutralise_les_liens_symboliques_et_isole_git(): void
    {
        Process::preventStrayProcesses();
        Process::fake();

        (new CloneurGithub)->cloner('https://github.com/acme/pharmacie', 'd1');

        Process::assertRan(function (PendingProcess $process): bool {
            $commande = $process->command;

            return is_array($commande)
                && array_slice($commande, 0, 2) === ['git', 'clone']
                && in_array('core.symlinks=false', $commande, true)
                && ($process->environment['LC_ALL'] ?? null) === 'C'
                && ($process->environment['GIT_TERMINAL_PROMPT'] ?? null) === '0'
                && ($process->environment['GIT_CONFIG_GLOBAL'] ?? null) === '/dev/null';
        });
    }
}
