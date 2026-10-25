<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\Process;
use JsonException;

/**
 * Interroge le service web sur le réseau de la copie, avant de la déclarer en ligne.
 * Seule une adresse privée renvoyée par Docker est contactée.
 *
 * Avec le réseau de la copie, la requête part d'un conteneur jetable branché sur ce
 * réseau : le moteur tourne lui-même dans un conteneur (ou vise une autre machine),
 * et Docker isole les réseaux entre eux.
 */
final class SondeServiceWeb
{
    /**
     * @param  (callable(string, int): bool)|null  $requete
     */
    public function __construct(
        private $requete = null,
        private int $tentatives = 30,
        private int $pauseMicrosecondes = 1_000_000,
    ) {}

    public const IMAGE_SONDE = 'curlimages/curl:8.11.1';

    public function attendre(string $hote, int $port, ?string $reseau = null): void
    {
        if ($port < 1 || $port > 65535) {
            throw new LancementEchoue('Le port du service web est invalide.');
        }

        if (! $this->ipv4Privee($hote)) {
            throw new LancementEchoue('L\'adresse du service web est refusée.');
        }

        if ($reseau !== null && $this->requete === null) {
            $this->attendreDepuisLeReseau($reseau, $hote, $port);

            return;
        }

        $tentatives = max(1, $this->tentatives);

        for ($essai = 1; $essai <= $tentatives; $essai++) {
            if ($this->interroge($hote, $port)) {
                return;
            }

            if ($essai < $tentatives && $this->pauseMicrosecondes > 0) {
                usleep($this->pauseMicrosecondes);
            }
        }

        throw new LancementEchoue("Le service web ne répond pas sur le port {$port}.");
    }

    /**
     * Une seule requête curl, qui réessaie elle-même : toute réponse HTTP compte,
     * même une erreur 500 ; seuls un refus de connexion ou un délai dépassé échouent.
     */
    private function attendreDepuisLeReseau(string $reseau, string $hote, int $port): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,127}$/', $reseau) !== 1) {
            throw new LancementEchoue('Le réseau de la copie est invalide.');
        }

        $tentatives = max(1, $this->tentatives);

        $resultat = Process::timeout($tentatives * 7 + 120)->run([
            'docker', 'run', '--rm',
            '--network', $reseau,
            '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges:true',
            '--read-only',
            '--memory', '32m',
            '--pids-limit', '16',
            $this->imageSonde(),
            '--silent', '--output', '/dev/null', '--write-out', '%{http_code}',
            '--connect-timeout', '2', '--max-time', '5',
            '--retry', (string) ($tentatives - 1), '--retry-delay', '1',
            '--retry-connrefused', '--retry-all-errors',
            sprintf('http://%s:%d/', $hote, $port),
        ]);

        if ($resultat->successful() && preg_match('/^[1-5]\d\d$/', trim($resultat->output())) === 1) {
            return;
        }

        if (str_contains($resultat->errorOutput(), 'Unable to find image') || str_contains($resultat->errorOutput(), 'pull access denied')) {
            throw new LancementEchoue('La sonde du service web n\'a pas pu démarrer (image indisponible).');
        }

        throw new LancementEchoue("Le service web ne répond pas sur le port {$port}.");
    }

    private function imageSonde(): string
    {
        $image = function_exists('config') ? config('moteur.image_sonde') : null;

        return is_string($image) && $image !== '' ? $image : self::IMAGE_SONDE;
    }

    public function adresseDepuisInspection(string $json): string
    {
        try {
            $reseaux = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LancementEchoue('Adresse du service web illisible.');
        }

        if (! is_array($reseaux) || array_is_list($reseaux)) {
            throw new LancementEchoue('Adresse du service web illisible.');
        }

        foreach ($reseaux as $reseau) {
            if (! is_array($reseau)) {
                continue;
            }

            $ip = $reseau['IPAddress'] ?? '';

            if (is_string($ip) && $this->ipv4Privee(trim($ip))) {
                return trim($ip);
            }
        }

        throw new LancementEchoue('Le service web n\'a pas d\'adresse sur le réseau de la copie.');
    }

    private function interroge(string $hote, int $port): bool
    {
        if ($this->requete !== null) {
            return (bool) ($this->requete)($hote, $port);
        }

        $contexte = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $reponse = @file_get_contents(sprintf('http://%s:%d/', $hote, $port), false, $contexte);
        $entetes = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : ($http_response_header ?? []);
        $statut = is_array($entetes) ? ($entetes[0] ?? null) : null;

        if ($reponse === false && ! is_string($statut)) {
            return false;
        }

        return is_string($statut)
            && preg_match('#^HTTP/\d(?:\.\d)? \d{3}#', $statut) === 1;
    }

    private function ipv4Privee(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if (str_starts_with($ip, '127.') || str_starts_with($ip, '0.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false;
    }
}
