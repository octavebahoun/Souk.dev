<?php

namespace Tests\Unit\Moteur;

use App\Moteur\DepotGithub;
use App\Moteur\Exceptions\DepotInvalide;
use PHPUnit\Framework\TestCase;

class DepotGithubTest extends TestCase
{
    public function test_analyse_une_url_https(): void
    {
        $depot = DepotGithub::depuisUrl('https://github.com/excellence-team/cobaye');

        $this->assertSame('excellence-team', $depot->owner);
        $this->assertSame('cobaye', $depot->repo);
        $this->assertSame('https://github.com/excellence-team/cobaye.git', $depot->urlClone);
        $this->assertNull($depot->branche);
    }

    public function test_accepte_le_suffixe_git(): void
    {
        $depot = DepotGithub::depuisUrl('https://github.com/excellence-team/cobaye.git');

        $this->assertSame('https://github.com/excellence-team/cobaye.git', $depot->urlClone);
        $this->assertNull($depot->branche);
    }

    public function test_accepte_une_branche(): void
    {
        $depot = DepotGithub::depuisUrl('https://github.com/excellence-team/cobaye/tree/fix-momo');

        $this->assertSame('https://github.com/excellence-team/cobaye.git', $depot->urlClone);
        $this->assertSame('fix-momo', $depot->branche);
    }

    public function test_accepte_une_branche_avec_slash(): void
    {
        $depot = DepotGithub::depuisUrl('https://github.com/excellence-team/cobaye/tree/feat/paiement');

        $this->assertSame('feat/paiement', $depot->branche);
    }

    public function test_refuse_http(): void
    {
        $this->expectException(DepotInvalide::class);

        DepotGithub::depuisUrl('http://github.com/excellence-team/cobaye');
    }

    public function test_refuse_un_autre_hebergeur(): void
    {
        $this->expectException(DepotInvalide::class);

        DepotGithub::depuisUrl('https://gitlab.com/excellence-team/cobaye');
    }

    public function test_refuse_des_identifiants_dans_l_url(): void
    {
        $this->expectException(DepotInvalide::class);
        $this->expectExceptionMessage('identifiants');

        DepotGithub::depuisUrl('https://user:token@github.com/excellence-team/cobaye');
    }

    public function test_refuse_une_url_de_fichier(): void
    {
        $this->expectException(DepotInvalide::class);

        DepotGithub::depuisUrl('https://github.com/excellence-team/cobaye/blob/main/README.md');
    }
}
