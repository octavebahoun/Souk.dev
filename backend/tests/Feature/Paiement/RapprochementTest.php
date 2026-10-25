<?php

namespace Tests\Feature\Paiement;

use App\Events\DeploiementEtat;
use App\Events\FactureModifiee;
use App\Jobs\LancerDeploiement;
use App\Jobs\RapprochementQuotidien;
use App\Jobs\RapprocherPaiementsEnAttente;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Rapprochement;
use App\Models\SimulationSasPay;
use App\Notifications\EcartRapprochement;
use App\Paiement\Encaissement;
use App\Paiement\Passerelles\PasserellePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Filets des webhooks : revérification toutes les 15 minutes et comparaison quotidienne. */
class RapprochementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([LancerDeploiement::class]);
        Event::fake([DeploiementEtat::class, FactureModifiee::class]);
        Notification::fake();
        Http::fake();
    }

    public function test_un_paiement_reussi_sans_webhook_est_rattrape_dans_les_15_minutes(): void
    {
        $paiement = $this->paiementSimule('SUCCESS');
        $this->travel(5)->minutes();

        (new RapprocherPaiementsEnAttente)->handle(app(Encaissement::class));

        $this->assertSame('reussi', $paiement->refresh()->statut);
        $this->assertSame('payee', $paiement->facture->statut);
    }

    public function test_la_comparaison_quotidienne_corrige_et_previent_l_admin_des_ecarts(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $rattrape = $this->paiementSimule('SUCCESS');
        // Une transaction réussie de Souk.dev que rien ne relie à un paiement d'ici.
        SimulationSasPay::query()->create([
            'type' => 'session', 'montant' => 7000, 'statut' => 'SUCCESS', 'transaction_id' => '6f9b1d2e-0000-4000-8000-00000000abcd',
            'reference' => 'SIM-ORPHELIN', 'metadata' => ['description' => 'Souk.dev — Caisse — facture SD-2026-000099'],
        ]);
        // Une vente d'une autre activité du même compte marchand : ignorée.
        SimulationSasPay::query()->create([
            'type' => 'session', 'montant' => 2500, 'statut' => 'SUCCESS', 'transaction_id' => '6f9b1d2e-0000-4000-8000-00000000ef01',
            'reference' => 'AUTRE', 'metadata' => ['description' => 'Gentube — abonnement'],
        ]);
        $this->travelTo(now()->addDay()->setTime(2, 30));

        (new RapprochementQuotidien)->handle(app(PasserellePaiement::class), app(Encaissement::class));

        $rapprochement = Rapprochement::query()->sole();
        $this->assertSame(2, $rapprochement->transactions_saspay);
        $this->assertSame(['corrige', 'inconnue_localement'], collect($rapprochement->ecarts)->pluck('type')->sort()->values()->all());
        $this->assertSame('reussi', $rattrape->refresh()->statut);
        Notification::assertSentTo(new AnonymousNotifiable, EcartRapprochement::class, fn ($notification, $canaux, $notifiable) => $notifiable->routes['mail'] === 'admin@exemple.test' && $notification->ecarts === 1);
    }

    public function test_un_jour_sans_ecart_ne_derange_personne(): void
    {
        (new RapprochementQuotidien)->handle(app(PasserellePaiement::class), app(Encaissement::class));

        $this->assertSame([], Rapprochement::query()->sole()->ecarts);
        Notification::assertNothingSent();
    }

    private function paiementSimule(string $statut): Paiement
    {
        $facture = Facture::factory()->create();
        $paiement = app(Encaissement::class)->ouvrirSession($facture, $facture->client);
        SimulationSasPay::query()->findOrFail($paiement->session_id)->forceFill([
            'statut' => $statut, 'transaction_id' => (string) str()->uuid(), 'reference' => 'SIM-'.$paiement->id,
        ])->save();

        return $paiement;
    }
}
