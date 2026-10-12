<?php

namespace Tests\Feature;

use App\Jobs\MesurerMemoire;
use App\Models\Appli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationAppliTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_publication_lance_la_mesure_en_tache_de_fond(): void
    {
        Queue::fake();
        Storage::fake('public');
        $this->simulerDepot();
        $utilisateur = User::factory()->create();

        $reponse = $this->actingAs($utilisateur)->post('/api/apps', $this->payload(), [
            'Accept' => 'application/json',
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('nom', 'Pharmacie')
            ->assertJsonPath('taille', 'petite')
            ->assertJsonPath('backend', 'propre')
            ->assertJsonPath('mesure.statut', 'en_cours')
            ->assertJsonPath('mesure.memoire_max_mo', null)
            ->assertJsonPath('mesure.taille_recommandee', null)
            ->assertJsonPath('variables_client.0.nom', 'APP_NOM');

        $appli = Appli::query()->first();
        $this->assertNotNull($appli);
        $this->assertSame('en_cours', $appli->mesure_statut);
        $this->assertSame(1, $appli->mesure_jeton);

        Queue::assertPushed(MesurerMemoire::class, function (MesurerMemoire $job) use ($appli) {
            return $job->appliId === $appli->id && $job->jeton === 1;
        });
    }

    public function test_un_invite_recoit_401(): void
    {
        Queue::fake();

        $this->post('/api/apps', $this->payload(), [
            'Accept' => 'application/json',
        ])->assertUnauthorized();

        Queue::assertNothingPushed();
        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_depot_invalide_est_refuse_sans_lancer_la_mesure(): void
    {
        Queue::fake();
        Storage::fake('public');
        $this->simulerDepot(compose: "services:\n  front:\n    image: nginx:alpine\n    privileged: true\n");

        $this->actingAs(User::factory()->create())
            ->post('/api/apps', $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');

        Queue::assertNothingPushed();
        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_soukdev_invalide_est_refuse_sans_lancer_la_mesure(): void
    {
        Queue::fake();
        Storage::fake('public');
        $this->simulerDepot(manifeste: ['version' => 2]);

        $this->actingAs(User::factory()->create())
            ->post('/api/apps', $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');

        Queue::assertNothingPushed();
        $this->assertSame(0, Appli::query()->count());
    }

    /**
     * @param  array<string, mixed>  $manifeste
     */
    private function simulerDepot(?array $manifeste = null, ?string $compose = null): void
    {
        $manifeste ??= [
            'version' => 1,
            'service_web' => ['nom' => 'front', 'port' => 3000],
            'backend' => 'propre',
            'variables' => [
                ['nom' => 'APP_NOM', 'source' => 'client', 'libelle' => 'Nom de votre structure', 'requis' => true],
                ['nom' => 'DB_PASSWORD', 'source' => 'plateforme'],
            ],
        ];
        $compose ??= "services:\n  front:\n    image: nginx:alpine\n";

        Process::fake(function (PendingProcess $process) use ($manifeste, $compose) {
            if (($process->command[0] ?? '') !== 'git') {
                return Process::result(errorOutput: 'inattendu', exitCode: 1);
            }

            $destination = $process->command[array_key_last($process->command)];
            File::ensureDirectoryExists($destination);
            file_put_contents(
                $destination.DIRECTORY_SEPARATOR.'soukdev.json',
                json_encode($manifeste, JSON_THROW_ON_ERROR),
            );
            file_put_contents($destination.DIRECTORY_SEPARATOR.'docker-compose.yml', $compose);

            return Process::result();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'nom' => 'Pharmacie',
            'description' => 'Gestion d\'une officine.',
            'categorie' => 'sante',
            'depot_url' => 'https://github.com/acme/cobaye',
            'demo_url' => 'https://demo.example.com',
            'prix' => 15000,
            'type_prix' => 'mensuel',
            'taille' => 'petite',
            'stack' => ['laravel', 'react'],
            'captures' => [UploadedFile::fake()->image('couverture.png', 80, 80)],
        ];
    }
}
