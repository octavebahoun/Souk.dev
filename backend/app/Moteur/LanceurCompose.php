<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\Process;

final class LanceurCompose
{
    public function verifier(string $repertoire, string $nomService): string
    {
        $compose = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml';

        if (! is_file($compose)) {
            throw new LancementEchoue('docker-compose.yml est absent à la racine du dépôt.');
        }

        $contenu = file_get_contents($compose);

        if ($contenu === false || trim($contenu) === '') {
            throw new LancementEchoue('docker-compose.yml est vide ou illisible.');
        }

        $noms = $this->nomsServices($contenu);

        if ($noms === []) {
            throw new LancementEchoue('docker-compose.yml ne déclare aucun service.');
        }

        if (! in_array($nomService, $noms, true)) {
            throw new LancementEchoue("Le service web « {$nomService} » est absent de docker-compose.yml.");
        }

        return $compose;
    }

    /**
     * @return list<string>
     */
    public function nomsServices(string $yaml): array
    {
        $noms = [];
        $dansServices = false;
        $indentServices = 0;
        $indentEnfant = null;

        foreach (preg_split('/\R/', $yaml) as $ligne) {
            $sansCommentaire = preg_replace('/\s#.*$/', '', $ligne) ?? $ligne;

            if (trim($sansCommentaire) === '') {
                continue;
            }

            preg_match('/^(\s*)/', $sansCommentaire, $espaces);
            $indent = strlen($espaces[1]);

            if (! $dansServices) {
                if (preg_match('/^services:\s*$/', $sansCommentaire)) {
                    $dansServices = true;
                    $indentServices = $indent;
                }

                continue;
            }

            if ($indent <= $indentServices) {
                break;
            }

            $indentEnfant ??= $indent;

            if ($indent === $indentEnfant && preg_match('/^\s+([A-Za-z0-9._-]+)\s*:/', $sansCommentaire, $nom)) {
                $noms[] = $nom[1];
            }
        }

        return $noms;
    }

    public function lancer(string $repertoire, string $copie): void
    {
        $this->verifierNomCopie($copie);

        $env = $repertoire.DIRECTORY_SEPARATOR.'.env';

        $resultat = Process::path($repertoire)
            ->timeout((int) config('moteur.compose_timeout'))
            ->run(['docker', 'compose', '-p', $copie, '--env-file', $env, 'up', '-d', '--build']);

        if ($resultat->failed()) {
            $sortie = trim($resultat->errorOutput().' '.$resultat->output());

            if (str_contains($sortie, 'not found') || $resultat->exitCode() === 127) {
                throw new LancementEchoue('Docker Compose est introuvable. Installez Docker pour lancer une copie.');
            }

            throw new LancementEchoue('Le lancement Docker Compose a échoué.');
        }
    }

    public function arreter(string $repertoire, string $copie): void
    {
        $this->verifierNomCopie($copie);

        Process::path($repertoire)
            ->timeout(120)
            ->run(['docker', 'compose', '-p', $copie, 'down', '--remove-orphans']);
    }

    public function verifierNomCopie(string $copie): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $copie) !== 1) {
            throw new LancementEchoue('Le nom de la copie est invalide pour Docker Compose.');
        }
    }
}
