<?php

namespace App\Moteur;

use InvalidArgumentException;

/**
 * Un démon Docker joint par sa valeur DOCKER_HOST : null pour le démon local (le socket que
 * seul le moteur voit), sinon unix:///… ou tcp://hôte:port. Passée en variable
 * d'environnement aux commandes, jamais sur la ligne de commande.
 */
final class MachineDocker implements MachineCible
{
    public function __construct(private readonly ?string $hoteDocker)
    {
        if (! self::hoteValide($hoteDocker)) {
            throw new InvalidArgumentException('Le démon Docker doit être unix:///… ou tcp://hôte:port.');
        }
    }

    public static function hoteValide(?string $hote): bool
    {
        return $hote === null || preg_match('/^(unix:\/\/\/\S+|tcp:\/\/[A-Za-z0-9.-]+:\d{1,5})$/', $hote) === 1;
    }

    /**
     * Démon du poste de développement : le socket local, ou un tcp:// vers cette machine
     * (localhost, 127.0.0.0/8, host.docker.internal). Tout autre tcp:// sans TLS exposerait
     * Docker sans authentification : refusé en production, faute de TLS en v1.
     */
    public static function hoteLocal(?string $hote): bool
    {
        if ($hote === null || str_starts_with($hote, 'unix://')) {
            return true;
        }

        if (preg_match('/^tcp:\/\/([^:]+):\d{1,5}$/', $hote, $correspondance) !== 1) {
            return false;
        }

        $nom = strtolower($correspondance[1]);

        if (in_array($nom, ['localhost', 'host.docker.internal', 'host.containers.internal'], true)) {
            return true;
        }

        return preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $nom) === 1;
    }

    /** « Le démon Docker ne répond pas : socket introuvable (démon arrêté ?). » */
    public static function cause(string $erreur): string
    {
        $causes = [
            'permission denied' => 'accès refusé au socket du démon',
            'no such file or directory' => 'socket introuvable (démon arrêté ?)',
            'connection refused' => 'le démon refuse la connexion',
            'timeout' => 'délai dépassé',
            'no such host' => 'adresse inconnue',
            'certificate' => 'certificat TLS refusé',
        ];
        foreach ($causes as $motif => $cause) {
            if (stripos($erreur, $motif) !== false) {
                return "Le démon Docker ne répond pas\u{00A0}: {$cause}.";
            }
        }
        $derniere = trim((string) last(preg_split('/\R/', $erreur) ?: ['']));

        return $derniere === '' ? 'Le démon Docker ne répond pas.' : mb_substr("Le démon Docker ne répond pas\u{00A0}: {$derniere}", 0, 200);
    }

    public function environnement(): array
    {
        return $this->hoteDocker === null ? [] : ['DOCKER_HOST' => $this->hoteDocker];
    }

    public function sonder(): SanteMachine
    {
        $resultat = Commande::isolee($this->environnement())->timeout(20)->run([
            'docker', 'info', '--format', '{{json .}}',
        ]);

        if ($resultat->failed()) {
            // docker info écrit son JSON client sur la sortie même quand le démon ne répond pas :
            // seule l'erreur compte, traduite quand la cause est connue.
            return SanteMachine::injoignable(self::cause(trim($resultat->errorOutput())));
        }

        $infos = json_decode($resultat->output(), true);

        if (! is_array($infos) || ! isset($infos['MemTotal']) || ! is_numeric($infos['MemTotal'])) {
            return SanteMachine::injoignable('La réponse du démon Docker est illisible.');
        }

        $version = $infos['ServerVersion'] ?? null;

        return new SanteMachine(
            true,
            (int) ceil(((float) $infos['MemTotal']) / 1024 / 1024),
            is_string($version) && $version !== '' ? mb_substr($version, 0, 32) : null,
        );
    }
}
