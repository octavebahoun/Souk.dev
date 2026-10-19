<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\SourceCopieTest;
use PHPUnit\Framework\TestCase;

class SourceCopieTestTest extends TestCase
{
    public function test_l_appli_jointe_passe_avant_le_depot(): void
    {
        $this->assertSame(
            ['depot_url' => 'https://github.com/auteur/appli', 'taille' => 'moyenne'],
            SourceCopieTest::choisir('https://github.com/auteur/appli', 'moyenne', 'https://github.com/auteur/autre'),
        );
    }

    public function test_une_appli_de_taille_inconnue_tourne_en_petite(): void
    {
        $this->assertSame('petite', SourceCopieTest::choisir('https://github.com/auteur/appli', 'geante', null)['taille']);
    }

    public function test_sans_appli_le_depot_joint_tourne_en_petite(): void
    {
        $this->assertSame(
            ['depot_url' => 'https://github.com/wasfade/momo', 'taille' => 'petite'],
            SourceCopieTest::choisir(null, null, 'https://github.com/wasfade/momo'),
        );
    }

    public function test_refuse_un_depot_qui_n_est_pas_github(): void
    {
        $this->expectException(DepotInvalide::class);

        SourceCopieTest::choisir(null, null, 'https://gitlab.com/wasfade/momo');
    }

    public function test_refuse_une_discussion_sans_rien_de_joint(): void
    {
        $this->expectException(DepotInvalide::class);
        $this->expectExceptionMessage('ni appli ni dépôt');

        SourceCopieTest::choisir(null, null, null);
    }
}
