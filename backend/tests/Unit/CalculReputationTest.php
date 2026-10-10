<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Reputation\BilanDev;
use App\Reputation\CalculReputation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculReputationTest extends TestCase
{
    // Le profil des maquettes : 14 bugs, 1 appli « Security Checked », 4 missions notées, 7 actions récentes.
    private function afiDossou(): BilanDev
    {
        return new BilanDev(
            bugsResolus: 14,
            applisPubliees: 1,
            applisSecurisees: 1,
            notesMissions: [5, 5, 5, 4],
            actionsRecentes: 7,
        );
    }

    public function test_le_cas_dafi_dossou(): void
    {
        $reputation = (new CalculReputation)->pour($this->afiDossou());

        // 14 × 50 + 100 + 50 + (150 + 150 + 150 + 140)
        $this->assertSame(1440, $reputation->xp);
        $this->assertSame('confirme', $reputation->niveau);
        $this->assertSame(
            ['premier_correctif', 'chasseur_de_bugs', 'publie', 'fiable', 'securise'],
            $reputation->badges,
        );
    }

    public function test_un_bilan_vide_ne_donne_rien(): void
    {
        $reputation = (new CalculReputation)->pour(new BilanDev);

        $this->assertSame(0, $reputation->xp);
        $this->assertSame('debutant', $reputation->niveau);
        $this->assertSame([], $reputation->badges);
    }

    /**
     * @return list<array{int, int, string}>
     */
    public static function niveaux(): array
    {
        return [
            [0, 0, 'debutant'],
            [3, 150, 'debutant'],
            [4, 200, 'contributeur'],
            [19, 950, 'contributeur'],
            [20, 1000, 'confirme'],
            [59, 2950, 'confirme'],
            [60, 3000, 'expert'],
        ];
    }

    #[DataProvider('niveaux')]
    public function test_les_seuils_de_niveau(int $bugs, int $xpAttendue, string $niveauAttendu): void
    {
        $reputation = (new CalculReputation)->pour(new BilanDev(bugsResolus: $bugs));

        $this->assertSame($xpAttendue, $reputation->xp);
        $this->assertSame($niveauAttendu, $reputation->niveau);
    }

    public function test_chaque_action_compte_son_bareme(): void
    {
        $reputation = (new CalculReputation)->pour(new BilanDev(
            bugsResolus: 1,
            applisPubliees: 2,
            applisSecurisees: 1,
            notesMissions: [3],
            evenementsPasses: 1,
        ));

        // 50 + 200 + 50 + (100 + 30) + 50
        $this->assertSame(480, $reputation->xp);
    }

    public function test_le_badge_fiable_exige_trois_missions_et_une_moyenne_de_4_5(): void
    {
        $calcul = new CalculReputation;

        // Deux missions parfaites ne suffisent pas.
        $this->assertNotContains('fiable', $calcul->pour(new BilanDev(notesMissions: [5, 5]))->badges);

        // Trois missions, mais la moyenne tombe à 4,33.
        $this->assertNotContains('fiable', $calcul->pour(new BilanDev(notesMissions: [5, 4, 4]))->badges);

        // Une moyenne de 4,5 pile passe.
        $this->assertContains('fiable', $calcul->pour(new BilanDev(notesMissions: [5, 5, 4, 4]))->badges);
    }

    public function test_le_badge_chasseur_de_bugs_exige_dix_bugs(): void
    {
        $calcul = new CalculReputation;

        $this->assertSame(['premier_correctif'], $calcul->pour(new BilanDev(bugsResolus: 9))->badges);
        $this->assertSame(
            ['premier_correctif', 'chasseur_de_bugs'],
            $calcul->pour(new BilanDev(bugsResolus: 10))->badges,
        );
    }

    public function test_le_badge_organisateur_vient_dun_evenement_passe(): void
    {
        $badges = (new CalculReputation)->pour(new BilanDev(evenementsPasses: 1))->badges;

        $this->assertSame(['organisateur'], $badges);
    }
}
