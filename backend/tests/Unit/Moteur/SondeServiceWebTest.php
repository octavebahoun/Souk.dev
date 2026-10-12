<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\SondeServiceWeb;
use PHPUnit\Framework\TestCase;

class SondeServiceWebTest extends TestCase
{
    public function test_lit_l_adresse_privee_du_conteneur(): void
    {
        $sonde = new SondeServiceWeb;

        $adresse = $sonde->adresseDepuisInspection('{"vide":{"IPAddress":""},"bridge":{"IPAddress":"172.18.0.2"}}');

        $this->assertSame('172.18.0.2', $adresse);
    }

    public function test_refuse_une_adresse_publique_ou_de_lien_local(): void
    {
        $sonde = new SondeServiceWeb;

        foreach (['{"n":{"IPAddress":"8.8.8.8"}}', '{"n":{"IPAddress":"169.254.169.254"}}', '{"n":{"IPAddress":"127.0.0.1"}}'] as $json) {
            try {
                $sonde->adresseDepuisInspection($json);
                $this->fail($json);
            } catch (LancementEchoue $exception) {
                $this->assertStringContainsString('adresse', $exception->getMessage());
            }
        }
    }

    public function test_attend_que_le_service_reponde(): void
    {
        $appels = 0;
        $sonde = new SondeServiceWeb(function (string $hote, int $port) use (&$appels): bool {
            $appels++;

            return $appels === 2 && $hote === '10.0.0.4' && $port === 3000;
        }, 3, 0);

        $sonde->attendre('10.0.0.4', 3000);

        $this->assertSame(2, $appels);
    }

    public function test_echoue_si_le_service_reste_muet(): void
    {
        $sonde = new SondeServiceWeb(fn (): bool => false, 2, 0);

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('ne répond pas sur le port 3000');

        $sonde->attendre('192.168.1.8', 3000);
    }

    public function test_refuse_de_contacter_une_adresse_qui_n_est_pas_privee(): void
    {
        $sonde = new SondeServiceWeb(function (): bool {
            $this->fail('La sonde ne doit pas contacter cette adresse.');
        }, 1, 0);

        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('refusée');

        $sonde->attendre('169.254.169.254', 80);
    }
}
