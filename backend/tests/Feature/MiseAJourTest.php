<?php

namespace Tests\Feature;

use App\Jobs\AnalyserDependances;
use App\Jobs\MettreAJourCopie;
use App\Jobs\VerifierVersion;
use App\Models\Appli;
use App\Models\Deploiement;
use App\Models\MiseAJour;
use App\Models\User;
use App\Models\VersionAppli;
use App\Moteur\BackendIntegre;
use App\Moteur\CloneurGithub;
use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\MiseAJourRetablie;
use App\Moteur\LanceurCompose;
use App\Moteur\MiseAJourCopie;
use App\Moteur\PublicationDepot;
use App\Moteur\RouteurCopies;
use App\Moteur\SauvegardeVolumes;
use App\Moteur\SondeServiceWeb;
use App\Notifications\AlerteAdmin;
use App\Notifications\MiseAJourTerminee;
use App\Notifications\NouvelleVersion;
use App\Securite\AnalyseurDepot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Mises à jour poussées : l'auteur publie une version (vérifiée comme une publication), ses
 * clients sont prévenus et l'appliquent quand ils veulent ; une mise à jour qui ne démarre pas
 * revient à la version précédente avec ses données.
 */
class MiseAJourTest extends TestCase
{
    use RefreshDatabase;

    private const COMMIT = 'a3f5c9d2e8b14f6a7c0d9e2b5a8f1c4d7e0b3a6f';

    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->racine = sys_get_temp_dir().'/soukdev-maj-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->racine);
        config(['moteur.copies' => $this->racine]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->racine);

        parent::tearDown();
    }

    public function test_l_auteur_publie_une_version_verifiee_et_ses_clients_sont_prevenus(): void
    {
        Notification::fake();
        Queue::fake([AnalyserDependances::class]);
        [$appli, $copie] = $this->copieEnLigne();
        $this->mock(PublicationDepot::class, fn (MockInterface $mock) => $mock->shouldReceive('verifier')
            ->withArgs(fn (string $url, ?string $ref) => $url === $appli->depot_url && $ref === 'v2')
            ->andReturn(['manifeste' => $this->manifeste(), 'commit' => self::COMMIT]));

        $this->actingAs($appli->auteur)
            ->postJson("/api/apps/{$appli->id}/versions", ['ref' => 'v2', 'notes' => 'Export PDF des ventes du jour.'])
            ->assertStatus(202)
            ->assertJsonPath('numero', 2)
            ->assertJsonPath('statut', 'verification');

        $version = $appli->versions()->where('numero', 2)->firstOrFail();
        $this->assertSame('publiee', $version->statut);
        $this->assertSame(self::COMMIT, $version->commit);
        Notification::assertSentTo($copie->utilisateur, NouvelleVersion::class);
        $this->actingAs($copie->utilisateur)
            ->getJson("/api/deployments/{$copie->id}")
            ->assertJsonPath('version', 1)
            ->assertJsonPath('version_disponible.numero', 2)
            ->assertJsonPath('version_disponible.notes', 'Export PDF des ventes du jour.');
        $this->getJson("/api/apps/{$appli->id}")->assertJsonPath('version', 2);
        // Le badge de sécurité suit la nouvelle version.
        Queue::assertPushed(AnalyserDependances::class);
    }

    public function test_une_version_avec_un_secret_est_refusee_avec_sa_raison(): void
    {
        [$appli] = $this->copieEnLigne();
        $this->mock(AnalyseurDepot::class, fn (MockInterface $mock) => $mock->shouldReceive('secrets')->andReturn([
            ['fichier' => 'config.js', 'ligne' => 3, 'regle' => 'stripe-access-token', 'description' => ''],
        ]));
        $this->mock(PublicationDepot::class, fn (MockInterface $mock) => $mock->shouldReceive('verifier')->andReturnUsing(function (string $url, ?string $ref, callable $aussi) {
            $aussi('/tmp');

            return ['manifeste' => $this->manifeste(), 'commit' => self::COMMIT];
        }));

        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/versions", ['notes' => 'Correctifs.'])->assertStatus(202);

        $version = $appli->versions()->where('numero', 2)->firstOrFail();
        $this->assertSame('refusee', $version->statut);
        $this->assertStringContainsString('config.js, ligne 3', (string) $version->erreur);
        // L'auteur voit aussi la version refusée ; le public ne voit que les versions publiées.
        $this->actingAs($appli->auteur)->getJson("/api/apps/{$appli->id}/versions")->assertJsonPath('data.*.statut', ['refusee', 'publiee']);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/apps/{$appli->id}/versions")->assertJsonPath('data.*.numero', [1]);
    }

    public function test_seul_l_auteur_publie_une_version_et_une_seule_se_verifie_a_la_fois(): void
    {
        Queue::fake();
        [$appli, $copie] = $this->copieEnLigne();

        $this->postJson("/api/apps/{$appli->id}/versions", ['notes' => 'Correctifs.'])->assertUnauthorized();
        $this->actingAs($copie->utilisateur)->postJson("/api/apps/{$appli->id}/versions", ['notes' => 'Correctifs.'])->assertForbidden();
        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/versions", ['notes' => ''])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/versions", ['ref' => '--upload-pack=x', 'notes' => 'Correctifs.'])->assertUnprocessable()->assertJsonValidationErrors('ref');
        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/versions", ['ref' => 'main', 'notes' => 'Correctifs.'])->assertStatus(202);
        $this->actingAs($appli->auteur)->postJson("/api/apps/{$appli->id}/versions", ['ref' => 'main', 'notes' => 'Encore.'])->assertConflict();

        Queue::assertPushed(VerifierVersion::class, 1);
    }

    public function test_le_client_lance_la_mise_a_jour_de_sa_copie_et_personne_d_autre(): void
    {
        Queue::fake();
        [$appli, $copie] = $this->copieEnLigne();
        $this->versionPubliee($appli, 2);

        $this->actingAs(User::factory()->create())->postJson("/api/deployments/{$copie->id}/mise-a-jour")->assertNotFound();
        $this->actingAs($copie->utilisateur)
            ->postJson("/api/deployments/{$copie->id}/mise-a-jour")
            ->assertStatus(202)
            ->assertJsonPath('etat', 'mise_a_jour')
            ->assertJsonPath('mise_a_jour.statut', 'en_file')
            ->assertJsonPath('mise_a_jour.de_version', 1)
            ->assertJsonPath('mise_a_jour.vers_version', 2);
        $this->actingAs($copie->utilisateur)->postJson("/api/deployments/{$copie->id}/mise-a-jour")->assertConflict();

        Queue::assertPushed(MettreAJourCopie::class, 1);
    }

    public function test_une_copie_a_jour_ou_hors_ligne_ne_se_met_pas_a_jour(): void
    {
        Queue::fake();
        [, $copie] = $this->copieEnLigne();

        $this->actingAs($copie->utilisateur)->postJson("/api/deployments/{$copie->id}/mise-a-jour")->assertConflict()->assertJsonPath('message', 'Votre copie est déjà à jour.');
        $copie->forceFill(['etat' => 'suspendu'])->save();
        $this->actingAs($copie->utilisateur)->postJson("/api/deployments/{$copie->id}/mise-a-jour")->assertConflict();
    }

    public function test_une_nouvelle_variable_obligatoire_doit_etre_fournie(): void
    {
        Queue::fake();
        [$appli, $copie] = $this->copieEnLigne();
        $this->versionPubliee($appli, 2, [['nom' => 'NOM_PHARMACIE', 'source' => 'client', 'libelle' => 'Nom de la pharmacie', 'requis' => true]]);

        $this->actingAs($copie->utilisateur)
            ->postJson("/api/deployments/{$copie->id}/mise-a-jour")
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['variables.NOM_PHARMACIE' => ['La version 2 demande « Nom de la pharmacie ».']]);
        $this->actingAs($copie->utilisateur)
            ->postJson("/api/deployments/{$copie->id}/mise-a-jour", ['variables' => ['NOM_PHARMACIE' => 'Pharmacie du Port']])
            ->assertStatus(202);

        $this->assertSame(['NOM_PHARMACIE' => 'Pharmacie du Port'], MiseAJour::query()->sole()->variables);
    }

    public function test_les_issues_de_la_mise_a_jour_sont_enregistrees_et_annoncees(): void
    {
        Notification::fake();
        [$appli, $copie] = $this->copieEnLigne();
        $version = $this->versionPubliee($appli, 2);
        $issues = [
            'reussie' => fn () => '20261026-090000',
            'retablie' => fn () => throw new MiseAJourRetablie('Le service web ne répond pas sur le port 80.', '20261026-090000'),
            'refusee' => fn () => throw new DepotInvalide('Le commit est introuvable.'),
            'echouee' => fn () => throw new \RuntimeException('docker injoignable'),
        ];

        foreach ($issues as $issue => $resultat) {
            $copie->forceFill(['etat' => 'mise_a_jour', 'version_id' => $appli->versions()->where('numero', 1)->value('id'), 'url' => 'http://d'.$copie->id.'.localhost'])->save();
            $mise = $copie->misesAJour()->create(['de_version_id' => $copie->version_id, 'vers_version_id' => $version->id, 'statut' => 'en_file', 'variables' => []]);
            $this->mock(MiseAJourCopie::class, fn (MockInterface $mock) => $mock->shouldReceive('appliquer')->once()->andReturnUsing($resultat));

            (new MettreAJourCopie($mise->id))->handle(app(MiseAJourCopie::class));

            $mise->refresh();
            $copie->refresh();
            match ($issue) {
                'reussie' => $this->assertTrue($mise->statut === 'reussie' && $copie->etat === 'en_ligne' && $copie->version_id === $version->id && $mise->sauvegarde === '20261026-090000'),
                'retablie' => $this->assertTrue($mise->statut === 'retablie' && $copie->etat === 'en_ligne' && $mise->erreur === 'Le service web ne répond pas sur le port 80.'),
                'refusee' => $this->assertTrue($mise->statut === 'retablie' && $copie->etat === 'en_ligne' && str_starts_with((string) $mise->erreur, 'Rien n’a été modifié')),
                'echouee' => $this->assertTrue($mise->statut === 'echouee' && $copie->etat === 'echec'),
            };
            $this->assertSame('http://d'.$copie->id.'.localhost', $issue === 'echouee' ? 'http://d'.$copie->id.'.localhost' : $copie->url);
        }

        Notification::assertSentToTimes($copie->utilisateur, MiseAJourTerminee::class, 4);
        Notification::assertSentOnDemand(AlerteAdmin::class);
    }

    public function test_la_mise_a_jour_bascule_puis_revient_en_arriere_si_la_nouvelle_version_ne_demarre_pas(): void
    {
        $ancien = $this->depot($this->racine.'/d7', 'ancienne');
        $nouveau = $this->depot($this->racine.'/d7-maj', 'nouvelle');
        $etapes = [];
        $this->mock(CloneurGithub::class, fn (MockInterface $mock) => $mock->shouldReceive('cloner')->with('https://github.com/acme/stock', 'd7-maj', self::COMMIT)->andReturn($nouveau));
        $this->mock(SauvegardeVolumes::class, function (MockInterface $mock) use (&$etapes) {
            $mock->shouldReceive('sauvegarder')->once()->andReturnUsing(function () use (&$etapes) {
                $etapes[] = 'sauvegarde';

                return '20261026-090000';
            });
            $mock->shouldReceive('restaurer')->once()->with('d7', '20261026-090000')->andReturnUsing(function () use (&$etapes) {
                $etapes[] = 'restauration';
            });
        });
        $this->mock(LanceurCompose::class, function (MockInterface $mock) use (&$etapes) {
            $mock->shouldReceive('verifier')->andReturn('web');
            $mock->shouldReceive('suspendre')->andReturnUsing(function () use (&$etapes) {
                $etapes[] = 'arret';
            });
            $mock->shouldReceive('lancer')->andReturnUsing(function (string $repertoire) use (&$etapes) {
                $version = trim((string) file_get_contents($repertoire.'/VERSION'));
                $etapes[] = 'lancement '.$version;
                if ($version === 'nouvelle') {
                    throw new LancementEchoue('La construction de l’image a échoué.');
                }
            });
            $mock->shouldReceive('adresseService')->andReturn('172.20.0.5');
        });
        $this->mock(BackendIntegre::class, function (MockInterface $mock) {
            $mock->shouldReceive('reconduire')->andReturnUsing(fn (string $ancien, string $nouveau, array $manifeste, array $env) => $env);
            $mock->shouldReceive('appliquer');
        });
        $this->mock(RouteurCopies::class, fn (MockInterface $mock) => $mock->shouldReceive('publier')->once()->with('d7', '172.20.0.5', 80));
        $this->app->instance(SondeServiceWeb::class, new SondeServiceWeb(fn () => true, 1, 0));

        try {
            app(MiseAJourCopie::class)->appliquer('d7', $ancien, 'https://github.com/acme/stock', self::COMMIT, [], 'petite');
            $this->fail('La nouvelle version ne démarre pas : retour arrière attendu.');
        } catch (MiseAJourRetablie $retablie) {
            $this->assertSame('La construction de l’image a échoué.', $retablie->getMessage());
            $this->assertSame('20261026-090000', $retablie->sauvegarde);
        }

        $this->assertSame(['arret', 'sauvegarde', 'lancement nouvelle', 'arret', 'restauration', 'lancement ancienne'], $etapes);
        $this->assertSame('ancienne', trim((string) file_get_contents($ancien.'/VERSION')));
        $this->assertDirectoryDoesNotExist($ancien.'-precedente');
        $this->assertDirectoryDoesNotExist($nouveau);
        // Le .env de l'ancienne version (secrets, valeurs du client) est revenu avec elle.
        $this->assertStringContainsString('DB_PASSWORD=ancien-secret', (string) file_get_contents($ancien.'/.env'));
    }

    public function test_un_nouveau_code_refuse_ne_touche_pas_a_la_copie(): void
    {
        $ancien = $this->depot($this->racine.'/d8', 'ancienne');
        $nouveau = $this->depot($this->racine.'/d8-maj', 'nouvelle');
        file_put_contents($nouveau.'/soukdev.json', '{"version": 1}');
        $this->mock(CloneurGithub::class, fn (MockInterface $mock) => $mock->shouldReceive('cloner')->andReturn($nouveau));
        $this->mock(LanceurCompose::class, fn (MockInterface $mock) => $mock->shouldNotReceive('suspendre'));
        $this->mock(SauvegardeVolumes::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sauvegarder'));

        try {
            app(MiseAJourCopie::class)->appliquer('d8', $ancien, 'https://github.com/acme/stock', self::COMMIT, [], 'petite');
            $this->fail('Un soukdev.json invalide doit être refusé.');
        } catch (\Throwable $refus) {
            $this->assertNotInstanceOf(MiseAJourRetablie::class, $refus);
        }

        $this->assertDirectoryDoesNotExist($nouveau);
        $this->assertSame('ancienne', trim((string) file_get_contents($ancien.'/VERSION')));
    }

    public function test_la_sauvegarde_passe_chaque_volume_par_le_disque_sans_reseau(): void
    {
        config(['moteur.sauvegardes' => $this->racine.'/sauvegardes']);
        Process::fake(function (PendingProcess $process) {
            $commande = $process->command;
            if (($commande[1] ?? null) === 'volume') {
                return Process::result("d9_donnees\nd9_fichiers\n");
            }
            // Le shell du moteur écrit l'archive (ici simulée) à l'emplacement demandé.
            if (($commande[3] ?? null) === 'sauvegarde') {
                file_put_contents($commande[6], 'ARCHIVE-'.$commande[4]);
            }

            return Process::result();
        });

        $sauvegardes = new SauvegardeVolumes;
        $identifiant = $sauvegardes->sauvegarder('d9');
        $sauvegardes->restaurer('d9', $identifiant);

        $dossier = $sauvegardes->dossier('d9', $identifiant);
        $this->assertSame('ARCHIVE-d9_donnees', file_get_contents($dossier.'/d9_donnees.tar.gz'));
        $this->assertSame(['d9_donnees', 'd9_fichiers'], json_decode((string) file_get_contents($dossier.'/volumes.json'), true));
        Process::assertRan(fn (PendingProcess $process) => ($process->command[3] ?? null) === 'restauration' && $process->command[4] === 'd9_fichiers' && $process->command[6] === $dossier.'/d9_fichiers.tar.gz');
        Process::assertRan(fn (PendingProcess $process) => str_contains((string) ($process->command[2] ?? ''), '--network none') && str_contains((string) $process->command[2], '> "$3"'));
        $this->expectException(LancementEchoue::class);
        $sauvegardes->restaurer('d9', '20200101-000000');
    }

    /**
     * @return array{Appli, Deploiement}
     */
    private function copieEnLigne(): array
    {
        $appli = Appli::factory()->create(['depot_url' => 'https://github.com/acme/stock']);
        $v1 = $appli->versions()->create(['numero' => 1, 'statut' => 'publiee']);
        $v1->forceFill(['commit' => str_repeat('1', 40), 'manifeste' => $this->manifeste()])->save();
        $copie = Deploiement::factory()->create(['appli_id' => $appli->id, 'etat' => 'en_ligne', 'url' => 'http://d1.localhost', 'variables' => []]);
        $copie->forceFill(['version_id' => $v1->id, 'repertoire' => $this->racine.'/d'.$copie->id])->save();

        return [$appli, $copie];
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     */
    private function versionPubliee(Appli $appli, int $numero, array $variables = []): VersionAppli
    {
        $version = $appli->versions()->create(['numero' => $numero, 'statut' => 'publiee', 'notes' => 'Nouveautés.']);
        $version->forceFill(['commit' => self::COMMIT, 'manifeste' => [...$this->manifeste(), 'variables' => $variables]])->save();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifeste(): array
    {
        return ['version' => 1, 'service_web' => ['nom' => 'web', 'port' => 80], 'backend' => 'propre', 'variables' => []];
    }

    private function depot(string $chemin, string $version): string
    {
        File::ensureDirectoryExists($chemin);
        file_put_contents($chemin.'/VERSION', $version."\n");
        file_put_contents($chemin.'/docker-compose.yml', "services:\n  web:\n    image: nginx:alpine\n");
        file_put_contents($chemin.'/soukdev.json', json_encode(['version' => 1, 'service_web' => ['nom' => 'web', 'port' => 80], 'backend' => 'propre', 'variables' => [['nom' => 'DB_PASSWORD', 'source' => 'plateforme']]]));
        file_put_contents($chemin.'/.env', 'DB_PASSWORD='.($version === 'ancienne' ? 'ancien-secret' : 'jamais-lu')."\n");

        return $chemin;
    }
}
