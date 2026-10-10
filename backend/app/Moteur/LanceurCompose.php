<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\Process;

class LanceurCompose
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

        (new VerificateurCompose)->verifier($contenu);

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

    public function lancer(string $repertoire, string $copie, string $taille = 'petite'): void
    {
        $this->verifierNomCopie($copie);

        $compose = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml';
        $contenu = is_file($compose) ? file_get_contents($compose) : false;

        if ($contenu === false || trim($contenu) === '') {
            throw new LancementEchoue('docker-compose.yml est absent à la racine du dépôt.');
        }

        $services = $this->nomsServices($contenu);

        if (is_file($repertoire.DIRECTORY_SEPARATOR.'docker-compose.backend.yml')) {
            foreach (['soukdev-db', 'soukdev-api'] as $reserve) {
                if (in_array($reserve, $services, true)) {
                    throw new LancementEchoue("Le service « {$reserve} » est réservé au backend intégré.");
                }
            }

            $services = array_merge($services, ['soukdev-db', 'soukdev-api']);
        }

        (new LimitesTaille)->ecrire($repertoire, $services, $taille);

        $env = $repertoire.DIRECTORY_SEPARATOR.'.env';

        $resultat = Process::path($repertoire)
            ->timeout((int) config('moteur.compose_timeout'))
            ->run([
                ...$this->argumentsCompose($repertoire, $copie),
                '--env-file', $env,
                'up', '-d', '--build',
            ]);

        if ($resultat->failed()) {
            $sortie = trim($resultat->errorOutput().' '.$resultat->output());

            if (str_contains($sortie, 'not found') || $resultat->exitCode() === 127) {
                throw new LancementEchoue('Docker Compose est introuvable. Installez Docker pour lancer une copie.');
            }

            throw new LancementEchoue('Le lancement Docker Compose a échoué.');
        }
    }

    public function adresseService(string $repertoire, string $copie, string $service): string
    {
        $this->verifierNomCopie($copie);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $service) !== 1) {
            throw new LancementEchoue('Le nom du service web est invalide.');
        }

        $liste = Process::path($repertoire)
            ->timeout(30)
            ->run(['docker', 'compose', '-p', $copie, 'ps', '-q', $service]);

        if ($liste->failed()) {
            throw new LancementEchoue("Impossible de trouver le conteneur du service « {$service} ».");
        }

        $id = strtok(trim($liste->output()), "\n") ?: '';

        if (preg_match('/^[a-f0-9]{12,64}$/', $id) !== 1) {
            throw new LancementEchoue("Le service « {$service} » n'a pas de conteneur.");
        }

        $inspection = Process::timeout(30)->run([
            'docker', 'inspect', '-f', '{{json .NetworkSettings.Networks}}', $id,
        ]);

        if ($inspection->failed()) {
            throw new LancementEchoue("Impossible de lire l'adresse du service « {$service} ».");
        }

        return (new SondeServiceWeb)->adresseDepuisInspection($inspection->output());
    }

    public function arreter(string $repertoire, string $copie): void
    {
        $this->verifierNomCopie($copie);

        Process::path($repertoire)
            ->timeout(120)
            ->run(['docker', 'compose', '-p', $copie, 'down', '--remove-orphans']);
    }

    /**
     * @return list<string>
     */
    public function argumentsCompose(string $repertoire, string $copie): array
    {
        $this->verifierNomCopie($copie);

        $arguments = [
            'docker', 'compose', '-p', $copie,
            '-f', 'docker-compose.yml',
            '-f', 'docker-compose.soukdev.yml',
        ];

        if (is_file($repertoire.DIRECTORY_SEPARATOR.'docker-compose.backend.yml')) {
            $arguments[] = '-f';
            $arguments[] = 'docker-compose.backend.yml';
        }

        return $arguments;
    }

    public function verifierNomCopie(string $copie): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $copie) !== 1) {
            throw new LancementEchoue('Le nom de la copie est invalide pour Docker Compose.');
        }
    }
}
