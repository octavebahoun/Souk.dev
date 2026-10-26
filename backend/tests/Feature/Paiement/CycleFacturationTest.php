<?php

namespace Tests\Feature\Paiement;

use App\Events\DeploiementEtat;
use App\Events\FactureModifiee;
use App\Jobs\ArreterDeploiement;
use App\Jobs\LancerDeploiement;
use App\Jobs\ReactiverCopie;
use App\Jobs\SuspendreCopie;
use App\Models\Abonnement;
use App\Models\Appli;
use App\Models\Facture;
use App\Models\SimulationSasPay;
use App\Models\User;
use App\Notifications\AvertissementArret;
use App\Notifications\CopieResiliee;
use App\Notifications\CopieSuspendue;
use App\Notifications\FactureEmise;
use App\Notifications\FactureEnRetard;
use App\Paiement\CycleFacturation;
use App\Paiement\Encaissement;
use App\Paiement\Facturation;
use App\Paiement\Passerelles\SimulateurSasPay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Facturation à date anniversaire : facture et rappel 3 jours avant, relance, suspension après
 * 7 jours de grâce, réactivation au paiement, avertissement puis arrêt après 30 jours.
 */
class CycleFacturationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([LancerDeploiement::class, SuspendreCopie::class, ReactiverCopie::class, ArreterDeploiement::class]);
        Event::fake([DeploiementEtat::class, FactureModifiee::class]);
        Notification::fake();
        Http::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
    }

    public function test_la_facture_du_mois_suivant_part_3_jours_avant_avec_son_rappel(): void
    {
        $abonnement = $this->copiePayee();
        $this->assertSame('2026-11-05', $abonnement->prochaine_echeance->toDateString());

        $this->passage('2026-11-01');
        $this->assertSame(1, Facture::query()->count(), 'Pas encore : 4 jours avant l\'échéance.');

        $this->passage('2026-11-02');
        $facture = Facture::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-11-05', $facture->echeance_le->toDateString());
        $this->assertSame('2026-12-04', $facture->periode_fin->toDateString());
        // Prix mensuel : hébergement et appli chaque mois.
        $this->assertSame(18500, $facture->montant_total);
        Notification::assertSentTo($abonnement->client, FactureEmise::class, 1);

        // Le même jour, un second passage ne refait rien.
        $this->passage('2026-11-02');
        $this->assertSame(2, Facture::query()->count());
        Notification::assertSentTo($abonnement->client, FactureEmise::class, 1);
    }

    public function test_un_prix_unique_n_est_facture_qu_une_fois(): void
    {
        $abonnement = $this->copiePayee(typePrix: 'unique', prix: 50000);
        $this->assertSame(53500, Facture::query()->sole()->montant_total);

        $this->passage('2026-11-02');

        $this->assertSame(3500, Facture::query()->latest('id')->firstOrFail()->montant_total);
    }

    public function test_un_impaye_est_relance_suspendu_puis_reactive_au_paiement(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');
        $facture = Facture::query()->latest('id')->firstOrFail();

        $this->passage('2026-11-06');
        $this->assertSame('en_retard', $abonnement->refresh()->statut);
        Notification::assertSentTo($abonnement->client, FactureEnRetard::class, 1);

        $this->passage('2026-11-11');
        $this->assertSame('en_retard', $abonnement->refresh()->statut, '6 jours de retard : encore dans la grâce.');

        $this->passage('2026-11-12');
        $this->assertSame('suspendu', $abonnement->refresh()->statut);
        Queue::assertPushed(SuspendreCopie::class, fn (SuspendreCopie $job) => $job->deploiementId === $abonnement->deploiement_id);
        Notification::assertSentTo($abonnement->client, CopieSuspendue::class);

        $this->payer($facture);

        $this->assertSame('actif', $abonnement->refresh()->statut);
        $this->assertNull($abonnement->suspendu_le);
        Queue::assertPushed(ReactiverCopie::class, fn (ReactiverCopie $job) => $job->deploiementId === $abonnement->deploiement_id);
    }

    public function test_une_copie_suspendue_est_avertie_puis_arretee_apres_30_jours(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');
        $this->passage('2026-11-12');
        $this->assertSame('suspendu', $abonnement->refresh()->statut);

        $this->passage('2026-12-04');
        Notification::assertNotSentTo($abonnement->client, AvertissementArret::class);

        $this->passage('2026-12-05');
        Notification::assertSentTo($abonnement->client, AvertissementArret::class, 1);
        $this->passage('2026-12-06');
        Notification::assertSentTo($abonnement->client, AvertissementArret::class, 1);

        $this->passage('2026-12-12');
        $abonnement->refresh();
        $this->assertSame('resilie', $abonnement->statut);
        $this->assertSame(['annulee'], Facture::query()->where('statut', '!=', 'payee')->pluck('statut')->unique()->values()->all());
        $this->assertSame('arret_en_cours', $abonnement->deploiement->etat);
        Queue::assertPushed(ArreterDeploiement::class);
        Notification::assertSentTo($abonnement->client, CopieResiliee::class);

        // Plus aucune facture ensuite.
        $this->passage('2027-01-02');
        $this->assertSame(0, Facture::query()->where('statut', 'a_payer')->count());
    }

    public function test_une_commande_jamais_payee_est_annulee_apres_7_jours(): void
    {
        $client = User::factory()->client()->create();
        $deploiement = app(Facturation::class)->commander(Appli::factory()->create(), $client, []);

        $this->passage('2026-10-11');
        $this->assertSame('attente_paiement', $deploiement->refresh()->etat);

        $this->passage('2026-10-12');
        $this->assertSame('arrete', $deploiement->refresh()->etat);
        $this->assertSame('resilie', $deploiement->abonnement->statut);
        $this->assertSame('annulee', Facture::query()->sole()->statut);
        Notification::assertSentTo($client, CopieResiliee::class, fn (CopieResiliee $notification) => $notification->jamaisPayee);
    }

    public function test_le_client_qui_arrete_sa_copie_n_est_plus_facture(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');

        $this->actingAs($abonnement->client)->deleteJson('/api/deployments/'.$abonnement->deploiement_id)->assertAccepted();

        $this->assertSame('resilie', $abonnement->refresh()->statut);
        $this->assertSame('annulee', Facture::query()->latest('id')->firstOrFail()->statut);
        $this->passage('2026-12-02');
        $this->assertSame(2, Facture::query()->count());
    }

    public function test_payer_une_seule_des_deux_factures_echues_garde_la_copie_suspendue(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');
        $this->passage('2026-11-12');
        // La suspension arrête l'émission ; deux factures échues pour le test.
        $abonnement->refresh()->forceFill(['statut' => 'en_retard'])->save();
        $this->passage('2026-12-02');
        $abonnement->refresh()->forceFill(['statut' => 'suspendu'])->save();
        $this->travelTo(CarbonImmutable::parse('2026-12-10 08:00:00'));
        [$premiere, $seconde] = Facture::query()->where('statut', 'a_payer')->orderBy('echeance_le')->get()->all();

        $this->payer($premiere);
        $this->assertSame('suspendu', $abonnement->refresh()->statut);
        Queue::assertNotPushed(ReactiverCopie::class);

        $this->payer($seconde);
        $this->assertSame('actif', $abonnement->refresh()->statut);
        Queue::assertPushed(ReactiverCopie::class, 1);
    }

    public function test_une_copie_reactivee_apres_sa_date_anniversaire_n_est_pas_resuspendue(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');
        $impayee = Facture::query()->latest('id')->firstOrFail();
        $this->passage('2026-11-12');
        $this->assertSame('suspendu', $abonnement->refresh()->statut);

        // Le client paie après la date anniversaire suivante (5 décembre) : nouveau mois depuis le paiement.
        $this->travelTo(CarbonImmutable::parse('2026-12-08 10:00:00'));
        $this->payer($impayee);
        $this->assertSame('2027-01-08', $abonnement->refresh()->prochaine_echeance->toDateString());
        $this->assertSame(0, Facture::query()->where('statut', 'a_payer')->count(), 'Aucune facture déjà échue à la réactivation.');

        foreach (['2026-12-09', '2026-12-12', '2026-12-20'] as $jour) {
            $this->passage($jour);
        }
        $this->assertSame('actif', $abonnement->refresh()->statut);
        Notification::assertNotSentTo($abonnement->client, FactureEnRetard::class, fn ($notification) => $notification->facture->id !== $impayee->id);
    }

    public function test_le_premier_mois_court_depuis_le_paiement(): void
    {
        $client = User::factory()->client()->create();
        $deploiement = app(Facturation::class)->commander(Appli::factory()->create(), $client, []);

        $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00'));
        $this->payer(Facture::query()->sole());

        $this->assertSame('2026-11-11', $deploiement->abonnement->refresh()->prochaine_echeance->toDateString());
        $this->assertSame('2026-10-11', Facture::query()->sole()->periode_debut->toDateString());
    }

    public function test_un_paiement_pendant_le_passage_l_emporte_sur_la_suspension(): void
    {
        $abonnement = $this->copiePayee();
        $this->passage('2026-11-02');
        $impayee = Facture::query()->latest('id')->firstOrFail();
        $this->passage('2026-11-06');

        // Le client paie au moment où le passage relit l'abonnement pour le suspendre.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 06:00:00'));
        $paye = false;
        Abonnement::retrieved(function () use (&$paye, $impayee): void {
            if (! $paye) {
                $paye = true;
                $this->payer($impayee);
            }
        });
        app(CycleFacturation::class)->executer();

        $this->assertSame('payee', $impayee->refresh()->statut);
        $this->assertSame('actif', $abonnement->refresh()->statut);
        Queue::assertNotPushed(SuspendreCopie::class);
    }

    public function test_la_commande_de_demo_fait_passer_le_temps_jusqu_a_la_suspension(): void
    {
        $abonnement = $this->copiePayee();

        $this->artisan('demo:avancer-facturation', ['deploiement' => $abonnement->deploiement_id, '--jours' => 39])->assertSuccessful();

        $this->assertSame('suspendu', $abonnement->refresh()->statut);
        Queue::assertPushed(SuspendreCopie::class);
    }

    public function test_la_commande_de_demo_est_refusee_hors_developpement(): void
    {
        $abonnement = $this->copiePayee();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:avancer-facturation', ['deploiement' => $abonnement->deploiement_id])->assertFailed();

        $this->assertSame('actif', $abonnement->refresh()->statut);
    }

    private function passage(string $jour): void
    {
        $this->travelTo(CarbonImmutable::parse($jour.' 06:00:00'));
        app(CycleFacturation::class)->executer();
    }

    private function copiePayee(string $typePrix = 'mensuel', int $prix = 15000): Abonnement
    {
        $client = User::factory()->client()->create();
        $appli = Appli::factory()->create(['prix' => $prix, 'type_prix' => $typePrix, 'taille' => 'petite']);
        $deploiement = app(Facturation::class)->commander($appli, $client, []);
        $this->payer(Facture::query()->where('abonnement_id', $deploiement->abonnement->id)->sole());
        $deploiement->refresh()->forceFill(['etat' => 'en_ligne', 'copie' => 'd'.$deploiement->id, 'url' => 'http://d'.$deploiement->id.'.localhost'])->save();

        return $deploiement->abonnement->refresh();
    }

    /** Le client paie sur le simulateur, la vérification par l'API applique le paiement. */
    private function payer(Facture $facture): void
    {
        $encaissement = app(Encaissement::class);
        $paiement = $encaissement->ouvrirSession($facture, $facture->client);
        app(SimulateurSasPay::class)->choisir(SimulationSasPay::query()->findOrFail($paiement->session_id), 'succes', 'mtn_bj');
        $encaissement->verifierFacture($facture);
        $this->assertSame('payee', $facture->refresh()->statut);
    }
}
