<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\LienMagiqueMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class LienMagiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_ouvre_sa_session_avec_un_lien_a_usage_unique(): void
    {
        Mail::fake();

        $this->postJson('/auth/lien-magique', ['email' => 'Client@Example.com'])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Si cette adresse est valide, un email a été envoyé.');

        $url = null;

        Mail::assertSent(LienMagiqueMail::class, function (LienMagiqueMail $mail) use (&$url): bool {
            $url = $mail->url;

            return $mail->hasTo('client@example.com');
        });

        $this->assertNotNull($url);
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->get('/auth/lien-magique/'.$token)
            ->assertRedirect('http://localhost:5173/store');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'profil' => 'client',
            'github_lie' => false,
        ]);

        $this->getJson('/auth/lien-magique/'.$token)
            ->assertStatus(410)
            ->assertJsonPath('message', 'Lien expiré ou déjà utilisé.');
    }

    public function test_la_reponse_est_la_meme_si_le_compte_existe_deja(): void
    {
        Mail::fake();
        User::factory()->client()->create(['email' => 'client@example.com']);

        $this->postJson('/auth/lien-magique', ['email' => 'client@example.com'])->assertStatus(202);
        $this->postJson('/auth/lien-magique', ['email' => 'client@example.com'])->assertStatus(202);

        $this->assertDatabaseCount('users', 1);
        Mail::assertSent(LienMagiqueMail::class, 2);
    }

    public function test_une_adresse_invalide_est_refusee(): void
    {
        Mail::fake();

        $this->postJson('/auth/lien-magique', ['email' => 'pas-une-adresse'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);

        Mail::assertNothingSent();
    }

    public function test_un_lien_expire_repond_410(): void
    {
        Mail::fake();

        $this->postJson('/auth/lien-magique', ['email' => 'client@example.com'])->assertStatus(202);

        $url = '';
        Mail::assertSent(LienMagiqueMail::class, function (LienMagiqueMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        $token = basename((string) parse_url($url, PHP_URL_PATH));

        $this->travel(16)->minutes();

        $this->getJson('/auth/lien-magique/'.$token)->assertStatus(410);
        $this->assertGuest();
    }
}
