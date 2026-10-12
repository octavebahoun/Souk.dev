<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Reputation\BilanDev;
use App\Reputation\CalculSoukScore;
use PHPUnit\Framework\TestCase;

class CalculSoukScoreTest extends TestCase
{
    public function test_le_cas_dafi_dossou(): void
    {
        $score = (new CalculSoukScore)->pour(new BilanDev(
            bugsResolus: 14,
            applisPubliees: 1,
            applisSecurisees: 1,
            notesMissions: [5, 5, 5, 4],
            actionsRecentes: 7,
        ));

        $this->assertSame(250, $score->entraide);  // 14 × 25, plafonné
        $this->assertSame(50, $score->projets);    // 1 × 50
        $this->assertSame(243, $score->clients);   // 4,75 / 5 × 150 = 143, + 4 × 25
        $this->assertSame(150, $score->securite);  // 1 appli sur 1 au badge Security Checked
        $this->assertSame(105, $score->activite);  // 7 × 15
        $this->assertSame(798, $score->total);
    }

    public function test_un_bilan_vide_donne_zero(): void
    {
        $score = (new CalculSoukScore)->pour(new BilanDev);

        $this->assertSame(0, $score->total);
        $this->assertSame(0, $score->entraide);
        $this->assertSame(0, $score->projets);
        $this->assertSame(0, $score->clients);
        $this->assertSame(0, $score->securite);
        $this->assertSame(0, $score->activite);
    }

    public function test_les_plafonds_donnent_mille_pile(): void
    {
        $score = (new CalculSoukScore)->pour(new BilanDev(
            bugsResolus: 20,
            applisPubliees: 10,
            applisSecurisees: 10,
            notesMissions: [5, 5, 5, 5, 5],
            actionsRecentes: 20,
        ));

        $this->assertSame(250, $score->entraide);  // 500 ramené à 250
        $this->assertSame(200, $score->projets);   // 500 ramené à 200
        $this->assertSame(250, $score->clients);   // 150 + 125 ramené à 100
        $this->assertSame(150, $score->securite);
        $this->assertSame(150, $score->activite);  // 300 ramené à 150
        $this->assertSame(1000, $score->total);
    }

    public function test_la_securite_est_la_part_des_applis_verifiees(): void
    {
        $calcul = new CalculSoukScore;

        // Sans aucune appli publiée, la part ne veut rien dire : 0.
        $this->assertSame(0, $calcul->pour(new BilanDev(applisSecurisees: 0))->securite);
        $this->assertSame(0, $calcul->pour(new BilanDev(applisPubliees: 3))->securite);
        $this->assertSame(75, $calcul->pour(new BilanDev(applisPubliees: 2, applisSecurisees: 1))->securite);
        $this->assertSame(50, $calcul->pour(new BilanDev(applisPubliees: 3, applisSecurisees: 1))->securite);
    }

    public function test_la_part_clients_sans_note_est_nulle(): void
    {
        $score = (new CalculSoukScore)->pour(new BilanDev(bugsResolus: 2));

        $this->assertSame(0, $score->clients);
        $this->assertSame(50, $score->total);
    }

    public function test_une_mauvaise_moyenne_pese_moins(): void
    {
        $score = (new CalculSoukScore)->pour(new BilanDev(notesMissions: [1, 2]));

        // 1,5 / 5 × 150 = 45, plus 2 × 25
        $this->assertSame(95, $score->clients);
    }
}
