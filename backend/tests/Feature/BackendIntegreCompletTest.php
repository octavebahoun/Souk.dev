<?php

namespace Tests\Feature;

use App\Events\DeploiementEtat;
use App\Jobs\SauvegarderCopie;
use App\Models\CodeConnexion;
use App\Models\Deploiement;
use App\Models\User;
use App\Moteur\LanceurCompose;
use App\Moteur\SauvegardeVolumes;
use App\Notifications\CodeConnexionCopie;
use App\Sms\FournisseurSms;
use App\Sms\SmsNonEnvoye;
use App\Sms\SmsSimule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;
use Tests\TestCase;

/**
 * Backend intégré complet : les utilisateurs d'une appli se connectent par un code SMS ou e-mail
 * (jeton pour l'API de la copie), et chaque copie a ses sauvegardes, exportables par son client.
 */
class BackendIntegreCompletTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718';

    private string $sauvegardes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sauvegardes = sys_get_temp_dir().'/soukdev-sauvegardes-'.bin2hex(random_bytes(4));
        config(['moteur.sauvegardes' => $this->sauvegardes]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sauvegardes);

        parent::tearDown();
    }

    public function test_un_utilisateur_de_l_appli_se_connecte_par_sms_et_recoit_un_jeton_pour_l_api_de_la_copie(): void
    {
        $copie = $this->copieIntegree();

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+229 01 97 12 34 56'])
            ->assertStatus(202)
            ->assertJsonPath('expire_dans', 600);
        $code = $this->dernierCode('+2290197123456');

        $session = $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => '+2290197123456', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('utilisateur.canal', 'sms')
            ->assertJsonPath('utilisateur.destinataire', '+229 •• •• •• 56');

        [$entete, $corps, $signature] = explode('.', $session->json('jeton'));
        $this->assertSame($this->base64Url(hash_hmac('sha256', $entete.'.'.$corps, self::SECRET, true)), $signature);
        $charge = json_decode((string) base64_decode(strtr($corps, '-_', '+/')), true);
        $this->assertSame('soukdev_utilisateur', $charge['role']);
        $this->assertSame($session->json('utilisateur.id'), $charge['sub']);
        $this->assertEqualsWithDelta(now()->addDay()->getTimestamp(), $charge['exp'], 5);

        // Un code ne sert qu'une fois ; le même numéro retrouve le même utilisateur.
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => '+2290197123456', 'code' => $code])->assertUnprocessable();
        $this->travel(16)->minutes();
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+2290197123456'])->assertStatus(202);
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => '+2290197123456', 'code' => $this->dernierCode('+2290197123456')])
            ->assertJsonPath('utilisateur.id', $session->json('utilisateur.id'));
    }

    public function test_le_code_part_aussi_par_e_mail(): void
    {
        Notification::fake();
        $copie = $this->copieIntegree();

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'email', 'destinataire' => 'Client@Exemple.test'])->assertStatus(202);

        Notification::assertSentTo(new AnonymousNotifiable, CodeConnexionCopie::class, function (CodeConnexionCopie $notification, array $canaux, AnonymousNotifiable $destinataire) {
            return $destinataire->routes['mail'] === 'client@exemple.test' && preg_match('/^\d{6}$/', $notification->code) === 1;
        });
    }

    public function test_un_nom_d_appli_avec_retour_a_la_ligne_n_empeche_pas_l_envoi_du_code(): void
    {
        Notification::fake();
        $copie = $this->copieIntegree();
        $copie->appli->forceFill(['nom' => "Pharmacie\nBcc: pirate@exemple.test"])->save();

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'email', 'destinataire' => 'client@exemple.test'])->assertStatus(202);

        Notification::assertSentTo(new AnonymousNotifiable, CodeConnexionCopie::class, function (CodeConnexionCopie $notification) {
            return $notification->appli === 'Pharmacie Bcc: pirate@exemple.test'
                && ! str_contains($notification->appli, "\n")
                && ! str_contains($notification->appli, "\r");
        });
    }

    public function test_les_codes_expires_sont_purges_chaque_nuit(): void
    {
        $planifiees = collect(app(Schedule::class)->events())
            ->filter(fn ($tache) => $tache->description === 'codes-connexion');

        $this->assertCount(1, $planifiees);
        $this->assertSame('0 0 * * *', $planifiees->first()->expression);

        CodeConnexion::query()->create([
            'deploiement_id' => $this->copieIntegree()->id,
            'empreinte' => 'expiree',
            'code' => 'hache',
            'expire_le' => now()->subDay(),
        ]);
        $this->assertSame(1, CodeConnexion::query()->where('expire_le', '<=', now())->delete());
        $this->assertSame(0, CodeConnexion::query()->count());
    }

    public function test_un_envoi_impossible_repond_503_avec_un_message_clair(): void
    {
        $copie = $this->copieIntegree();
        app()->bind(FournisseurSms::class, fn () => new class implements FournisseurSms
        {
            public function envoyer(string $numero, string $message): void
            {
                throw new SmsNonEnvoye('panne');
            }
        });

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+2290197123456'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('message', 'Le SMS n’a pas pu partir. Réessayez dans un instant, ou connectez-vous par e-mail.');

        $this->mock(ChannelManager::class, fn ($faux) => $faux->shouldReceive('route')->andThrow(new RuntimeException('Resend en panne')));
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'email', 'destinataire' => 'client@exemple.test'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('message', 'L’e-mail n’a pas pu partir. Réessayez dans un instant, ou connectez-vous par SMS.');
    }

    public function test_un_code_faux_expire_ou_trop_essaye_est_refuse(): void
    {
        $copie = $this->copieIntegree();
        $demande = ['canal' => 'sms', 'destinataire' => '+2290197123456'];
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", $demande)->assertStatus(202);
        $bon = $this->dernierCode('+2290197123456');
        $faux = $bon === '000000' ? '111111' : '000000';

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", [...$demande, 'code' => $faux])->assertUnprocessable()->assertJsonPath('errors.code.0', "Ce code est invalide ou expiré\u{00A0}: demandez-en un nouveau.");
        foreach (range(2, 5) as $essai) {
            $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", [...$demande, 'code' => $faux])->assertUnprocessable();
        }
        // Cinq essais faux : même le bon code ne passe plus.
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", [...$demande, 'code' => $bon])->assertStatus(429);

        $this->travel(16)->minutes();
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", $demande)->assertStatus(202);
        $this->travel(11)->minutes();
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", [...$demande, 'code' => $this->dernierCode('+2290197123456')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', "Ce code est invalide ou expiré\u{00A0}: demandez-en un nouveau.");
        // Le même verdict pour un numéro sans code : aucune différence observable (pas d'oracle).
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => '+22990000009', 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', "Ce code est invalide ou expiré\u{00A0}: demandez-en un nouveau.");
    }

    public function test_les_essais_de_session_sont_brides_par_adresse_ip(): void
    {
        $copie = $this->copieIntegree();

        // Quatre destinataires, cinq faux essais chacun : vingt essais, tous refusés en 422.
        foreach (range(1, 4) as $rang) {
            $numero = '+2299000000'.$rang;
            $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => $numero])->assertStatus(202);
            $faux = $this->dernierCode($numero) === '000000' ? '111111' : '000000';
            foreach (range(1, 5) as $essai) {
                $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => $numero, 'code' => $faux])->assertUnprocessable();
            }
        }

        // Un cinquième destinataire, avec un code frais et aucun essai : refusé en 429 par la
        // limite d'adresse IP, pas par les 5 essais du code.
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+22990000005'])->assertStatus(202);
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/session", ['canal' => 'sms', 'destinataire' => '+22990000005', 'code' => $this->dernierCode('+22990000005')])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop d’essais : réessayez dans quelques minutes.');
    }

    public function test_une_panne_sms_inattendue_repond_503_comme_la_voie_e_mail(): void
    {
        $copie = $this->copieIntegree();
        app()->bind(FournisseurSms::class, fn () => new class implements FournisseurSms
        {
            public function envoyer(string $numero, string $message): void
            {
                throw new RuntimeException('fournisseur injoignable');
            }
        });

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+2290197123456'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('message', 'Le SMS n’a pas pu partir. Réessayez dans un instant, ou connectez-vous par e-mail.');
    }

    public function test_les_codes_sont_limites_et_les_copies_sans_backend_n_ont_pas_de_connexion(): void
    {
        $copie = $this->copieIntegree();
        $demande = ['canal' => 'sms', 'destinataire' => '+2290197123456'];
        foreach (range(1, 3) as $rang) {
            $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", $demande)->assertStatus(202);
        }
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", $demande)->assertStatus(429);

        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '97123456'])->assertUnprocessable()->assertJsonValidationErrors('destinataire');
        $this->postJson('/api/copies/'.Str::uuid().'/auth/code', $demande)->assertNotFound();
        $sansBackend = Deploiement::factory()->create(['etat' => 'en_ligne', 'cle_publique' => (string) Str::uuid()]);
        $this->postJson("/api/copies/{$sansBackend->cle_publique}/auth/code", $demande)->assertNotFound();
        $copie->forceFill(['etat' => 'suspendu'])->save();
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+22990000001'])->assertNotFound();
    }

    public function test_la_connexion_s_ouvre_a_toute_origine_mais_jamais_avec_les_cookies(): void
    {
        $copie = $this->copieIntegree();

        $this->call('OPTIONS', "/api/copies/{$copie->cle_publique}/auth/code", server: ['HTTP_ORIGIN' => 'http://d42.localhost', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->postJson("/api/copies/{$copie->cle_publique}/auth/code", ['canal' => 'sms', 'destinataire' => '+2290197123456'], ['Origin' => 'http://d42.localhost'])
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        // Le reste de l'API ne s'ouvre pas à l'origine d'une copie.
        $this->getJson('/api/apps', ['Origin' => 'http://d42.localhost'])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_le_client_liste_et_exporte_ses_sauvegardes_et_personne_d_autre(): void
    {
        $copie = $this->copieIntegree();
        $dossier = $this->sauvegardes.'/d'.$copie->id.'/20261026-033000';
        File::ensureDirectoryExists($dossier);
        file_put_contents($dossier.'/base.dump', 'PGDMP');
        file_put_contents($dossier.'/volumes.json', '[]');
        file_put_contents($dossier.'/sauvegarde.json', json_encode(['type' => 'quotidienne', 'cree_le' => '2026-10-26T03:30:00+00:00']));

        $this->actingAs($copie->utilisateur)
            ->getJson("/api/deployments/{$copie->id}/sauvegardes")
            ->assertOk()
            ->assertJsonPath('data.0.identifiant', '20261026-033000')
            ->assertJsonPath('data.0.type', 'quotidienne');
        $this->actingAs($copie->utilisateur)->getJson("/api/deployments/{$copie->id}")->assertJsonPath('derniere_sauvegarde.identifiant', '20261026-033000');

        $export = $this->actingAs($copie->utilisateur)->get("/api/deployments/{$copie->id}/sauvegardes/20261026-033000");
        $export->assertOk()->assertHeader('Content-Type', 'application/x-tar');
        $archive = sys_get_temp_dir().'/export-test-'.bin2hex(random_bytes(4)).'.tar';
        file_put_contents($archive, $export->streamedContent());
        $this->assertSame('PGDMP', file_get_contents('phar://'.$archive.'/base.dump'));
        unlink($archive);

        $this->actingAs($copie->utilisateur)->get("/api/deployments/{$copie->id}/sauvegardes/20201010-101010")->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson("/api/deployments/{$copie->id}/sauvegardes")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/api/deployments/{$copie->id}/sauvegardes/20261026-033000")->assertNotFound();
        $this->assertTrue(class_exists(PharData::class));
    }

    public function test_le_client_demande_une_sauvegarde_maintenant(): void
    {
        Queue::fake();
        $copie = $this->copieIntegree();

        $this->actingAs($copie->utilisateur)->postJson("/api/deployments/{$copie->id}/sauvegardes")->assertStatus(202);
        Queue::assertPushed(SauvegarderCopie::class, fn (SauvegarderCopie $travail) => $travail->deploiementId === $copie->id && $travail->type === 'manuelle');

        $copie->forceFill(['etat' => 'suspendu'])->save();
        $this->actingAs($copie->utilisateur)->postJson("/api/deployments/{$copie->id}/sauvegardes")->assertConflict();
    }

    public function test_la_sauvegarde_a_chaud_exporte_la_base_integree_et_laisse_ses_fichiers(): void
    {
        $repertoire = sys_get_temp_dir().'/soukdev-integre-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($repertoire);
        file_put_contents($repertoire.'/docker-compose.yml', "services:\n  web:\n    image: nginx\n");
        file_put_contents($repertoire.'/docker-compose.soukdev.yml', "services: {}\n");
        file_put_contents($repertoire.'/docker-compose.backend.yml', "services: {}\n");
        $copie = $this->copieIntegree();
        $copie->forceFill(['repertoire' => $repertoire])->save();
        $commandes = [];
        Process::fake(function (PendingProcess $process) use (&$commandes) {
            $commande = $process->command;
            $commandes[] = $commande;

            return match (true) {
                ($commande[1] ?? null) === 'volume' => Process::result('d'.$this->copieId.'_soukdev-db-data'."\n".'d'.$this->copieId.'_fichiers'."\n"),
                ($commande[1] ?? null) === 'ps' => Process::result("a1b2c3d4e5f6\n"),
                ($commande[1] ?? null) === 'inspect' => Process::result('d'.$this->copieId.'_soukdev-db-data '),
                default => Process::result(),
            };
        });
        $this->copieId = $copie->id;
        Event::fake([DeploiementEtat::class]);

        (new SauvegarderCopie($copie->id, 'quotidienne'))->handle(app(SauvegardeVolumes::class), app(LanceurCompose::class));

        $dump = collect($commandes)->first(fn (array $commande) => in_array('pg_dump', $commande, true));
        $this->assertNotNull($dump);
        $this->assertSame(['sh', '-c', '"$@" > "$0"'], array_slice($dump, 0, 3));
        $sauvees = collect($commandes)->filter(fn (array $commande) => ($commande[3] ?? null) === 'sauvegarde')->map(fn (array $commande) => $commande[4])->values()->all();
        $this->assertSame(['d'.$copie->id.'_fichiers'], $sauvees);
        $this->assertSame('quotidienne', app(SauvegardeVolumes::class)->derniere('d'.$copie->id)['type']);
        // La carte de « Mes copies » reçoit la nouvelle sauvegarde en direct.
        Event::assertDispatched(DeploiementEtat::class, fn (DeploiementEtat $etat) => $etat->deploiementId === $copie->id && $etat->payload['derniere_sauvegarde']['type'] === 'quotidienne');
        File::deleteDirectory($repertoire);
    }

    private int $copieId = 0;

    private function copieIntegree(): Deploiement
    {
        $copie = Deploiement::factory()->create(['etat' => 'en_ligne', 'url' => 'http://d1.localhost']);
        $copie->forceFill(['cle_publique' => (string) Str::uuid(), 'backend_secret' => self::SECRET, 'copie' => 'd'.$copie->id])->save();

        return $copie->refresh();
    }

    private function dernierCode(string $numero): string
    {
        $message = collect(Cache::get(SmsSimule::CLE, []))->firstWhere('numero', $numero);
        $this->assertNotNull($message, 'Aucun SMS simulé pour ce numéro.');
        preg_match('/^(\d{6})/', $message['message'], $code);

        return $code[1];
    }

    private function base64Url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }
}
