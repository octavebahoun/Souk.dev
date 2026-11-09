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
                  entryPoints:
                    - websecure
                  tls:
                    certResolver: letsencrypt
              services:
                d42:
                  loadBalancer:
                    servers:
                      - url: "http://172.18.0.3:8080"

            YAML, file_get_contents($chemin));
        $this->assertFileDoesNotExist($chemin.'.tmp');
    }

    public function test_en_http_local_pas_de_tls(): void
    {
        $chemin = $this->routeur('http://{copie}.localhost')->publier('d42', '172.18.0.3', 8080);

        $contenu = (string) file_get_contents($chemin);
        $this->assertStringContainsString('Host(`d42.localhost`)', $contenu);
        $this->assertStringNotContainsString('websecure', $contenu);
        $this->assertStringNotContainsString('certResolver', $contenu);
    }

    public function test_derriere_un_proxy_qui_termine_le_tls_la_route_ne_demande_pas_de_certificat(): void
    {
        $routeur = new RouteurCopies($this->dossier, 'https://{copie}.soukdev.com', tls: false);
        $routeur->publier('d42', '172.18.0.3', 8080);

        $contenu = (string) file_get_contents($routeur->publierPageSuspendue('d42'));

        $this->assertStringContainsString("entryPoints:\n        - websecure\n", $contenu);
        $this->assertStringNotContainsString('tls:', $contenu);
        $this->assertStringNotContainsString('certResolver', $contenu);
    }

    public function test_sert_le_sous_domaine_d_une_copie_qui_a_sa_route(): void
    {
        $routeur = $this->routeur('https://{copie}.souk-apps.duckdns.org');
        $routeur->publier('d42', '172.18.0.3', 8080);

        $this->assertTrue($routeur->sert('d42.souk-apps.duckdns.org'));
        $this->assertTrue($routeur->sert('D42.Souk-Apps.DuckDNS.org'));
    }

    /**
     * Le proxy TLS prendrait un certificat pour chacun de ces noms : Let's Encrypt limite à 50
     * certificats par semaine, un nom inventé ne doit jamais en coûter un.
     */
    #[DataProvider('domainesNonServis')]
    public function test_ne_sert_pas_un_domaine_sans_copie(string $domaine): void
    {
        $routeur = $this->routeur('https://{copie}.souk-apps.duckdns.org');
        $routeur->publier('d42', '172.18.0.3', 8080);
        // Les routes de la plateforme (écrites par le service « init ») partagent le dossier.
        file_put_contents($this->dossier.'/plateforme.yml', "http:\n  routers:\n    front:\n      rule: \"Host(`soukdev.shop`)\"\n");

        $this->assertFalse($routeur->sert($domaine));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function domainesNonServis(): array
    {
        return [
            'copie sans route' => ['d43.souk-apps.duckdns.org'],
            'domaine racine des copies' => ['souk-apps.duckdns.org'],
            'sous-domaine d’une copie' => ['x.d42.souk-apps.duckdns.org'],
            'autre domaine' => ['d42.evil.example'],
            'suffixe trompeur' => ['d42.souk-apps.duckdns.org.evil.example'],
            'fichier hors du dossier' => ['..%2Fd42.souk-apps.duckdns.org'],
            'route de la plateforme' => ['plateforme.souk-apps.duckdns.org'],
        ];
    }

    public function test_une_copie_suspendue_affiche_la_page_de_la_plateforme(): void
    {
        $routeur = new RouteurCopies($this->dossier, 'https://{copie}.soukdev.com', 'http://api:8000');
        $routeur->publier('d42', '172.18.0.3', 8080);

        $chemin = $routeur->publierPageSuspendue('d42');

        $this->assertSame(<<<'YAML'
            http:
              routers:
                d42:
                  rule: "Host(`d42.soukdev.com`)"
                  service: d42
                  entryPoints:
                    - websecure
                  tls:
                    certResolver: letsencrypt
                  middlewares:
                    - d42-suspendue
              middlewares:
                d42-suspendue:
                  replacePath:
                    path: "/copie-suspendue"
              services:
                d42:
                  loadBalancer:
                    servers:
                      - url: "http://api:8000"

            YAML, file_get_contents($chemin));
    }

    public function test_une_adresse_de_page_suspendue_douteuse_est_refusee(): void
    {
        $this->expectException(LancementEchoue::class);

        (new RouteurCopies($this->dossier, 'https://{copie}.soukdev.com', "http://api:8000\"\n  evil: x"))->publierPageSuspendue('d42');
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
