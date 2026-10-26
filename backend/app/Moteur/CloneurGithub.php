<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use Illuminate\Support\Facades\File;

class CloneurGithub
{
    /** Dépôt d'un inconnu : aucune configuration de la machine, aucune invite, messages en anglais. */
    public const ENV_GIT = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_LFS_SKIP_SMUDGE' => '1',
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

        $commande[] = $this->miroir($depot) ?? $depot->urlClone;
        $commande[] = $destination;

        $resultat = Commande::isolee(self::ENV_GIT)
            ->timeout((int) config('moteur.git_timeout'))
            ->run($commande);

        if ($resultat->failed()) {
            File::deleteDirectory($destination);

            throw new DepotInvalide('Impossible de cloner le dépôt GitHub public.');
        }

        return $destination;
    }

    /**
     * Jeu de démo, en développement seulement : un dépôt local remplace le dépôt GitHub du même
     * nom (owner et repo sont déjà réduits à [A-Za-z0-9._-] par DepotGithub). Jamais en production.
     */
    private function miroir(DepotGithub $depot): ?string
    {
        $racine = config('moteur.miroirs');

        if (! is_string($racine) || $racine === '' || ! app()->environment('local', 'testing')) {
            return null;
        }

        $chemin = rtrim($racine, '/').'/'.$depot->owner.'/'.$depot->repo.'.git';

        return is_dir($chemin) && ! is_link($chemin) ? 'file://'.$chemin : null;
    }
}
