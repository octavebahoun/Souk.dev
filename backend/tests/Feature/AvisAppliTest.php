<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Appli;
use App\Models\AvisAppli;
use App\Models\Deploiement;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avis des applis : seulement par un client qui en a eu une copie payée, de 1 à 5, un par client
 * et par appli ; la moyenne apparaît sur la fiche et sert au tri du Store.
 */
class AvisAppliTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_qui_a_paye_une_copie_note_l_appli_et_la_moyenne_suit(): void
    {
        $appli = Appli::factory()->create();
        $premier = $this->clientAyantPaye($appli);
        $second = $this->clientAyantPaye($appli);

        $this->actingAs($premier)
            ->postJson("/api/apps/{$appli->id}/avis", ['note' => 5, 'commentaire' => 'Simple et fiable.'])
            ->assertCreated()
            ->assertJsonPath('note', 5)
            ->assertJsonPath('commentaire', 'Simple et fiable.')
            ->assertJsonPath('auteur.id', $premier->id);
        $this->actingAs($second)
            ->postJson("/api/apps/{$appli->id}/avis", ['note' => 4])
            ->assertCreated()
            ->assertJsonPath('commentaire', null);

        $this->getJson("/api/apps/{$appli->id}")
            ->assertOk()
            ->assertJsonPath('note_moyenne', 4.5)
            ->assertJsonPath('nb_avis', 2);
    }

    public function test_seul_un_client_avec_une_copie_payee_peut_noter(): void
    {
        $appli = Appli::factory()->create();
        $copie = Deploiement::factory()->create(['appli_id' => $appli->id]);
        Facture::factory()->create(['abonnement_id' => Abonnement::factory()->create(['deploiement_id' => $copie->id])->id]);
        $autreAppli = Appli::factory()->create();

        $this->postJson("/api/apps/{$appli->id}/avis", ['note' => 5])->assertUnauthorized();
        // Facture encore à payer : la copie n'a jamais tourné.
        $this->actingAs($copie->utilisateur)
            ->postJson("/api/apps/{$appli->id}/avis", ['note' => 5])
            ->assertForbidden()
            ->assertJsonPath('message', 'Seul un client qui a eu une copie payée de cette appli peut la noter.');
        // Payer la copie d'une autre appli ne donne pas le droit de noter celle-ci.
        $this->actingAs($this->clientAyantPaye($autreAppli))
            ->postJson("/api/apps/{$appli->id}/avis", ['note' => 5])
            ->assertForbidden();
        $this->actingAs($appli->auteur)
            ->postJson("/api/apps/{$appli->id}/avis", ['note' => 5])
            ->assertForbidden();

        $this->assertSame(0, AvisAppli::query()->count());
        $this->assertSame(0, $appli->fresh()->avis_nombre);
    }

    public function test_un_seul_avis_par_client_qui_peut_ensuite_le_modifier(): void
    {
        $appli = Appli::factory()->create();
        $client = $this->clientAyantPaye($appli);

        $this->actingAs($client)->postJson("/api/apps/{$appli->id}/avis", ['note' => 2, 'commentaire' => 'Lent.'])->assertCreated();
        $this->actingAs($client)->postJson("/api/apps/{$appli->id}/avis", ['note' => 5])->assertConflict();

        // Sans commentaire envoyé, le commentaire reste ; null l'efface.
        $this->actingAs($client)
            ->patchJson("/api/apps/{$appli->id}/avis", ['note' => 4])
            ->assertOk()
            ->assertJsonPath('note', 4)
            ->assertJsonPath('commentaire', 'Lent.');
        $this->actingAs($client)
            ->patchJson("/api/apps/{$appli->id}/avis", ['note' => 4, 'commentaire' => null])
            ->assertOk()
            ->assertJsonPath('commentaire', null);

        $this->assertSame(1, AvisAppli::query()->count());
        $this->assertSame(4.0, $appli->fresh()->noteMoyenne());
        $this->actingAs($this->clientAyantPaye($appli))
            ->patchJson("/api/apps/{$appli->id}/avis", ['note' => 1])
            ->assertNotFound();
    }

    public function test_la_note_va_de_1_a_5_et_le_commentaire_reste_court(): void
    {
        $appli = Appli::factory()->create();
        $client = $this->clientAyantPaye($appli);

        foreach ([['note' => 0], ['note' => 6], ['note' => 'cinq'], [], ['note' => 3, 'commentaire' => str_repeat('a', 1001)]] as $saisie) {
            $this->actingAs($client)->postJson("/api/apps/{$appli->id}/avis", $saisie)->assertUnprocessable();
        }
        $this->assertSame(0, AvisAppli::query()->count());
    }

    public function test_la_liste_est_publique_et_dit_au_client_s_il_peut_noter(): void
    {
        $appli = Appli::factory()->create();
        $ancien = $this->clientAyantPaye($appli);
        AvisAppli::factory()->create(['appli_id' => $appli->id, 'user_id' => $ancien->id, 'note' => 3, 'created_at' => now()->subDay()]);
        AvisAppli::factory()->create(['appli_id' => $appli->id, 'user_id' => $this->clientAyantPaye($appli)->id, 'note' => 5]);
        $nouveau = $this->clientAyantPaye($appli);

        $this->getJson("/api/apps/{$appli->id}/avis")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.note', 5)
            ->assertJsonPath('peut_noter', false)
            ->assertJsonPath('mon_avis', null);
        $this->actingAs($nouveau)->getJson("/api/apps/{$appli->id}/avis")->assertJsonPath('peut_noter', true);
        $this->actingAs($ancien)
            ->getJson("/api/apps/{$appli->id}/avis")
            ->assertJsonPath('peut_noter', false)
            ->assertJsonPath('mon_avis.note', 3);
    }

    public function test_le_store_trie_par_note_sans_qu_un_seul_avis_passe_devant_vingt(): void
    {
        $sansAvis = Appli::factory()->create();
        $unSeulCinq = Appli::factory()->create(['avis_nombre' => 1, 'avis_somme' => 5]);
        $vingtAQuatreSept = Appli::factory()->create(['avis_nombre' => 20, 'avis_somme' => 94]);
        $mauvaise = Appli::factory()->create(['avis_nombre' => 4, 'avis_somme' => 6]);

        $ordre = $this->getJson('/api/apps?tri=note')->assertOk()->json('data.*.id');

        $this->assertSame([$vingtAQuatreSept->id, $unSeulCinq->id, $sansAvis->id, $mauvaise->id], $ordre);
    }

    private function clientAyantPaye(Appli $appli): User
    {
        $copie = Deploiement::factory()->create(['appli_id' => $appli->id]);
        $abonnement = Abonnement::factory()->create(['deploiement_id' => $copie->id]);
        Facture::factory()->create(['abonnement_id' => $abonnement->id, 'statut' => 'payee', 'payee_le' => now()]);

        return $copie->utilisateur;
    }
}
