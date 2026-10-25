<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final class CloneurGithub
{
    /** Dépôt d'un inconnu : aucune configuration de la machine, aucune invite, messages en anglais. */
    public const ENV_GIT = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_LFS_SKIP_SMUDGE' => '1',
        'LC_ALL' => 'C',
        'LANG' => 'C',
    ];

    public function cloner(string $url, string $copie): string
    {
        $depot = DepotGithub::depuisUrl($url);
        $parent = config('moteur.copies');
        $destination = $parent.DIRECTORY_SEPARATOR.$copie;

        if (is_dir($destination)) {
            throw new DepotInvalide("La copie « {$copie} » existe déjà.");
        }

        File::ensureDirectoryExists($parent);

        // core.symlinks=false : un lien symbolique du dépôt (vers un .env, /etc…) devient un simple fichier.
        // Ni le validateur, ni docker compose (env_file, contexte de build) ne lisent alors hors du dépôt.
        $commande = ['git', 'clone', '--depth', '1', '--config', 'core.symlinks=false'];

        if ($depot->branche !== null) {
            $commande[] = '--branch';
            $commande[] = $depot->branche;
            $commande[] = '--single-branch';
        }

        $commande[] = $depot->urlClone;
        $commande[] = $destination;

        $resultat = Process::env(self::ENV_GIT)
            ->timeout((int) config('moteur.git_timeout'))
            ->run($commande);

        if ($resultat->failed()) {
            File::deleteDirectory($destination);

            throw new DepotInvalide('Impossible de cloner le dépôt GitHub public.');
        }

        return $destination;
    }
}
