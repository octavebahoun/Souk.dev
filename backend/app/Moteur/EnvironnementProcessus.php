<?php

namespace App\Moteur;

/**
 * Environnement des commandes git et docker lancées pour un dépôt d'inconnu. Rien n'est hérité
 * du processus PHP : sinon un docker-compose.yml qui écrit « ${SASPAY_SECRET_KEY} » ou liste
 * « APP_KEY » sans valeur recevrait les secrets de la plateforme (interpolation de Compose).
 * Seules passent les variables nécessaires à git et à la CLI Docker.
 */
final class EnvironnementProcessus
{
    /** @var list<string> */
    private const GARDEES = ['PATH', 'HOME', 'TMPDIR', 'DOCKER_HOST', 'DOCKER_CONFIG', 'DOCKER_CONTEXT', 'DOCKER_CERT_PATH', 'DOCKER_TLS_VERIFY'];

    /**
     * Symfony Process ajoute l'environnement courant aux variables fournies, sauf celles qui
     * valent false : chaque variable héritée est donc explicitement retirée.
     *
     * @param  array<string, string>  $ajouts
     * @return array<string, string|false>
     */
    public static function minimal(array $ajouts = []): array
    {
        $env = [];

        foreach (array_keys([...self::courant(), ...$_ENV, ...$_SERVER]) as $nom) {
            if (is_string($nom) && $nom !== '') {
                $env[$nom] = false;
            }
        }

        foreach (self::GARDEES as $nom) {
            $valeur = getenv($nom);

            if (is_string($valeur) && $valeur !== '') {
                $env[$nom] = $valeur;
            }
        }

        $env['PATH'] = is_string($env['PATH'] ?? null) ? $env['PATH'] : '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $env['LC_ALL'] = 'C';
        $env['LANG'] = 'C';
        $env['COMPOSE_ANSI'] = 'never';

        return [...$env, ...$ajouts];
    }

    /**
     * @return array<string, string>
     */
    private static function courant(): array
    {
        $variables = getenv();

        return is_array($variables) ? $variables : [];
    }
}
