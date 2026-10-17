<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UtilisateurGithub;
use Tests\TestCase;

final class GithubConnexionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_redirection_demande_uniquement_les_depots_publics(): void
    {
        $driver = \Mockery::mock();
        $driver->shouldReceive('scopes')
            ->once()
            ->with(['read:user', 'user:email'])
            ->andReturnSelf();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect()->away('https://github.com/login/oauth/authorize'));

        Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver);

        $this->get('/auth/github?profil=dev')
            ->assertRedirect('https://github.com/login/oauth/authorize');
    }

    public function test_le_retour_github_ouvre_la_session_dun_dev(): void
    {
        $this->simulerGithub('42', 'wasfade', 'Wasfade', 'wasfade@example.com');

        $this->withSession(['profil_entree' => 'dev'])
            ->get('/auth/github/callback')
            ->assertRedirect('http://localhost:5173/echange');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'github_id' => '42',
            'username' => 'wasfade',
            'email' => 'wasfade@example.com',
            'profil' => 'dev',
            'github_lie' => true,
        ]);
    }

    public function test_un_client_deja_connecte_lie_github_sans_changer_de_profil(): void
    {
        $client = User::factory()->client()->create(['email' => 'agence@example.com']);

        $this->simulerGithub('7', 'agence', 'Agence', 'agence@example.com');

        $this->actingAs($client)
            ->get('/auth/github/callback')
            ->assertRedirect('http://localhost:5173/store');

        $client->refresh();
        $this->assertTrue($client->github_lie);
        $this->assertSame('7', $client->github_id);
        $this->assertSame('client', $client->profil->value);
        $this->assertSame($client->id, Auth::id());
    }

    public function test_un_github_deja_lie_a_un_autre_compte_est_refuse(): void
    {
        User::factory()->create([
            'github_id' => '7',
            'username' => 'occupe',
            'github_lie' => true,
        ]);
        $client = User::factory()->client()->create();

        $this->simulerGithub('7', 'occupe', 'Occupe', 'autre@example.com');

        $this->actingAs($client)
            ->get('/auth/github/callback')
            ->assertRedirect('http://localhost:5173/?erreur=github_deja_lie');

        $client->refresh();
        $this->assertFalse($client->github_lie);
    }

    public function test_un_choix_dentree_inconnu_est_refuse(): void
    {
        $this->getJson('/auth/github?profil=admin')
            ->assertStatus(422)
            ->assertJsonPath('errors.profil.0', 'Le choix d\'entrée doit être dev ou client.');
    }

    private function simulerGithub(string $id, string $username, string $nom, string $email): void
    {
        $github = (new UtilisateurGithub)->map([
            'id' => $id,
            'nickname' => $username,
            'name' => $nom,
            'email' => $email,
            'avatar' => 'https://avatars.githubusercontent.com/u/'.$id,
        ]);

        $driver = \Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($github);
        Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver);
    }
}
