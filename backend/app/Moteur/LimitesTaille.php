<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Plafond de chaque conteneur d'une copie. Compose ne sait pas limiter
 * le projet entier : web, base et les autres services reçoivent la même
 * formule. Le fichier ajouté au lancement remplace un mem_limit ou un
 * cpus écrit dans le dépôt.
 *
 * Le même fichier impose le durcissement (voir Durcissement) : pids_limit,
 * cap_drop ALL, no-new-privileges et le réseau propre à la copie. Compose
 * remplace les valeurs simples (pids_limit) et fusionne les listes
 * (cap_drop, security_opt) : le dépôt ne peut donc pas les assouplir, et
 * VerificateurCompose refuse ses cap_add et security_opt hors liste.
 */
final class LimitesTaille
{
    /**
     * @var array<string, array{memoire: string, coeurs: string}>
     */
    private const FORMULES = [
        'petite' => ['memoire' => '512m', 'coeurs' => '0.5'],
        'moyenne' => ['memoire' => '1g', 'coeurs' => '1'],
        'grande' => ['memoire' => '2g', 'coeurs' => '2'],
    ];

    public static function connue(string $taille): bool
    {
        return isset(self::FORMULES[$taille]);
    }

    /**
     * Plafond mémoire de la formule, en Mo (512m, 1g, 2g).
     */
    public static function plafondMo(string $taille): int
    {
        if (! self::connue($taille)) {
            throw new LancementEchoue('La taille doit être petite, moyenne ou grande.');
        }

        return match (self::FORMULES[$taille]['memoire']) {
            '512m' => 512,
            '1g' => 1024,
            '2g' => 2048,
            default => throw new LancementEchoue('Plafond mémoire inconnu.'),
        };
    }

    /**
     * @param  list<string>  $services
     */
    public function ecrire(string $repertoire, array $services, string $taille): string
    {
        if (! self::connue($taille)) {
            throw new LancementEchoue('La taille doit être petite, moyenne ou grande.');
        }

        if ($services === []) {
            throw new LancementEchoue('docker-compose.yml ne déclare aucun service.');
        }

        $formule = self::FORMULES[$taille];
        $pids = Durcissement::pids($taille);
        $capacites = Durcissement::capacites();
        $lignes = ['services:'];

        foreach ($services as $service) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $service) !== 1) {
                throw new LancementEchoue('Un nom de service empêche d\'appliquer les limites.');
            }

            $lignes[] = "  {$service}:";
            // Remplace le restart du dépôt : une copie suspendue (arrêtée) ne repart pas seule.
            $lignes[] = '    restart: unless-stopped';
            $lignes[] = "    mem_limit: {$formule['memoire']}";
            $lignes[] = "    memswap_limit: {$formule['memoire']}";
            $lignes[] = "    cpus: \"{$formule['coeurs']}\"";
            $lignes[] = '    deploy:';
            $lignes[] = '      resources:';
            $lignes[] = '        limits:';
            $lignes[] = "          cpus: \"{$formule['coeurs']}\"";
            $lignes[] = "          memory: {$formule['memoire']}";
            // Compose refuse un pids_limit différent de deploy.resources.limits.pids : on écrit les deux.
            $lignes[] = "          pids: {$pids}";
            $lignes[] = "    pids_limit: {$pids}";
            $lignes[] = '    cap_drop:';
            $lignes[] = '      - ALL';

            if ($capacites !== []) {
                $lignes[] = '    cap_add:';

                foreach ($capacites as $capacite) {
                    $lignes[] = "      - {$capacite}";
                }
            }

            $lignes[] = '    security_opt:';
            $lignes[] = '      - "'.Durcissement::SECURITE.'"';
            $lignes[] = '    networks:';
            $lignes[] = '      '.Durcissement::RESEAU.': {}';
        }

        $lignes[] = 'networks:';
        $lignes[] = '  '.Durcissement::RESEAU.':';
        $lignes[] = '    driver: bridge';

        $chemin = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml';
        $yaml = implode("\n", $lignes)."\n";

        if (file_put_contents($chemin, $yaml) === false) {
            throw new LancementEchoue('Impossible d\'écrire les limites de la copie.');
        }

        return $chemin;
    }
}
