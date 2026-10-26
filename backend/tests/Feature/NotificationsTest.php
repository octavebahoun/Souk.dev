<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Correctif;
use App\Models\Discussion;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\Reversement;
use App\Models\User;
use App\Notifications\AvertissementArret;
use App\Notifications\CopieEnIncident;
use App\Notifications\CopieResiliee;
use App\Notifications\CopieSuspendue;
use App\Notifications\CorrectifRecu;
use App\Notifications\CorrectifRetenu;
use App\Notifications\FactureEmise;
use App\Notifications\FactureEnRetard;
use App\Notifications\NouveauMessageMission;
use App\Notifications\PaiementRecu;
use App\Notifications\ReversementEffectue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

/**
 * Centre de notifications : chaque type arrive dans Souk.dev et par e-mail, selon les
 * préférences du compte ; seul le destinataire lit ses notifications.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_type_arrive_dans_le_centre_et_par_e_mail(): void
    {
        $client = User::factory()->client()->create();
        $dev = User::factory()->create();
        $abonnement = Abonnement::factory()->create();
        $abonnement->forceFill(['suspendu_le' => now()->subDays(23)])->save();
        $facture = Facture::factory()->create(['abonnement_id' => $abonnement->id]);
        $discussion = Discussion::factory()->create(['user_id' => $client->id, 'titre' => 'Le callback MoMo renvoie 500']);
        $correctif = Correctif::query()->create([
            'discussion_id' => $discussion->id, 'user_id' => $dev->id, 'accepte' => false,
            'depot_url' => 'https://github.com/dev/momo', 'description' => 'Le callback vérifie la commande.',
        ]);
        $mission = Mission::factory()->create(['client_id' => $client->id, 'auteur_id' => $dev->id]);

        $envois = [
            [$client, new FactureEmise($facture), 'echeance'],
            [$client, new FactureEnRetard($facture), 'echeance'],
            [$client, new PaiementRecu($facture), 'echeance'],
            [$client, new CopieSuspendue($facture), 'suspension'],
            [$client, new AvertissementArret($abonnement), 'suspension'],
            [$client, new CopieResiliee($abonnement), 'suspension'],
            [$client, new CopieEnIncident($abonnement), 'suspension'],
            [$dev, new ReversementEffectue($this->reversement($dev, 'reussi')), 'reversement'],
            [$dev, new ReversementEffectue($this->reversement($dev, 'echoue')), 'reversement'],
            [$client, new CorrectifRecu($correctif), 'correctif_propose'],
            [$dev, new CorrectifRetenu($correctif), 'correctif_accepte'],
            [$client, new NouveauMessageMission(MissionMessage::query()->create(['mission_id' => $mission->id, 'auteur_id' => $dev->id, 'texte' => 'Je livre vendredi.'])), 'message_mission'],
        ];
        foreach ($envois as [$compte, $notification]) {
            $compte->notify($notification);
        }

        $this->assertSame(count($envois), $this->transport()->messages()->count());
        // Créées dans la même seconde : l'ordre entre elles n'est pas garanti, le contenu l'est.
        $attendus = array_column(array_filter($envois, fn (array $envoi) => $envoi[0]->is($client)), 2);
        $recus = $this->actingAs($client)->getJson('/api/me/notifications?per_page=50')->assertOk()->json('data.*.type');
        sort($attendus);
        sort($recus);
        $this->assertSame($attendus, $recus);
        $this->actingAs($dev)
            ->getJson('/api/me/notifications')
            ->assertOk()
            ->assertJsonPath('non_lues', 3)
            ->assertJsonFragment(['type' => 'correctif_accepte', 'titre' => 'Correctif accepté', 'lien' => '/echange/'.$discussion->id, 'lue' => false]);
    }

    public function test_les_preferences_coupent_un_canal_par_type_sauf_l_e_mail_obligatoire(): void
    {
        $client = User::factory()->client()->create();
        $facture = Facture::factory()->create(['abonnement_id' => Abonnement::factory()->create()->id]);

        $this->actingAs($client)->putJson('/api/me/preferences-notifications', ['preferences' => [
            ['type' => 'echeance', 'application' => true, 'email' => false],
            ['type' => 'suspension', 'application' => false, 'email' => false],
        ]])
            ->assertOk()
            ->assertJsonPath('types.3.type', 'echeance')
            ->assertJsonPath('types.3.email', false)
            ->assertJsonPath('types.4.type', 'suspension')
            ->assertJsonPath('types.4.application', false)
            ->assertJsonPath('types.4.email', true)
            ->assertJsonPath('types.4.email_obligatoire', true)
            ->assertJsonPath('types.0.email', true);

        $client->refresh()->notify(new FactureEmise($facture));
        $client->notify(new CopieSuspendue($facture));

        $this->assertSame(['Votre copie de '.$facture->abonnement->appli->nom.' est suspendue'], $this->transport()->messages()->map(fn ($message) => $message->getOriginalMessage()->getSubject())->all());
        $this->actingAs($client)->getJson('/api/me/notifications')->assertJsonPath('data.*.type', ['echeance']);
    }

    public function test_les_preferences_refusent_un_type_inconnu_ou_regle_deux_fois(): void
    {
        $compte = User::factory()->create();

        $this->actingAs($compte)->putJson('/api/me/preferences-notifications', ['preferences' => [['type' => 'publicite', 'application' => true, 'email' => true]]])
            ->assertUnprocessable();
        $this->actingAs($compte)->putJson('/api/me/preferences-notifications', ['preferences' => [
            ['type' => 'echeance', 'application' => true, 'email' => true],
            ['type' => 'echeance', 'application' => false, 'email' => false],
        ]])->assertUnprocessable();
        $this->actingAs($compte)->getJson('/api/me/preferences-notifications')->assertOk()->assertJsonCount(6, 'types');
        $this->assertNull($compte->fresh()->preferences_notifications);
    }

    public function test_le_centre_filtre_les_non_lues_et_marque_comme_lu(): void
    {
        $dev = User::factory()->create();
        $dev->notify(new ReversementEffectue($this->reversement($dev, 'reussi')));
        $dev->notify(new ReversementEffectue($this->reversement($dev, 'echoue')));
        $id = $dev->notifications()->firstOrFail()->id;

        $this->actingAs($dev)->postJson("/api/me/notifications/{$id}/lecture")->assertOk()->assertJsonPath('lue', true);
        $this->actingAs($dev)->getJson('/api/me/notifications?filtre=non_lues')->assertJsonCount(1, 'data')->assertJsonPath('non_lues', 1);
        $this->actingAs($dev)->postJson('/api/me/notifications/lecture')->assertNoContent();
        $this->actingAs($dev)->getJson('/api/me/notifications')->assertJsonCount(2, 'data')->assertJsonPath('non_lues', 0);
        $this->actingAs($dev)->getJson('/api/me/notifications?filtre=archives')->assertUnprocessable();
    }

    public function test_la_notification_d_un_autre_compte_repond_404(): void
    {
        $dev = User::factory()->create();
        $dev->notify(new ReversementEffectue($this->reversement($dev, 'reussi')));
        $notification = $dev->notifications()->firstOrFail();
        $autre = User::factory()->create();

        $this->postJson("/api/me/notifications/{$notification->id}/lecture")->assertUnauthorized();
        $this->actingAs($autre)->postJson("/api/me/notifications/{$notification->id}/lecture")->assertNotFound();
        $this->actingAs($autre)->postJson('/api/me/notifications/lecture')->assertNoContent();
        $this->actingAs($autre)->getJson('/api/me/notifications')->assertJsonCount(0, 'data');
        $this->actingAs($autre)->postJson('/api/me/notifications/pas-un-uuid/lecture')->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_les_comptes_de_demo_ont_le_centre_sans_jamais_d_e_mail(): void
    {
        $demo = User::factory()->create(['est_demo' => true, 'email' => 'awa@demo.souk.dev']);

        $demo->notify(new ReversementEffectue($this->reversement($demo, 'reussi')));

        $this->assertSame(0, $this->transport()->messages()->count());
        $this->assertSame(1, $demo->notifications()->count());
    }

    public function test_un_fil_de_mission_n_envoie_qu_un_e_mail_par_demi_heure(): void
    {
        $mission = Mission::factory()->create();
        $message = fn (string $texte) => MissionMessage::query()->create(['mission_id' => $mission->id, 'auteur_id' => $mission->auteur_id, 'texte' => $texte]);

        $mission->client->notify(new NouveauMessageMission($message('Premier jet prêt.')));
        $mission->client->notify(new NouveauMessageMission($message('Et la maquette mobile.')));
        $this->travel(31)->minutes();
        $mission->client->notify(new NouveauMessageMission($message('Vous avez vu ?')));

        $this->assertSame(3, $mission->client->notifications()->count());
        $this->assertSame(2, $this->transport()->messages()->count());
    }

    public function test_correctifs_et_messages_de_mission_previennent_l_autre_personne(): void
    {
        Notification::fake();
        $auteur = User::factory()->create();
        $dev = User::factory()->create(['github_lie' => true, 'username' => 'wasfade']);
        $discussion = Discussion::factory()->create(['user_id' => $auteur->id]);

        $correctif = $this->actingAs($dev)->postJson("/api/discussions/{$discussion->id}/correctifs", [
            'depot_url' => 'https://github.com/wasfade/momo',
            'description' => 'Le callback vérifie la commande.',
        ])->assertCreated()->json('id');
        $this->actingAs($auteur)->postJson("/api/correctifs/{$correctif}/accepter")->assertOk();

        $mission = Mission::factory()->create(['auteur_id' => $dev->id]);
        $this->actingAs($dev)->postJson("/api/missions/{$mission->id}/messages", ['texte' => 'Je livre vendredi.'])->assertCreated();

        Notification::assertSentTo($auteur, CorrectifRecu::class);
        Notification::assertSentTo($dev, CorrectifRetenu::class);
        Notification::assertSentTo($mission->client, NouveauMessageMission::class);
        Notification::assertNotSentTo($dev, NouveauMessageMission::class);
        Notification::assertCount(3);
    }

    public function test_la_diffusion_en_direct_reprend_la_forme_de_l_api(): void
    {
        $dev = User::factory()->create();
        $notification = new ReversementEffectue($this->reversement($dev, 'reussi'));
        $notification->id = (string) Str::uuid();

        $notification->toBroadcast($dev);

        $this->assertSame('utilisateur.'.$dev->id, $dev->receivesBroadcastNotificationsOn());
        $this->assertSame('notification.creee', $notification->broadcastAs());
        $this->assertSame(['id', 'type', 'titre', 'texte', 'lien', 'lue', 'cree_le'], array_keys($notification->broadcastWith()));
        $this->assertSame('reversement', $notification->broadcastWith()['type']);
    }

    private function reversement(User $dev, string $statut): Reversement
    {
        return Reversement::query()->create([
            'dev_id' => $dev->id,
            'montant' => 15000,
            'passerelle' => 'simulee',
            'cle_idempotence' => (string) Str::uuid(),
            'pays' => 'BJ',
            'reseau' => 'mtn_bj',
            'numero_masque' => '+229 01 •• •• •• 56',
            'beneficiaire' => ['numero' => '+2290197123456', 'email' => $dev->email, 'nom' => $dev->name],
            'declenchement' => 'demande',
            'statut' => $statut,
        ]);
    }

    private function transport(): TransportInterface
    {
        return Mail::mailer()->getSymfonyTransport();
    }
}
