<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final class CloneurGithub
{
    public function cloner(string $url, string $copie): string
    {
        $depot = DepotGithub::depuisUrl($url);
        $parent = config('moteur.copies');
        $destination = $parent.DIRECTORY_SEPARATOR.$copie;

        if (is_dir($destination)) {
            throw new DepotInvalide("La copie « {$copie} » existe déjà.");
        }

        File::ensureDirectoryExists($parent);

        $commande = ['git', 'clone', '--depth', '1'];

        if ($depot->branche !== null) {
            $commande[] = '--branch';
            $commande[] = $depot->branche;
            $commande[] = '--single-branch';
        }

        $commande[] = $depot->urlClone;
        $commande[] = $destination;

        $resultat = Process::timeout((int) config('moteur.git_timeout'))->run($commande);

        if ($resultat->failed()) {
            File::deleteDirectory($destination);

            throw new DepotInvalide('Impossible de cloner le dépôt GitHub public.');
        }

        return $destination;
    }
}
