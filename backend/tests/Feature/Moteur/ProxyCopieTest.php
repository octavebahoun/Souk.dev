<?php

namespace Tests\Feature\Moteur;

use App\Moteur\BackendIntegre;
use App\Moteur\CopieLocale;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use App\Moteur\MoteurDeploiement;
use App\Moteur\RouteurCopies;
use App\Moteur\SondeServiceWeb;
use Illuminate\Support\Facades\Process;
use Mockery\MockInterface;
use Tests\TestCase;

class ProxyCopieTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dossier = sys_get_temp_dir().'/soukdev-proxy-'.bin2hex(random_bytes(4));
        config(['moteur.proxy_routes' => $this->dossier, 'moteur.url_copie' => 'http://{copie}.localhost']);

        $this->mock(BackendIntegre::class, fn (MockInterface $mock) => $mock->shouldReceive('appliquer'));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dossier));

        parent::tearDown();
    }

    private function copie(): CopieLocale
    {
        return new CopieLocale('d9', '/tmp/d9', ['service_web' => ['nom' => 'web', 'port' => 3000]], [], false);
    }

    public function test_la_route_s_ouvre_quand_le_service_web_repond(): void
    {
        $this->mock(LanceurCompose::class, function (MockInterface $mock) {
            $mock->shouldReceive('lancer')->once();
            $mock->shouldReceive('adresseService')->once()->with('/tmp/d9', 'd9', 'web')->andReturn('172.20.0.5');
            $mock->shouldNotReceive('arreter');
        });
        $this->app->instance(SondeServiceWeb::class, new SondeServiceWeb(fn (string $hote, int $port) => $hote === '172.20.0.5' && $port === 3000, 1, 0));

        $this->app->make(MoteurDeploiement::class)->mettreEnLigne($this->copie());

        $this->assertStringContainsString('url: "http://172.20.0.5:3000"', (string) file_get_contents($this->dossier.'/d9.yml'));
        $this->assertStringContainsString('Host(`d9.localhost`)', (string) file_get_contents($this->dossier.'/d9.yml'));
    }

    public function test_sans_reponse_du_service_web_aucune_route_n_est_ouverte(): void
    {
        $this->mock(LanceurCompose::class, function (MockInterface $mock) {
            $mock->shouldReceive('lancer')->once();
            $mock->shouldReceive('adresseService')->once()->andReturn('172.20.0.5');
            $mock->shouldReceive('arreter')->once();
        });
        $this->app->instance(SondeServiceWeb::class, new SondeServiceWeb(fn () => false, 1, 0));

        try {
            $this->app->make(MoteurDeploiement::class)->mettreEnLigne($this->copie());
            $this->fail('La mise en ligne aurait dû échouer.');
        } catch (LancementEchoue) {
            $this->assertFileDoesNotExist($this->dossier.'/d9.yml');
        }
    }

    public function test_l_arret_retire_la_route_avant_les_conteneurs(): void
    {
        RouteurCopies::depuisConfig()->publier('d9', '172.20.0.5', 3000);

        Process::fake(function () {
            $this->assertFileDoesNotExist($this->dossier.'/d9.yml');

            return Process::result();
        });

        $this->app->make(LanceurCompose::class)->arreter('/tmp/d9', 'd9');

        Process::assertRanTimes(fn ($process) => in_array('down', $process->command, true), 1);
    }
}
