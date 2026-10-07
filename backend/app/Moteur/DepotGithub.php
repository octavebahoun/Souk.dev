<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;

final readonly class DepotGithub
{
    public function __construct(
        public string $owner,
        public string $repo,
        public string $urlClone,
        public ?string $branche,
    ) {}

    public static function depuisUrl(string $url): self
    {
        $url = trim($url);

        if ($url === '') {
            throw new DepotInvalide('L\'URL du dépôt est vide.');
        }

        if (! str_starts_with($url, 'https://')) {
            throw new DepotInvalide('Seuls les dépôts GitHub publics en HTTPS sont acceptés.');
        }

        $parties = parse_url($url);

        if (! is_array($parties) || ($parties['host'] ?? '') !== 'github.com') {
            throw new DepotInvalide('Seuls les dépôts GitHub publics en HTTPS sont acceptés.');
        }

        if (! empty($parties['user']) || ! empty($parties['pass'])) {
            throw new DepotInvalide('L\'URL ne doit pas contenir d\'identifiants.');
        }

        $chemin = trim($parties['path'] ?? '', '/');
        $segments = $chemin === '' ? [] : explode('/', $chemin);

        if (count($segments) < 2) {
            throw new DepotInvalide('URL GitHub incomplète. Exemple : https://github.com/compte/depot');
        }

        $owner = $segments[0];
        $repo = (string) preg_replace('/\\.git$/i', '', $segments[1]);

        if (! self::identifiantValide($owner) || ! self::identifiantValide($repo)) {
            throw new DepotInvalide('Le compte ou le nom du dépôt GitHub est invalide.');
        }

        $branche = null;

        if (isset($segments[2])) {
            if ($segments[2] !== 'tree' || count($segments) < 4) {
                throw new DepotInvalide('URL GitHub non reconnue. Utilisez https://github.com/compte/depot ou …/tree/branche.');
            }

            $branche = implode('/', array_slice($segments, 3));

            if ($branche === '' || str_contains($branche, '..') || str_contains($branche, "\0")) {
                throw new DepotInvalide('Le nom de la branche est invalide.');
            }
        }

        return new self(
            owner: $owner,
            repo: $repo,
            urlClone: "https://github.com/{$owner}/{$repo}.git",
            branche: $branche,
        );
    }

    private static function identifiantValide(string $valeur): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $valeur) === 1
            && $valeur !== '.'
            && $valeur !== '..'
            && ! str_starts_with($valeur, '-');
    }
}
