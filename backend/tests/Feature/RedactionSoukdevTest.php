<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** Un dépôt sans soukdev.json : la plateforme en écrit un, à ajouter au dépôt sur GitHub. */
class RedactionSoukdevTest extends TestCase
{
    use RefreshDatabase;

    public function test_propose_le_fichier_et_le_lien_github_deja_rempli(): void
    {
        $this->simulerDepot([
            'docker-compose.yml' => "services:\n  web:\n    image: nginx:alpine\n    expose: [\"80\"]\n    environment:\n      BOUTIQUE_NOM: \${BOUTIQUE_NOM}\n",
        ]);

        $reponse = $this->actingAs(User::factory()->github()->create())
            ->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/boutique'])
            ->assertOk();

        $texte = $reponse->json('soukdev_json');
        $this->assertSame([
            'version' => 1,
            'service_web' => ['nom' => 'web', 'port' => 80],
            'backend' => 'propre',
            'variables' => [['nom' => 'BOUTIQUE_NOM', 'source' => 'client', 'libelle' => 'Boutique nom', 'requis' => true]],
        ], json_decode($texte, true));
        $this->assertSame([], $reponse->json('a_verifier'));
        $this->assertSame([], $reponse->json('fichiers'));

        $lien = $reponse->json('lien_github');
        $this->assertStringStartsWith('https://github.com/acme/boutique/new/main?filename=soukdev.json&value=', $lien);
        parse_str((string) parse_url($lien, PHP_URL_QUERY), $parametres);
        $this->assertSame($texte, $parametres['value']);
        $this->assertSame(0, Appli::query()->count());
    }

    public function test_un_site_statique_recoit_ses_fichiers_docker_chacun_avec_son_lien_sur_la_branche_de_l_url(): void
    {
        $this->simulerDepot(['index.html' => '<h1>Bonjour</h1>']);

        $reponse = $this->actingAs(User::factory()->github()->create())
            ->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/boutique/tree/feature/souk'])
            ->assertOk()
            ->assertJsonPath('lien_github', fn (string $lien) => str_starts_with($lien, 'https://github.com/acme/boutique/new/feature/souk?'));

        $fichiers = $reponse->json('fichiers');
        $this->assertSame(['Dockerfile', '.dockerignore', 'docker-compose.yml'], array_column($fichiers, 'chemin'));
        foreach ($fichiers as $fichier) {
            parse_str((string) parse_url($fichier['lien_github'], PHP_URL_QUERY), $parametres);
            $this->assertStringStartsWith('https://github.com/acme/boutique/new/feature/souk?', $fichier['lien_github']);
            $this->assertSame(['filename' => $fichier['chemin'], 'value' => $fichier['contenu']], $parametres);
        }
    }

    public function test_un_depot_qui_a_deja_un_soukdev_json_est_renvoye_a_la_verification(): void
    {
        $this->simulerDepot(['soukdev.json' => '{}']);

        $this->actingAs(User::factory()->github()->create())
            ->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/boutique'])
            ->assertStatus(422)
            ->assertJsonPath('errors.depot_url.0', 'Ce dépôt a déjà un soukdev.json : lancez plutôt « Vérifier le dépôt ».');
    }

    public function test_un_depot_qui_ne_se_clone_pas_est_refuse_en_422(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'introuvable', exitCode: 128)]);

        $this->actingAs(User::factory()->github()->create())
            ->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/inconnu'])
            ->assertStatus(422)
            ->assertJsonPath('errors.depot_url.0', 'Impossible de cloner le dépôt GitHub public.');
    }

    public function test_un_compte_sans_github_recoit_403(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/boutique'])
            ->assertForbidden();
    }

    public function test_un_invite_recoit_401(): void
    {
        $this->postJson('/api/apps/soukdev-json', ['depot_url' => 'https://github.com/acme/boutique'])
            ->assertUnauthorized();
    }

    /** @param  array<string, string>  $fichiers */
    private function simulerDepot(array $fichiers): void
    {
        Process::fake(function (PendingProcess $process) use ($fichiers) {
            if (($process->command[0] ?? '') !== 'git') {
                return Process::result(errorOutput: 'inattendu', exitCode: 1);
            }

            // La branche par défaut du dépôt, lue après le clonage.
            if (($process->command[1] ?? '') === 'symbolic-ref') {
                return Process::result("main\n");
            }

            $destination = $process->command[array_key_last($process->command)];
            File::ensureDirectoryExists($destination);
            foreach ($fichiers as $chemin => $contenu) {
                file_put_contents($destination.DIRECTORY_SEPARATOR.$chemin, $contenu);
            }

            return Process::result();
        });
    }
}
