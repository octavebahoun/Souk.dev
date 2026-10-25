<?php

declare(strict_types=1);

namespace App\Comptes;

use App\Models\User;
use App\Reputation\BilanDev;
use App\Reputation\CalculReputation;
use App\Reputation\CalculSoukScore;
use App\Reputation\ConstruireBilan;

/**
 * Partie calculée du profil public : stats, réputation (XP, niveau, badges) et Souk Score.
 * Toujours tirée des actions validées par quelqu'un d'autre, jamais modifiable à la main.
 */
final readonly class ProfilCalcule
{
    public function __construct(
        private ConstruireBilan $bilan = new ConstruireBilan,
        private CalculReputation $reputation = new CalculReputation,
        private CalculSoukScore $soukScore = new CalculSoukScore,
    ) {}

    /**
     * @return array{stats: array<string, int|float|null>, reputation: array{xp: int, niveau: string, badges: list<string>}, souk_score: array<string, int>}
     */
    public function pour(User $dev): array
    {
        return $this->depuisBilan($this->bilan->pour($dev));
    }

    /**
     * Recalcule et range la copie qui sert au tri de la recherche de devs.
     *
     * @return array{stats: array<string, int|float|null>, reputation: array{xp: int, niveau: string, badges: list<string>}, souk_score: array<string, int>}
     */
    public function rafraichir(User $dev): array
    {
        $profil = $this->pour($dev);

        $dev->forceFill([
            'souk_score' => $profil['souk_score']['total'],
            'profil_calcule' => $profil,
            'profil_calcule_le' => now(),
        ])->saveQuietly();

        return $profil;
    }

    /**
     * Copie rangée si elle existe (recherche de devs), sinon calcul en direct.
     *
     * @return array{stats: array<string, int|float|null>, reputation: array{xp: int, niveau: string, badges: list<string>}, souk_score: array<string, int>}
     */
    public function range(User $dev): array
    {
        $profil = $dev->profil_calcule;

        return is_array($profil) && isset($profil['stats'], $profil['reputation'], $profil['souk_score'])
            ? $profil
            : $this->rafraichir($dev);
    }

    /**
     * @return array{stats: array<string, int|float|null>, reputation: array{xp: int, niveau: string, badges: list<string>}, souk_score: array<string, int>}
     */
    private function depuisBilan(BilanDev $bilan): array
    {
        $reputation = $this->reputation->pour($bilan);
        $score = $this->soukScore->pour($bilan);
        $moyenne = $bilan->noteMoyenne();

        return [
            'stats' => [
                'bugs_resolus' => $bilan->bugsResolus,
                'applis_publiees' => $bilan->applisPubliees,
                'missions_terminees' => $bilan->missionsTerminees(),
                'note_moyenne' => $moyenne === null ? null : round($moyenne, 2),
                'nb_notes' => $bilan->missionsTerminees(),
            ],
            'reputation' => [
                'xp' => $reputation->xp,
                'niveau' => $reputation->niveau,
                'badges' => $reputation->badges,
            ],
            'souk_score' => [
                'total' => $score->total,
                'entraide' => $score->entraide,
                'projets' => $score->projets,
                'clients' => $score->clients,
                'securite' => $score->securite,
                'activite' => $score->activite,
            ],
        ];
    }
}
