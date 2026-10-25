<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\RouteurCopies;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RouteurCopiesTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dossier = sys_get_temp_dir().'/soukdev-routes-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dossier));

        parent::tearDown();
    }

    private function routeur(string $url = 'https://{copie}.soukdev.com'): RouteurCopies
    {
        return new RouteurCopies($this->dossier, $url);
    }

    public function test_publier_ouvre_le_sous_domaine_vers_le_seul_service_web(): void
    {
        $chemin = $this->routeur()->publier('d42', '172.18.0.3', 8080);

        $this->assertSame($this->dossier.'/d42.yml', $chemin);
        $this->assertSame(<<<'YAML'
            http:
              routers:
                d42:
                  rule: "Host(`d42.soukdev.com`)"
                  service: d42
              services:
                d42:
                  loadBalancer:
                    servers:
                      - url: "http://172.18.0.3:8080"

            YAML, file_get_contents($chemin));
        $this->assertFileDoesNotExist($chemin.'.tmp');
    }

    public function test_une_adresse_ipv6_est_entre_crochets(): void
    {
        $chemin = $this->routeur()->publier('d42', 'fd00::3', 80);

        $this->assertStringContainsString('url: "http://[fd00::3]:80"', (string) file_get_contents($chemin));
    }

    public function test_retirer_supprime_la_route_et_ne_fait_rien_sans_route(): void
    {
        $routeur = $this->routeur();
        $chemin = $routeur->publier('d42', '172.18.0.3', 80);

        $routeur->retirer('d42');
        $routeur->retirer('d42');

        $this->assertFileDoesNotExist($chemin);
    }

    public function test_url_reprend_le_modele(): void
    {
        $this->assertSame('http://d7.localhost', $this->routeur('http://{copie}.localhost')->url('d7'));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function entreesRefusees(): array
    {
        return [
            'nom avec saut de ligne' => ["d1\n  evil:", '172.18.0.3', 80],
            'nom avec tiret bas' => ['d_1', '172.18.0.3', 80],
            'nom vers un autre dossier' => ['../d1', '172.18.0.3', 80],
            'hôte qui n\'est pas une IP' => ['d1', 'evil.example', 80],
            'hôte avec injection' => ['d1', '172.18.0.3"', 80],
            'port nul' => ['d1', '172.18.0.3', 0],
            'port trop grand' => ['d1', '172.18.0.3', 70000],
        ];
    }

    #[DataProvider('entreesRefusees')]
    public function test_les_entrees_douteuses_sont_refusees(string $copie, string $hote, int $port): void
    {
        $this->expectException(LancementEchoue::class);

        try {
            $this->routeur()->publier($copie, $hote, $port);
        } finally {
            $this->assertDirectoryDoesNotExist($this->dossier);
        }
    }

    public function test_un_modele_d_url_qui_casserait_la_regle_est_refuse(): void
    {
        $this->expectException(LancementEchoue::class);

        $this->routeur('https://{copie}.soukdev.com`) || Host(`autre.soukdev.com')->publier('d1', '172.18.0.3', 80);
    }
}
