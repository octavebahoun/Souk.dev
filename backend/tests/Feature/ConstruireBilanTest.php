<?php

namespace Tests\Feature;

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
}
