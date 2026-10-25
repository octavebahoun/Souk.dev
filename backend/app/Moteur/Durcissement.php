<?php

namespace App\Moteur;

use Throwable;

/**
 * Réglages de sécurité imposés à chaque conteneur d'une copie, quel que soit
 * le docker-compose.yml du dev : plafond de processus, capacités Linux
 * retirées, no-new-privileges et réseau propre à la copie.
 *
 * Les capacités gardées sont le strict minimum pour que les images courantes
 * (nginx, postgres, node, php-fpm) démarrent : changer d'utilisateur
 * (gosu, su-exec), écrire dans leurs dossiers, écouter sur le port 80 et
 * arrêter leurs propres processus. Tout le reste (NET_RAW, MKNOD,
 * SYS_CHROOT, SETFCAP, SETPCAP, AUDIT_WRITE, FSETID…) est retiré.
 */
final class Durcissement
{
    /** Réseau Compose ajouté à chaque service. Docker le préfixe par le nom de la copie. */
    public const RESEAU = 'soukdev-copie';

    public const SECURITE = 'no-new-privileges:true';

    /** @var list<string> */
    public const CAPACITES_PAR_DEFAUT = [
        'CHOWN', 'DAC_OVERRIDE', 'FOWNER', 'KILL', 'NET_BIND_SERVICE', 'SETGID', 'SETUID',
    ];

    /** @var array<string, int> */
    public const PIDS_PAR_DEFAUT = [
        'petite' => 256,
        'moyenne' => 512,
        'grande' => 1024,
    ];

    /**
     * @return list<string>
     */
    public static function capacites(): array
    {
        $valeur = self::config('moteur.capacites_autorisees');

        if (! is_array($valeur)) {
            return self::CAPACITES_PAR_DEFAUT;
        }

        $capacites = [];

        foreach ($valeur as $capacite) {
            if (is_string($capacite) && preg_match('/^[A-Z_]+$/', $nom = self::normaliserCapacite($capacite)) === 1) {
                $capacites[] = $nom;
            }
        }

        return array_values(array_unique($capacites));
    }

    public static function pids(string $taille): int
    {
        $valeur = self::config('moteur.pids_limit');
        $pids = is_array($valeur) && isset($valeur[$taille]) ? (int) $valeur[$taille] : (self::PIDS_PAR_DEFAUT[$taille] ?? 0);

        return $pids > 0 ? $pids : (self::PIDS_PAR_DEFAUT[$taille] ?? 256);
    }

    /** Plus grand plafond de processus parmi les formules : aucun service ne peut le dépasser. */
    public static function pidsMaximum(): int
    {
        return max(array_map(self::pids(...), array_keys(self::PIDS_PAR_DEFAUT)));
    }

    /** « cap_net_raw » et « NET_RAW » désignent la même capacité. */
    public static function normaliserCapacite(string $capacite): string
    {
        $nom = strtoupper(trim($capacite));

        return str_starts_with($nom, 'CAP_') ? substr($nom, 4) : $nom;
    }

    public static function estSecuriteAutorisee(mixed $option): bool
    {
        if (! is_string($option)) {
            return false;
        }

        $option = strtolower(str_replace(' ', '', $option));

        return in_array($option, ['no-new-privileges', 'no-new-privileges:true', 'no-new-privileges=true'], true);
    }

    private static function config(string $cle): mixed
    {
        if (! function_exists('config')) {
            return null;
        }

        try {
            return config($cle);
        } catch (Throwable) {
            return null;
        }
    }
}
