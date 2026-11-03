<?php

namespace App\Securite;

/**
 * Les copies font tourner le code de leurs auteurs. Servies sous le domaine du cookie de session
 * (ou sous celui du front ou de l'API), elles recevraient la session des membres qui les ouvrent :
 * leur auteur pourrait la rejouer. Elles vivent donc sur un domaine à part (DOMAINE_COPIES).
 */
class IsolementCopies
{
    /**
     * Décrit le défaut de configuration, ou null si les copies sont bien isolées.
     *
     * @param  list<string>  $urlsPlateforme  adresses du front et de l'API
     */
    public static function probleme(string $urlCopie, ?string $domaineSession, array $urlsPlateforme): ?string
    {
        $hoteCopie = self::hote(str_replace('{copie}', 'copie', $urlCopie));

        if ($hoteCopie === null) {
            return 'L\'adresse des copies (MOTEUR_URL_COPIE) est invalide.';
        }

        $domaines = array_filter([
            ltrim((string) $domaineSession, '.'),
            ...array_map(self::hote(...), $urlsPlateforme),
        ]);

        // Domaine commun des copies : l'adresse sans le nom de la copie (x.souk-apps.net → souk-apps.net).
        $baseCopies = str_contains($hoteCopie, '.') ? substr($hoteCopie, strpos($hoteCopie, '.') + 1) : $hoteCopie;

        foreach ($domaines as $domaine) {
            $domaine = strtolower((string) $domaine);

            if ($hoteCopie === $domaine || str_ends_with($hoteCopie, '.'.$domaine)) {
                return "Les copies ({$hoteCopie}) partagent le domaine {$domaine} de la plateforme : la session des membres leur serait envoyée. Servez-les sur un domaine à part (DOMAINE_COPIES).";
            }

            // L'inverse aussi : la plateforme sous le domaine des copies (DOMAINE=app.souk.dev,
            // DOMAINE_COPIES=souk.dev) laisserait une copie poser des cookies sur la plateforme.
            if ($domaine === $baseCopies || str_ends_with($domaine, '.'.$baseCopies)) {
                return "La plateforme ({$domaine}) est sous le domaine des copies ({$baseCopies}) : une copie pourrait lui poser des cookies. Prenez deux domaines distincts (DOMAINE et DOMAINE_COPIES).";
            }
        }

        return null;
    }

    private static function hote(string $url): ?string
    {
        $hote = parse_url($url, PHP_URL_HOST);

        return is_string($hote) && $hote !== '' ? strtolower($hote) : null;
    }
}
