<?php

declare(strict_types=1);

namespace App\Reputation;

/**
 * Score sur 1000 en cinq parts plafonnées : il mesure la qualité et l'activité
 * récente, là où l'XP cumule tout. Calcul : DECISIONS.md, ajout « Souk Score ».
 */
final readonly class CalculSoukScore
{
    private const ENTRAIDE_PAR_BUG = 25;

    private const ENTRAIDE_MAX = 250;

    private const PROJETS_PAR_APPLI = 50;

    private const PROJETS_MAX = 200;

    private const CLIENTS_NOTE_MAX = 150;

    private const CLIENTS_PAR_MISSION = 25;

    private const CLIENTS_MISSIONS_MAX = 100;

    private const SECURITE_MAX = 150;

    private const ACTIVITE_PAR_ACTION = 15;

    private const ACTIVITE_MAX = 150;

    public function pour(BilanDev $bilan): SoukScore
    {
        $entraide = min(self::ENTRAIDE_PAR_BUG * $bilan->bugsResolus, self::ENTRAIDE_MAX);
        $projets = min(self::PROJETS_PAR_APPLI * $bilan->applisPubliees, self::PROJETS_MAX);
        $clients = $this->clients($bilan);
        $securite = $this->securite($bilan);
        $activite = min(self::ACTIVITE_PAR_ACTION * $bilan->actionsRecentes, self::ACTIVITE_MAX);

        return new SoukScore(
            $entraide + $projets + $clients + $securite + $activite,
            $entraide,
            $projets,
            $clients,
            $securite,
            $activite,
        );
    }

    private function clients(BilanDev $bilan): int
    {
        $moyenne = $bilan->noteMoyenne();
        $note = $moyenne === null ? 0 : (int) round($moyenne / 5 * self::CLIENTS_NOTE_MAX);

        return $note + min(
            self::CLIENTS_PAR_MISSION * $bilan->missionsTerminees(),
            self::CLIENTS_MISSIONS_MAX,
        );
    }

    private function securite(BilanDev $bilan): int
    {
        if ($bilan->applisPubliees === 0) {
            return 0;
        }

        return (int) round($bilan->applisSecurisees / $bilan->applisPubliees * self::SECURITE_MAX);
    }
}
