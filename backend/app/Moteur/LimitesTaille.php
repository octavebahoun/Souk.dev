<?php

namespace App\Moteur;

use App\Moteur\Exceptions\LancementEchoue;

/**
 * Plafond de chaque conteneur d'une copie. Compose ne sait pas limiter
 * le projet entier : web, base et les autres services reçoivent la même
 * formule. Le fichier ajouté au lancement remplace un mem_limit ou un
 * cpus écrit dans le dépôt.
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
        $lignes = ['services:'];

        foreach ($services as $service) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $service) !== 1) {
                throw new LancementEchoue('Un nom de service empêche d\'appliquer les limites.');
            }

            $lignes[] = "  {$service}:";
            $lignes[] = "    mem_limit: {$formule['memoire']}";
            $lignes[] = "    memswap_limit: {$formule['memoire']}";
            $lignes[] = "    cpus: \"{$formule['coeurs']}\"";
            $lignes[] = '    deploy:';
            $lignes[] = '      resources:';
            $lignes[] = '        limits:';
            $lignes[] = "          cpus: \"{$formule['coeurs']}\"";
            $lignes[] = "          memory: {$formule['memoire']}";
        }

        $chemin = $repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml';
        $yaml = implode("\n", $lignes)."\n";

        if (file_put_contents($chemin, $yaml) === false) {
            throw new LancementEchoue('Impossible d\'écrire les limites de la copie.');
        }

        return $chemin;
    }
}
