<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\RecommandationTaille;
use PHPUnit\Framework\TestCase;

class RecommandationTailleTest extends TestCase
{
    private RecommandationTaille $recommandation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recommandation = new RecommandationTaille;
    }

    public function test_450_mo_recommande_la_moyenne(): void
    {
        $this->assertSame('moyenne', $this->recommandation->depuisPic(450));
    }

    public function test_le_seuil_garde_vingt_pour_cent_de_marge(): void
    {
        $this->assertSame('petite', $this->recommandation->depuisPic(100));
        $this->assertSame('petite', $this->recommandation->depuisPic(409));
        $this->assertSame('moyenne', $this->recommandation->depuisPic(410));
        $this->assertSame('moyenne', $this->recommandation->depuisPic(819));
        $this->assertSame('grande', $this->recommandation->depuisPic(820));
        $this->assertSame('grande', $this->recommandation->depuisPic(900));
        $this->assertSame('grande', $this->recommandation->depuisPic(2000));
    }

    public function test_refuse_un_pic_negatif(): void
    {
        $this->expectException(LancementEchoue::class);

        $this->recommandation->depuisPic(-1);
    }
}
