<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use JsonException;

/**
 * Interroge le service web sur le réseau de la copie, avant de la déclarer en ligne.
 * Seule une adresse privée renvoyée par Docker est contactée.
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

    public function attendre(string $hote, int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new LancementEchoue('Le port du service web est invalide.');
        }

        if (! $this->ipv4Privee($hote)) {
            throw new LancementEchoue('L\'adresse du service web est refusée.');
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

        if ($reponse === false && ! isset($http_response_header[0])) {
            return false;
        }

        return isset($http_response_header[0])
            && preg_match('#^HTTP/\d(?:\.\d)? \d{3}#', $http_response_header[0]) === 1;
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
