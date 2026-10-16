<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VerificationDepotTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_depot_valide_renvoie_ce_que_la_plateforme_a_lu(): void
    {
        $this->simulerDepot();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/verification', [
                'depot_url' => 'https://github.com/acme/cobaye',
            ])
            ->assertOk()
            ->assertExactJson([
                'service_web' => ['nom' => 'front', 'port' => 3000],
                'backend' => 'propre',
                'variables_client' => [
                    [
                        'nom' => 'APP_NOM',
                        'libelle' => 'Nom de votre structure',
                        'requis' => true,
                    ],
                ],
            ]);

        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_invite_recoit_401(): void
    {
        $this->postJson('/api/apps/verification', [
            'depot_url' => 'https://github.com/acme/cobaye',
        ])->assertUnauthorized();

        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_depot_invalide_est_refuse_sans_publier(): void
    {
        $this->simulerDepot(compose: "services:\n  front:\n    image: nginx:alpine\n    privileged: true\n");

        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/verification', [
                'depot_url' => 'https://github.com/acme/cobaye',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');

        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_soukdev_invalide_est_refuse_sans_publier(): void
    {
        $this->simulerDepot(manifeste: ['version' => 2]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/verification', [
                'depot_url' => 'https://github.com/acme/cobaye',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');

        $this->assertSame(0, Appli::query()->count());
    }

    public function test_une_url_manquante_est_refusee(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/verification', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');
    }

    public function test_un_compose_absent_est_refuse_sans_publier(): void
    {
        $this->simulerDepot(compose: false);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/apps/verification', [
                'depot_url' => 'https://github.com/acme/cobaye',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('depot_url');

        $this->assertSame(0, Appli::query()->count());
    }

    /**
     * @param  array<string, mixed>|null  $manifeste
     */
    private function simulerDepot(?array $manifeste = null, string|false|null $compose = null): void
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

            if ($compose !== false) {
                file_put_contents($destination.DIRECTORY_SEPARATOR.'docker-compose.yml', $compose);
            }

            return Process::result();
        });
    }
}
