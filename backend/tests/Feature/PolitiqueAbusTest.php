<?php

namespace Tests\Feature;

use App\Jobs\SurveillerProcesseur;
use App\Jobs\SuspendreCopieAbus;
use App\Models\Deploiement;
use App\Models\Machine;
use App\Models\User;
use App\Notifications\AlerteAdmin;
use App\Notifications\CopieSuspendueAbus;
use App\Securite\SuspensionAbus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PolitiqueAbusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Queue::fake([SuspendreCopieAbus::class]);
    }

    public function test_une_copie_au_processeur_sature_une_heure_est_suspendue_pour_minage(): void
    {
        $machine = Machine::assurerLocale();
        $mineuse = Deploiement::factory()->create(['copie' => 'mineuse', 'taille' => 'petite', 'machine_id' => $machine->id]);
        $sage = Deploiement::factory()->create(['copie' => 'sage', 'taille' => 'petite', 'machine_id' => $machine->id]);
        // Petite formule : 0,5 cœur. 49 % d'un cœur = 98 % de la formule ; 10 % reste sage.
        $this->dockerMesure(['mineuse' => ['49.10%', '0.02%'], 'sage' => ['10.00%']]);

        for ($releve = 1; $releve < SurveillerProcesseur::RELEVES; $releve++) {
            app()->call([new SurveillerProcesseur, 'handle']);
        }

        $this->assertNull($mineuse->refresh()->suspension_admin_motif, 'Onze relevés (55 minutes) ne suffisent pas.');

        app()->call([new SurveillerProcesseur, 'handle']);

        $this->assertSame('suspendu', $mineuse->refresh()->etat);
        $this->assertStringContainsString('Minage présumé', (string) $mineuse->suspension_admin_motif);
        $this->assertSame('en_ligne', $sage->refresh()->etat);
        Queue::assertPushed(SuspendreCopieAbus::class, fn ($job) => $job->deploiementId === $mineuse->id);
        Notification::assertSentTo($mineuse->utilisateur, CopieSuspendueAbus::class);
        Notification::assertSentOnDemand(AlerteAdmin::class);
    }

    public function test_un_releve_normal_remet_le_compte_a_zero(): void
    {
        $machine = Machine::assurerLocale();
        $copie = Deploiement::factory()->create(['copie' => 'calcul', 'taille' => 'moyenne', 'machine_id' => $machine->id]);

        $this->dockerMesure(['calcul' => ['95.00%']]);
        for ($releve = 1; $releve < SurveillerProcesseur::RELEVES; $releve++) {
            app()->call([new SurveillerProcesseur, 'handle']);
        }

        $this->dockerMesure(['calcul' => ['20.00%']]);
        app()->call([new SurveillerProcesseur, 'handle']);

        $this->dockerMesure(['calcul' => ['95.00%']]);
        for ($releve = 1; $releve < SurveillerProcesseur::RELEVES; $releve++) {
            app()->call([new SurveillerProcesseur, 'handle']);
        }

        $this->assertSame('en_ligne', $copie->refresh()->etat);
        Queue::assertNothingPushed();
    }

    public function test_une_copie_signalee_pour_hameconnage_est_suspendue_aussitot(): void
    {
        $membre = User::factory()->create();
        $copie = Deploiement::factory()->create(['copie' => 'faux-wave']);

        $this->actingAs($membre)->postJson('/api/signalements', [
            'cible' => 'copie',
            'cible_id' => $copie->id,
            'motif' => 'hameconnage',
            'details' => 'Page qui imite la connexion Wave et demande le code secret.',
        ])->assertCreated();

        $this->assertSame('suspendu', $copie->refresh()->etat);
        $this->assertStringContainsString('Hameçonnage signalé', (string) $copie->suspension_admin_motif);
        Queue::assertPushed(SuspendreCopieAbus::class, fn ($job) => $job->deploiementId === $copie->id);
        Notification::assertSentOnDemand(AlerteAdmin::class, fn (AlerteAdmin $alerte) => str_contains(implode(' ', $alerte->lignes), 'suspendue en attendant'));
    }

    public function test_un_membre_ne_fait_pas_tomber_des_copies_en_serie(): void
    {
        $membre = User::factory()->create();
        $copies = Deploiement::factory()->count(4)->create();

        foreach ($copies as $copie) {
            $this->actingAs($membre)->postJson('/api/signalements', [
                'cible' => 'copie',
                'cible_id' => $copie->id,
                'motif' => 'hameconnage',
            ])->assertCreated();
        }

        $this->assertSame(['suspendu', 'suspendu', 'suspendu', 'en_ligne'], $copies->map(fn ($copie) => $copie->refresh()->etat)->all());
    }

    public function test_un_autre_motif_ne_suspend_rien(): void
    {
        $copie = Deploiement::factory()->create();

        $this->actingAs(User::factory()->create())->postJson('/api/signalements', [
            'cible' => 'copie',
            'cible_id' => $copie->id,
            'motif' => 'spam',
        ])->assertCreated();

        $this->assertSame('en_ligne', $copie->refresh()->etat);
    }

    /**
     * Imite docker stats et docker ps : chaque copie a ses conteneurs, avec leur processeur.
     *
     * @param  array<string, list<string>>  $parCopie
     */
    private function dockerMesure(array $parCopie): void
    {
        $stats = [];
        $ps = [];
        $numero = 0;

        foreach ($parCopie as $copie => $pourcentages) {
            foreach ($pourcentages as $pourcentage) {
                $id = str_pad((string) ++$numero, 64, 'a');
                $stats[] = substr($id, 0, 12)."\t{$pourcentage}";
                $ps[] = "{$id}\t{$copie}";
            }
        }

        Process::fake(function (PendingProcess $process) use ($stats, $ps) {
            $commande = implode(' ', (array) $process->command);

            return match (true) {
                str_starts_with($commande, 'docker stats') => Process::result(implode("\n", $stats)),
                str_starts_with($commande, 'docker ps') => Process::result(implode("\n", $ps)),
                default => Process::result('', 'commande inattendue', 1),
            };
        });
    }
}
