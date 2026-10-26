<?php

namespace Tests\Feature\Paiement;

use App\Events\DeploiementEtat;
use App\Jobs\InitierReversement;
use App\Jobs\LancerDeploiement;
use App\Jobs\VerifierReversements;
use App\Models\Abonnement;
use App\Models\CoordonneesReversement;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\MoteurDeploiement;
use App\Notifications\AlerteAdmin;
use App\Notifications\CopieEnIncident;
use App\Notifications\FactureEmise;
use App\Paiement\CycleFacturation;
use App\Paiement\GrandLivre;
use App\Paiement\Passerelles\DemandeReversement;
use App\Paiement\Passerelles\PasserelleRefus;
use App\Paiement\Passerelles\PasserelleReversement;
use App\Paiement\Passerelles\ReversementsInactifs;
use App\Paiement\Passerelles\VerificationReversement;
use App\Paiement\Reversements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

/** Ce qui ne doit jamais coûter d'argent à quelqu'un : copie qui ne démarre pas, reversements. */
class IncidentEtReversementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake([DeploiementEtat::class]);
    }

    public function test_une_copie_payee_qui_ne_demarre_pas_n_est_plus_facturee(): void
    {
        $abonnement = Abonnement::factory()->create(['statut' => 'actif', 'prochaine_echeance' => today()->addDays(2)]);
        $abonnement->deploiement->forceFill(['etat' => 'en_file'])->save();
        $this->mock(MoteurDeploiement::class, fn (MockInterface $mock) => $mock->shouldReceive('depuisGithub')->andThrow(new LancementEchoue('Le service web ne répond pas sur le port 80.')));

        (new LancerDeploiement($abonnement->deploiement_id))->handle(app(MoteurDeploiement::class));

        $this->assertSame('echec', $abonnement->deploiement->refresh()->etat);
        $this->assertSame('incident', $abonnement->refresh()->statut);
        Notification::assertSentTo($abonnement->client, CopieEnIncident::class);
        Notification::assertSentTo(new AnonymousNotifiable, AlerteAdmin::class, fn (AlerteAdmin $alerte) => str_contains($alerte->sujet, 'ne démarre pas'));

        app(CycleFacturation::class)->executer();
        Notification::assertNotSentTo($abonnement->client, FactureEmise::class);
    }

    public function test_en_production_aucun_reversement_n_est_declare_verse_sans_argent_reel(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['facturation.passerelle' => 'saspay', 'facturation.reversements_reels' => false]);

        $this->assertInstanceOf(ReversementsInactifs::class, app(PasserelleReversement::class));
        $dev = $this->devCredite(20000);
        $this->assertSame('Les reversements sur Mobile Money ouvrent bientôt : votre solde vous attend.', app(Reversements::class)->blocage($dev));
    }

    public function test_une_reprise_refusee_garde_le_solde_reserve_et_previent_l_equipe(): void
    {
        Queue::fake([InitierReversement::class]);
        $dev = $this->devCredite(20000);
        $this->app->instance(PasserelleReversement::class, new class implements PasserelleReversement
        {
            public function nom(): string
            {
                return 'saspay';
            }

            public function initier(DemandeReversement $demande, string $cleIdempotence): string
            {
                throw new PasserelleRefus(403, 'ip_not_whitelisted', 'IP non autorisée.');
            }

            public function verifier(string $payoutId): VerificationReversement
            {
                return new VerificationReversement('PENDING', null);
            }
        });
        $reversement = app(Reversements::class)->demander($dev, 'demande');
        // Premier appel interrompu par un délai dépassé (rien ne dit que SasPay n'a rien créé).
        $reversement->forceFill(['essais' => 1])->save();

        app(Reversements::class)->initier($reversement->refresh());

        $this->assertSame('en_attente', $reversement->refresh()->statut);
        $this->assertSame(0, app(GrandLivre::class)->soldeDev($dev->id), 'Le solde reste réservé.');
        Notification::assertSentTo(new AnonymousNotifiable, AlerteAdmin::class, fn (AlerteAdmin $alerte) => str_contains($alerte->sujet, 'à vérifier'));
    }

    public function test_un_reversement_bloque_depuis_un_jour_previent_l_equipe_une_fois(): void
    {
        Queue::fake([InitierReversement::class]);
        $dev = $this->devCredite(20000);
        $reversement = app(Reversements::class)->demander($dev, 'demande');
        $this->travel(25)->hours();

        (new VerifierReversements)->handle(app(Reversements::class));
        (new VerifierReversements)->handle(app(Reversements::class));

        Notification::assertSentToTimes(new AnonymousNotifiable, AlerteAdmin::class, 1);
        $this->assertNotNull($reversement->refresh()->alerte_le);
    }

    private function devCredite(int $montant): User
    {
        $dev = User::factory()->github()->create();
        CoordonneesReversement::query()->create([
            'user_id' => $dev->id, 'pays' => 'BJ', 'reseau' => 'mtn_bj', 'numero' => '2290197123456', 'numero_masque' => '+229 01 •• •• 34 56',
        ])->forceFill(['confirme_le' => now()])->save();
        $facture = Facture::factory()->create(['montant_appli' => $montant, 'montant_total' => 3500 + $montant, 'statut' => 'payee']);
        $facture->abonnement->forceFill(['dev_id' => $dev->id])->save();
        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id, 'client_id' => $facture->client_id, 'passerelle' => 'simulee', 'montant' => $facture->montant_total, 'statut' => 'reussi',
        ]);
        app(GrandLivre::class)->encaisser($facture->refresh(), $paiement);

        return $dev;
    }
}
