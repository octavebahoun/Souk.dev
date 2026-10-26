<?php

namespace Tests\Feature;

use App\Jobs\AnalyserDependances;
use App\Jobs\AnalyserSecrets;
use App\Models\Appli;
use App\Models\User;
use App\Moteur\CloneurGithub;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\PublicationDepot;
use App\Securite\AnalyseurDepot;
use App\Securite\VerificationSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

/**
 * Sécurité avant publication : un secret bloque la publication, les failles des dépendances
 * donnent le badge orange, rien trouvé donne le badge vert ; l'auteur peut relancer.
 */
class SecuriteDepotTest extends TestCase
{
    use RefreshDatabase;

    private string $depot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = sys_get_temp_dir().'/soukdev-securite-'.bin2hex(random_bytes(4));
        mkdir($this->depot);
        file_put_contents($this->depot.'/config.js', "const cle = 'factice';\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->depot));

        parent::tearDown();
    }

    public function test_un_secret_bloque_la_verification_et_ne_revele_jamais_le_secret(): void
    {
        $this->mock(VerificationSecrets::class, fn (MockInterface $mock) => $mock->shouldReceive('secrets')->andReturn([
            ['fichier' => 'config.js', 'ligne' => 3, 'regle' => 'stripe-access-token', 'description' => 'Found a Stripe Access Token'],
        ]))->makePartial();
        $this->mock(PublicationDepot::class, fn (MockInterface $mock) => $mock->shouldReceive('lire')->andReturn([
            'service_web' => ['nom' => 'web', 'port' => 80], 'backend' => 'propre', 'variables' => [],
        ]));

        $this->actingAs(User::factory()->github()->create())
            ->postJson('/api/apps/verification', ['depot_url' => 'https://github.com/acme/pharmacie'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.depot_url.0', 'Un secret a été trouvé dans le dépôt : config.js, ligne 3 (stripe-access-token). Retirez-le, changez la clé concernée (elle est publique), puis réessayez.');
    }

    public function test_gitleaks_tourne_dans_un_conteneur_jetable_sans_reseau(): void
    {
        $commandes = [];
        Process::fake(function (PendingProcess $process) use (&$commandes) {
            $commandes[] = $process->command;

            return match ($process->command[1] ?? null) {
                'create' => Process::result('a29b35fac39bb4836ff8'),
                'start' => Process::result((string) json_encode([[
                    'File' => '/src/config.js', 'StartLine' => 3, 'RuleID' => 'stripe-access-token',
                    'Description' => 'Found a Stripe Access Token', 'Secret' => 'REDACTED', 'Match' => 'REDACTED',
                ]])),
                default => Process::result(),
            };
        });

        $secrets = (new AnalyseurDepot)->secrets($this->depot);

        $this->assertSame([['fichier' => 'config.js', 'ligne' => 3, 'regle' => 'stripe-access-token', 'description' => 'Found a Stripe Access Token']], $secrets);
        $this->assertContains('--network', $commandes[0]);
        $this->assertContains('--redact', $commandes[0]);
        $this->assertSame(['docker', 'cp', $this->depot.'/.', 'a29b35fac39bb4836ff8:/src'], $commandes[1]);
        $this->assertSame(['docker', 'rm', '-f', 'a29b35fac39bb4836ff8'], end($commandes), 'Le conteneur est toujours supprimé.');
    }

    public function test_trivy_ne_retient_que_les_failles_moyennes_et_plus(): void
    {
        Process::fake(fn (PendingProcess $process) => match ($process->command[1] ?? null) {
            'create' => Process::result('b39c46fad40cc5947009'),
            'start' => Process::result((string) json_encode(['Results' => [['Target' => 'package-lock.json', 'Vulnerabilities' => [
                ['VulnerabilityID' => 'CVE-2020-8203', 'PkgName' => 'lodash', 'InstalledVersion' => '4.17.15', 'Severity' => 'HIGH', 'FixedVersion' => '4.17.19'],
                ['VulnerabilityID' => 'CVE-2019-0001', 'PkgName' => 'minimist', 'InstalledVersion' => '0.0.8', 'Severity' => 'LOW', 'FixedVersion' => '0.2.1'],
                ['VulnerabilityID' => 'CVE-2021-23337', 'PkgName' => 'lodash', 'InstalledVersion' => '4.17.15', 'Severity' => 'CRITICAL', 'FixedVersion' => ''],
            ]]]])),
            default => Process::result(),
        });

        $problemes = (new AnalyseurDepot)->dependances($this->depot);

        $this->assertSame([
            ['identifiant' => 'CVE-2021-23337', 'paquet' => 'lodash', 'version' => '4.17.15', 'gravite' => 'critique', 'version_corrigee' => null],
            ['identifiant' => 'CVE-2020-8203', 'paquet' => 'lodash', 'version' => '4.17.15', 'gravite' => 'haute', 'version_corrigee' => '4.17.19'],
        ], $problemes);
    }

    public function test_l_analyse_des_dependances_donne_le_badge(): void
    {
        $appli = Appli::factory()->create(['securite_statut' => 'en_cours', 'securite_jeton' => 'jeton-1']);
        $this->mock(CloneurGithub::class, fn (MockInterface $mock) => $mock->shouldReceive('cloner')->andReturnUsing(fn () => $this->copieDuDepot()));
        $this->mock(AnalyseurDepot::class, fn (MockInterface $mock) => $mock->shouldReceive('dependances')->andReturn([]));

        (new AnalyserDependances($appli->id, 'jeton-1'))->handle(app(CloneurGithub::class), app(AnalyseurDepot::class));

        $appli->refresh();
        $this->assertSame('verifie', $appli->securite_statut);
        $this->assertNotNull($appli->securite_analyse_le);
    }

    public function test_un_resultat_depasse_par_une_analyse_plus_recente_est_ignore(): void
    {
        $appli = Appli::factory()->create(['securite_statut' => 'en_cours', 'securite_jeton' => 'jeton-2']);
        $this->mock(AnalyseurDepot::class, fn (MockInterface $mock) => $mock->shouldNotReceive('dependances'));

        (new AnalyserDependances($appli->id, 'jeton-1'))->handle(app(CloneurGithub::class), app(AnalyseurDepot::class));

        $this->assertSame('en_cours', $appli->refresh()->securite_statut);
    }

    public function test_l_auteur_relance_l_analyse_et_les_autres_non(): void
    {
        Queue::fake();
        $appli = Appli::factory()->create(['securite_statut' => 'problemes']);

        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/securite")
            ->assertAccepted()
            ->assertJsonPath('statut', 'en_cours');
        Queue::assertPushed(AnalyserDependances::class, fn (AnalyserDependances $job) => $job->appliId === $appli->id && $job->jeton === $appli->refresh()->securite_jeton);

        $this->actingAs(User::factory()->github()->create())->postJson("/api/apps/{$appli->id}/securite")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/apps/{$appli->id}/securite")->assertUnauthorized();
    }

    public function test_sans_reponse_du_moteur_la_publication_n_a_pas_lieu(): void
    {
        Queue::fake();
        config(['moteur.delai_analyse_secrets' => 0]);

        $this->expectException(ServiceUnavailableHttpException::class);
        app(VerificationSecrets::class)->secrets('https://github.com/acme/pharmacie');
    }

    public function test_une_analyse_ratee_ne_vaut_pas_une_analyse_propre(): void
    {
        $this->mock(CloneurGithub::class, fn (MockInterface $mock) => $mock->shouldReceive('cloner')->andThrow(new DepotInvalide('Impossible de cloner le dépôt GitHub public.')));

        (new AnalyserSecrets('https://github.com/acme/pharmacie', 'securite:essai'))->handle(app(CloneurGithub::class), new AnalyseurDepot);

        $this->assertSame('erreur', Cache::get('securite:essai')['statut']);
    }

    private function copieDuDepot(): string
    {
        $copie = sys_get_temp_dir().'/soukdev-securite-copie-'.bin2hex(random_bytes(4));
        mkdir($copie);

        return $copie;
    }
}
