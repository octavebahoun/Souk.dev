<?php

namespace App\Moteur;

use App\Moteur\Exceptions\DepotInvalide;

/**
 * D'où part la copie de test d'une discussion : l'appli jointe en priorité
 * (son dépôt et sa formule), sinon le depot_url joint, en formule petite.
 */
final class SourceCopieTest
{
    public const TAILLE_DEPOT = 'petite';

    /**
     * @return array{depot_url: string, taille: string}
     */
    public static function choisir(?string $depotAppli, ?string $tailleAppli, ?string $depotDiscussion): array
    {
        if (is_string($depotAppli) && $depotAppli !== '') {
            $taille = is_string($tailleAppli) && LimitesTaille::connue($tailleAppli) ? $tailleAppli : self::TAILLE_DEPOT;

            return ['depot_url' => $depotAppli, 'taille' => $taille];
        }

        if (is_string($depotDiscussion) && $depotDiscussion !== '') {
            // Refuse tout ce qui n'est pas un dépôt GitHub public avant de mettre la copie en file.
            DepotGithub::depuisUrl($depotDiscussion);

            return ['depot_url' => $depotDiscussion, 'taille' => self::TAILLE_DEPOT];
        }

        throw new DepotInvalide('La discussion n\'a ni appli ni dépôt joint : impossible de lancer une copie de test.');
    }
}
