<?php

declare(strict_types=1);

namespace App\Reputation;

/**
 * XP cumulée depuis l'arrivée du dev, niveau et badges.
 * Barème et seuils : DECISIONS.md, ajout « réputation ».
 */
final readonly class CalculReputation
{
    private const XP_CORRECTIF_ACCEPTE = 50;

    private const XP_APPLI_PUBLIEE = 100;

    private const XP_APPLI_SECURISEE = 50;

    private const XP_MISSION_TERMINEE = 100;

    private const XP_PAR_POINT_DE_NOTE = 10;

    private const XP_EVENEMENT_PASSE = 50;

    /** Du plus haut au plus bas : le premier seuil atteint gagne. */
    private const NIVEAUX = ['expert' => 3000, 'confirme' => 1000, 'contributeur' => 200];

    public function pour(BilanDev $bilan): Reputation
    {
        $xp = $this->xp($bilan);

        return new Reputation($xp, $this->niveau($xp), $this->badges($bilan));
    }

    private function xp(BilanDev $bilan): int
    {
        $xp = self::XP_CORRECTIF_ACCEPTE * $bilan->bugsResolus
            + self::XP_APPLI_PUBLIEE * $bilan->applisPubliees
            + self::XP_APPLI_SECURISEE * $bilan->applisSecurisees
            + self::XP_EVENEMENT_PASSE * $bilan->evenementsPasses;

        foreach ($bilan->notesMissions as $note) {
            $xp += self::XP_MISSION_TERMINEE + self::XP_PAR_POINT_DE_NOTE * $note;
        }

        return $xp;
    }

    private function niveau(int $xp): string
    {
        foreach (self::NIVEAUX as $niveau => $seuil) {
            if ($xp >= $seuil) {
                return $niveau;
            }
        }

        return 'debutant';
    }

    /**
     * @return list<string>
     */
    private function badges(BilanDev $bilan): array
    {
        $gagnes = [
            'premier_correctif' => $bilan->bugsResolus >= 1,
            'chasseur_de_bugs' => $bilan->bugsResolus >= 10,
            'publie' => $bilan->applisPubliees >= 1,
            'fiable' => $bilan->missionsTerminees() >= 3 && $bilan->noteMoyenne() >= 4.5,
            'securise' => $bilan->applisSecurisees >= 1,
            'organisateur' => $bilan->evenementsPasses >= 1,
        ];

        return array_keys(array_filter($gagnes));
    }
}
