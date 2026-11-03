<?php

namespace Tests\Unit\Securite;

use App\Securite\IsolementCopies;
use PHPUnit\Framework\TestCase;

class IsolementCopiesTest extends TestCase
{
    private const PLATEFORME = ['https://souk.dev', 'https://api.souk.dev'];

    public function test_des_copies_sur_un_domaine_a_part_sont_isolees(): void
    {
        $this->assertNull(IsolementCopies::probleme('https://{copie}.souk-apps.net', '.souk.dev', self::PLATEFORME));
    }

    public function test_des_copies_sous_le_domaine_de_la_session_sont_refusees(): void
    {
        $probleme = IsolementCopies::probleme('https://{copie}.souk.dev', '.souk.dev', self::PLATEFORME);

        $this->assertNotNull($probleme);
        $this->assertStringContainsString('DOMAINE_COPIES', $probleme);
    }

    public function test_des_copies_sous_l_api_sont_refusees_meme_sans_domaine_de_session(): void
    {
        $this->assertNotNull(IsolementCopies::probleme('https://{copie}.api.souk.dev', null, self::PLATEFORME));
        $this->assertNotNull(IsolementCopies::probleme('https://{copie}.SOUK.dev', null, self::PLATEFORME));
    }

    public function test_un_domaine_qui_finit_pareil_sans_en_etre_un_sous_domaine_passe(): void
    {
        $this->assertNull(IsolementCopies::probleme('https://{copie}.monsouk.dev', '.souk.dev', self::PLATEFORME));
    }

    public function test_la_plateforme_sous_le_domaine_des_copies_est_refusee(): void
    {
        $probleme = IsolementCopies::probleme('https://{copie}.souk.dev', '.app.souk.dev', ['https://app.souk.dev', 'https://api.app.souk.dev']);

        $this->assertNotNull($probleme);
        $this->assertStringContainsString('domaine des copies', $probleme);
    }
}
