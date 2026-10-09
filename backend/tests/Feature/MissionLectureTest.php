<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionLectureTest extends TestCase
{
    use RefreshDatabase;

    private function mission(User $client, User $auteur): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'auteur_id' => $auteur->id,
            'message' => 'Ajouter la prise de rendez-vous',
            'budget' => 150000,
        ]);
    }

    public function test_je_vois_mes_missions_comme_client_et_comme_auteur(): void
    {
        [$moi, $autre, $inconnu] = User::factory()->count(3)->create();
        $this->mission($moi, $autre);     // je suis client
        $this->mission($autre, $moi);     // je suis auteur
        $this->mission($autre, $inconnu); // pas la mienne

        $this->actingAs($moi)->getJson('/api/missions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'app_id', 'statut', 'note', 'client', 'auteur', 'message', 'budget', 'delai', 'cree_le']], 'links', 'meta']);
    }

    public function test_seuls_le_client_et_l_auteur_voient_une_mission(): void
    {
        [$client, $auteur, $intrus] = User::factory()->count(3)->create();
        $mission = $this->mission($client, $auteur);

        $this->actingAs($intrus)->getJson("/api/missions/{$mission->id}")->assertForbidden();

        $this->actingAs($auteur)->getJson("/api/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('statut', 'en_cours')
            ->assertJsonPath('note', null);
    }

    public function test_sans_connexion_401(): void
    {
        $this->getJson('/api/missions')->assertUnauthorized();
    }
}
