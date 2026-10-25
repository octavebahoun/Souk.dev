<?php

namespace Tests\Feature\Paiement;

use App\Jobs\InitierReversement;
use App\Jobs\ReversementsMensuels;
use App\Models\CoordonneesReversement;
use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Reversement;
use App\Models\User;
use App\Notifications\ConfirmerCoordonneesReversement;
use App\Notifications\ReversementEffectue;
use App\Paiement\GrandLivre;
use App\Paiement\Passerelles\DemandeReversement;
use App\Paiement\Passerelles\PasserelleIndisponible;
use App\Paiement\Passerelles\PasserelleRefus;
use App\Paiement\Passerelles\PasserelleReversement;
use App\Paiement\Passerelles\VerificationReversement;
use App\Paiement\Reversements;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Reversements aux devs : numéro confirmé par e-mail, tout le solde, frais pris sur la marge
 * de Souk.dev, solde rendu si le réseau refuse, et jamais deux paiements pour une demande.
 */
class ReversementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_le_numero_n_est_utilisable_qu_apres_le_bouton_de_confirmation(): void
    {
        $dev = User::factory()->github()->create();
        $this->crediter($dev, 15000);

        $this->actingAs($dev)->putJson('/api/me/coordonnees-reversement', [
            'pays' => 'BJ', 'reseau' => 'mtn_bj', 'numero' => '01 97 12 34 56', 'numero_confirmation' => '01 97 12 34 56',
        ])->assertOk()->assertJsonPath('confirme', false)->assertJsonMissingPath('numero');

        $jeton = null;
        Notification::assertSentTo($dev, ConfirmerCoordonneesReversement::class, function (ConfirmerCoordonneesReversement $notification) use (&$jeton) {
            $jeton = $notification->jeton;

            return true;
        });
        $this->actingAs($dev)->getJson('/api/me/revenus')->assertOk()->assertJsonPath('peut_demander', false);

        // Ouvrir le lien (un analyseur de liens le fait tout seul) ne confirme rien.
        $this->app['auth']->forgetGuards();
        $this->get('/reversement/confirmer/'.$jeton)->assertOk()->assertSee('Confirmer ce numéro')->assertDontSee('97123456');
        $this->assertFalse(CoordonneesReversement::query()->sole()->estConfirme());

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/reversement/confirmer/'.$jeton)
            ->assertRedirect('http://localhost:5173/revenus?confirmation=ok');
        $this->assertTrue(CoordonneesReversement::query()->sole()->estConfirme());

        // Usage unique.
        $this->get('/reversement/confirmer/'.$jeton)->assertRedirect('http://localhost:5173/revenus?confirmation=expiree');
    }

    public function test_un_dev_demande_son_reversement_et_les_frais_sont_pour_souk_dev(): void
    {
        $dev = $this->devConfirme('0197123456');
        $this->crediter($dev, 15000);

        $this->actingAs($dev)->postJson('/api/me/revenus/reversements', [], ['Idempotency-Key' => 'reversement-0001'])
            ->assertAccepted()
            ->assertJsonPath('montant', 15000)
            ->assertJsonPath('statut', 'reussi')
            ->assertJsonPath('numero_masque', CoordonneesReversement::query()->sole()->numero_masque);

        $grandLivre = app(GrandLivre::class);
        $this->assertSame(0, $grandLivre->soldeDev($dev->id));
        // 2,2 % + 50 F de frais, à la charge de Souk.dev : le dev reçoit ses 15 000 F entiers.
        $this->assertSame(380, $grandLivre->solde(Ecriture::COMPTE_FRAIS_REVERSEMENT));
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
        Notification::assertSentTo($dev, ReversementEffectue::class);

        // La même demande rejouée ne paie pas deux fois.
        $this->actingAs($dev)->postJson('/api/me/revenus/reversements', [], ['Idempotency-Key' => 'reversement-0001'])
            ->assertAccepted()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, Reversement::query()->count());
    }

    public function test_un_refus_du_reseau_rend_le_solde_au_dev(): void
    {
        $dev = $this->devConfirme('0197120000');
        $this->crediter($dev, 9000);

        $this->actingAs($dev)->postJson('/api/me/revenus/reversements')->assertAccepted()->assertJsonPath('statut', 'echoue');

        $this->assertSame(9000, app(GrandLivre::class)->soldeDev($dev->id));
        $this->assertSame(0, (int) Ecriture::query()->sum('montant'));
    }

    public function test_sous_le_seuil_la_demande_est_refusee(): void
    {
        $dev = $this->devConfirme('0197123456');
        $this->crediter($dev, 4999);

        $this->actingAs($dev)->postJson('/api/me/revenus/reversements')
            ->assertUnprocessable()
            ->assertJsonPath('errors.reversement.0', 'Le reversement à la demande commence à 5 000 F.');
    }

    public function test_un_conflit_d_idempotence_chez_saspay_ne_rend_jamais_le_solde(): void
    {
        Queue::fake([InitierReversement::class]);
        $dev = $this->devConfirme('0197123456');
        $this->crediter($dev, 20000);
        $this->app->instance(PasserelleReversement::class, new class extends ReversementFactice
        {
            public function initier(DemandeReversement $demande, string $cleIdempotence): string
            {
                // La première tentative tourne encore chez SasPay.
                throw new PasserelleRefus(409, 'idempotency_in_progress', 'Requête identique en cours.');
            }
        });

        $this->actingAs($dev)->postJson('/api/me/revenus/reversements')->assertAccepted();
        $reversement = Reversement::query()->sole();

        // Le job prend le 409 pour « réessayer plus tard », jamais pour un échec.
        try {
            app(Reversements::class)->initier($reversement);
            $this->fail('Le conflit aurait dû demander un nouvel essai.');
        } catch (PasserelleIndisponible) {
            $this->assertSame('en_attente', $reversement->refresh()->statut);
        }

        $this->assertSame(0, app(GrandLivre::class)->soldeDev($dev->id), 'Le solde reste réservé tant que SasPay n\'a pas répondu.');

        // Une seconde demande pendant ce temps : 409, rien de plus n'est réservé.
        $this->actingAs($dev)->postJson('/api/me/revenus/reversements')->assertStatus(409);
        $this->assertSame(1, Reversement::query()->count());
    }

    public function test_une_reprise_renvoie_exactement_la_meme_demande(): void
    {
        Queue::fake([InitierReversement::class]);
        $dev = $this->devConfirme('0197123456');
        $this->crediter($dev, 20000);
        $demandes = [];
        $passerelle = new class($demandes) extends ReversementFactice
        {
            public function __construct(private array &$demandes) {}

            public function initier(DemandeReversement $demande, string $cleIdempotence): string
            {
                $this->demandes[] = [$demande, $cleIdempotence];

                throw new PasserelleIndisponible('Délai dépassé.');
            }
        };
        $this->app->instance(PasserelleReversement::class, $passerelle);
        $reversement = app(Reversements::class)->demander($dev, 'mensuel');

        // Le dev change de nom entre les deux essais : le corps envoyé à SasPay ne bouge pas.
        $dev->forceFill(['name' => 'Autre nom'])->save();

        foreach ([1, 2] as $essai) {
            try {
                app(Reversements::class)->initier($reversement->refresh());
            } catch (PasserelleIndisponible) {
                // Le job réessaiera.
            }
        }

        $this->assertCount(2, $demandes);
        $this->assertEquals($demandes[0], $demandes[1]);
        $this->assertSame($reversement->cle_idempotence, $demandes[1][1]);
        $this->assertSame('2290197123456', $demandes[1][0]->numero);
    }

    public function test_le_numero_ne_change_pas_pendant_un_reversement(): void
    {
        $dev = $this->devConfirme('0197123456');
        $this->crediter($dev, 20000);
        $this->app->instance(PasserelleReversement::class, new class extends ReversementFactice {});
        app(Reversements::class)->demander($dev, 'demande');

        $this->actingAs($dev)->putJson('/api/me/coordonnees-reversement', [
            'pays' => 'BJ', 'reseau' => 'moov_bj', 'numero' => '0195000000', 'numero_confirmation' => '0195000000',
        ])->assertStatus(409);
    }

    public function test_le_reversement_mensuel_paie_les_devs_au_solde_positif(): void
    {
        $paye = $this->devConfirme('0197123456');
        $this->crediter($paye, 1200);
        $vide = $this->devConfirme('0197654321');

        (new ReversementsMensuels)->handle(app(Reversements::class), app(GrandLivre::class));

        $this->assertSame(['reussi'], Reversement::query()->where('dev_id', $paye->id)->pluck('statut')->all());
        $this->assertSame(0, Reversement::query()->where('dev_id', $vide->id)->count());
    }

    private function devConfirme(string $numero): User
    {
        $dev = User::factory()->github()->create();
        CoordonneesReversement::query()->create([
            'user_id' => $dev->id, 'pays' => 'BJ', 'reseau' => 'mtn_bj', 'numero' => '229'.$numero, 'numero_masque' => '+229 •• •• •• '.substr($numero, -4),
        ])->forceFill(['confirme_le' => now()])->save();

        return $dev;
    }

    /** Le dev a vendu son appli : une facture payée lui crédite la part de l'appli. */
    private function crediter(User $dev, int $montant): void
    {
        $facture = Facture::factory()->create(['montant_hebergement' => 3500, 'montant_appli' => $montant, 'montant_total' => 3500 + $montant, 'statut' => 'payee']);
        $facture->abonnement->forceFill(['dev_id' => $dev->id])->save();
        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id, 'client_id' => $facture->client_id, 'passerelle' => 'simulee', 'montant' => $facture->montant_total, 'statut' => 'reussi',
        ]);
        app(GrandLivre::class)->encaisser($facture->refresh(), $paiement);
    }
}

/** Passerelle de reversement qui ne répond jamais (le payout reste en attente). */
abstract class ReversementFactice implements PasserelleReversement
{
    public function nom(): string
    {
        return 'simulee';
    }

    public function initier(DemandeReversement $demande, string $cleIdempotence): string
    {
        return '0d0c8a5e-9b39-4d47-9a0e-5a1f2b3c4d5e';
    }

    public function verifier(string $payoutId): VerificationReversement
    {
        return new VerificationReversement('PENDING', null);
    }
}
