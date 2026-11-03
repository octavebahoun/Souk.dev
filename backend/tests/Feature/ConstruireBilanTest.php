<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Discussion;
use App\Models\Evenement;
use App\Models\Mission;
use App\Models\User;
use App\Reputation\ConstruireBilan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstruireBilanTest extends TestCase
{
    use RefreshDatabase;

    private function missionTerminee(User $client, User $auteur, int $note, ?string $le = null): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'auteur_id' => $auteur->id,
            'message' => 'Ajouter la prise de rendez-vous',
            'statut' => 'terminee',
            'note' => $note,
            'terminee_le' => $le ?? now(),
        ]);
    }

    public function test_il_rassemble_les_notes_des_missions_terminees(): void
    {
        [$dev, $client] = User::factory()->count(2)->create();
        $this->missionTerminee($client, $dev, 5);
        $this->missionTerminee($client, $dev, 4);

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertSame([5, 4], $bilan->notesMissions);
        $this->assertSame(2, $bilan->missionsTerminees());
        $this->assertSame(4.5, $bilan->noteMoyenne());
    }

    public function test_il_ignore_les_missions_en_cours_et_celles_des_autres(): void
    {
        [$dev, $client, $autreDev] = User::factory()->count(3)->create();
        $this->missionTerminee($client, $dev, 5);
        $this->missionTerminee($client, $autreDev, 1);          // la mission d'un autre dev
        Mission::create([                                        // encore en cours
            'client_id' => $client->id,
            'auteur_id' => $dev->id,
            'message' => 'En chantier',
        ]);

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertSame([5], $bilan->notesMissions);
    }

    public function test_une_mission_ou_le_dev_est_client_ne_compte_pas(): void
    {
        [$dev, $autreDev] = User::factory()->count(2)->create();
        $this->missionTerminee($dev, $autreDev, 5); // c'est lui qui a noté, pas lui qui est noté

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertSame([], $bilan->notesMissions);
        $this->assertNull($bilan->noteMoyenne());
    }

    public function test_l_activite_recente_ne_retient_que_les_trente_derniers_jours(): void
    {
        [$dev, $client] = User::factory()->count(2)->create();
        $this->missionTerminee($client, $dev, 5, now()->subDays(2)->toDateTimeString());
        $this->missionTerminee($client, $dev, 5, now()->subDays(29)->toDateTimeString());
        $this->missionTerminee($client, $dev, 5, now()->subDays(31)->toDateTimeString());

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertCount(3, $bilan->notesMissions);
        $this->assertSame(2, $bilan->actionsRecentes);
    }

    public function test_il_compte_les_correctifs_acceptes_les_applis_et_les_evenements_passes(): void
    {
        $dev = User::factory()->github()->create();
        $this->correctif($dev, accepte: true, le: now()->subDays(3));
        $this->correctif($dev, accepte: true, le: now()->subDays(60));
        $this->correctif($dev, accepte: false);
        Appli::factory()->for($dev, 'auteur')->create(['securite_statut' => 'verifie']);
        Appli::factory()->for($dev, 'auteur')->create(['securite_statut' => 'problemes', 'created_at' => now()->subDays(90)]);
        $this->evenement($dev, now()->subDays(10), inscrit: User::factory()->create());
        $this->evenement($dev, now()->addDays(10), inscrit: User::factory()->create());

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertSame(2, $bilan->bugsResolus);
        $this->assertSame(2, $bilan->applisPubliees);
        $this->assertSame(1, $bilan->applisSecurisees);
        $this->assertSame(1, $bilan->evenementsPasses);
        // Récent : un correctif, une appli, un événement (le reste a plus de 30 jours ou n'a pas eu lieu).
        $this->assertSame(3, $bilan->actionsRecentes);
    }

    public function test_rien_ne_compte_sans_validation_d_un_tiers(): void
    {
        $dev = User::factory()->github()->create();
        // Son correctif accepté sur sa propre discussion, son événement passé sans autre inscrit.
        $this->correctif($dev, accepte: true, le: now(), discussion: Discussion::factory()->create(['user_id' => $dev->id]));
        $this->evenement($dev, now()->subDay(), inscrit: $dev);

        $bilan = (new ConstruireBilan)->pour($dev);

        $this->assertSame(0, $bilan->bugsResolus);
        $this->assertSame(0, $bilan->evenementsPasses);
        $this->assertSame(0, $bilan->actionsRecentes);
    }

    public function test_un_dev_sans_rien_donne_un_bilan_vide(): void
    {
        $bilan = (new ConstruireBilan)->pour(User::factory()->create());

        $this->assertSame([], $bilan->notesMissions);
        $this->assertSame(0, $bilan->actionsRecentes);
        $this->assertSame(0, $bilan->bugsResolus);
        $this->assertSame(0, $bilan->applisPubliees);
    }

    public function test_la_note_dune_mission_remplit_terminee_le(): void
    {
        [$client, $auteur] = User::factory()->count(2)->create();
        $mission = Mission::create([
            'client_id' => $client->id,
            'auteur_id' => $auteur->id,
            'message' => 'Ajouter la prise de rendez-vous',
        ]);

        $this->actingAs($client)
            ->postJson("/api/missions/{$mission->id}/note", ['note' => 5])
            ->assertOk();

        $this->assertNotNull($mission->refresh()->terminee_le);
        $this->assertSame(1, (new ConstruireBilan)->pour($auteur)->actionsRecentes);
    }

    private function correctif(User $dev, bool $accepte, ?\DateTimeInterface $le = null, ?Discussion $discussion = null): Correctif
    {
        $correctif = Correctif::query()->create([
            'discussion_id' => ($discussion ?? Discussion::factory()->create())->id,
            'user_id' => $dev->id,
            'depot_url' => 'https://github.com/acme/momo/tree/correctif',
            'accepte' => $accepte,
        ]);
        $correctif->forceFill(['accepte_le' => $accepte ? $le : null])->save();

        return $correctif;
    }

    private function evenement(User $organisateur, \DateTimeInterface $date, ?User $inscrit = null): Evenement
    {
        $evenement = Evenement::query()->create([
            'user_id' => $organisateur->id,
            'titre' => 'Meetup Laravel Cotonou',
            'description' => 'Retours d\'expérience.',
            'date' => $date,
            'type' => 'presentiel',
            'lieu' => 'Cotonou',
        ]);

        if ($inscrit !== null) {
            $evenement->inscriptions()->create(['user_id' => $inscrit->id]);
        }

        return $evenement;
    }
}
