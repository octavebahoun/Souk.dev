<?php

namespace Tests\Feature\Moteur;

use App\Moteur\RouteurCopies;
use Tests\TestCase;

/**
 * Question de Caddy (certificats à la demande) avant de prendre un certificat pour un nom.
 */
class CertificatCopieTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dossier = sys_get_temp_dir().'/soukdev-tls-'.bin2hex(random_bytes(4));
        config(['moteur.proxy_routes' => $this->dossier, 'moteur.url_copie' => 'https://{copie}.souk-apps.duckdns.org']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dossier));

        parent::tearDown();
    }

    public function test_autorise_le_certificat_d_une_copie_servie(): void
    {
        RouteurCopies::depuisConfig()->publier('d42', '172.18.0.3', 8080);

        $this->get('/tls/copie?domain=d42.souk-apps.duckdns.org')->assertOk();
    }

    public function test_refuse_un_nom_sans_copie_en_404(): void
    {
        RouteurCopies::depuisConfig()->publier('d42', '172.18.0.3', 8080);

        $this->get('/tls/copie?domain=d43.souk-apps.duckdns.org')->assertNotFound();
    }

    public function test_refuse_une_demande_sans_domaine_en_404(): void
    {
        $this->get('/tls/copie')->assertNotFound();
        $this->get('/tls/copie?domain[]=d42.souk-apps.duckdns.org')->assertNotFound();
    }
}
