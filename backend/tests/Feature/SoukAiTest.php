<?php

namespace Tests\Feature;

use App\Events\AnalyseIaTerminee;
use App\Ia\DeepSeek;
use App\Ia\DemandeAnalyse;
use App\Ia\FournisseurIa;
use App\Ia\IaIndisponible;
use App\Ia\IaRefus;
use App\Ia\IaSimulee;
use App\Ia\ReponseIa;
use App\Jobs\AnalyserBug;
use App\Models\AnalyseIa;
use App\Models\Discussion;
use App\Models\User;
use App\Notifications\AlerteAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Souk AI : l'auteur d'un bug demande une analyse (consentement explicite), trois par 24 heures,
 * une à la fois ; le résultat arrive dans la discussion et par Reverb ; une panne ou un refus du
 * fournisseur donne « analyse indisponible » sans consommer la limite.
 */
class SoukAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_auteur_d_un_bug_recoit_une_analyse_dans_la_discussion(): void
    {
        Event::fake([AnalyseIaTerminee::class]);
        $discussion = $this->bug();

        $this->actingAs($discussion->auteur)
            ->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => true])
            ->assertStatus(202)
            ->assertJsonPath('statut', 'en_cours')
            ->assertJsonPath('texte', null);

        // File synchrone dans les tests : le fournisseur simulé a déjà répondu.
        $this->getJson("/api/discussions/{$discussion->id}")
            ->assertOk()
            ->assertJsonPath('analyse_ia.statut', 'prete')
            ->assertJsonPath('analyse_ia.texte', fn (string $texte) => str_contains($texte, 'Cause probable'));
        Event::assertDispatched(AnalyseIaTerminee::class, fn (AnalyseIaTerminee $evenement) => $evenement->broadcastAs() === 'analyse_ia.terminee'
            && $evenement->broadcastOn()->name === 'discussion.'.$discussion->id
            && $evenement->broadcastWith()['statut'] === 'prete');
    }

    public function test_seul_l_auteur_d_un_bug_qui_consent_peut_demander(): void
    {
        Queue::fake();
        $discussion = $this->bug();
        $question = Discussion::factory()->create(['etiquettes' => ['question']]);

        $this->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => true])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => true])->assertForbidden();
        $this->actingAs($discussion->auteur)->postJson("/api/discussions/{$discussion->id}/analyse-ia", [])->assertUnprocessable()->assertJsonValidationErrors('consentement');
        $this->actingAs($discussion->auteur)->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => false])->assertUnprocessable();
        $this->actingAs($question->auteur)->postJson("/api/discussions/{$question->id}/analyse-ia", ['consentement' => true])->assertUnprocessable()->assertJsonValidationErrors('etiquettes');

        Queue::assertNothingPushed();
        $this->assertSame(0, AnalyseIa::query()->count());
    }

    public function test_la_quatrieme_demande_en_24_heures_recoit_429_et_les_echecs_ne_comptent_pas(): void
    {
        Queue::fake();
        $auteur = User::factory()->create();
        $bugs = collect(range(1, 5))->map(fn () => $this->bug($auteur));
        AnalyseIa::query()->create(['discussion_id' => $bugs[4]->id, 'user_id' => $auteur->id, 'statut' => 'echec', 'consentement_le' => now()]);

        foreach ($bugs->take(3) as $bug) {
            $this->actingAs($auteur)->postJson("/api/discussions/{$bug->id}/analyse-ia", ['consentement' => true])->assertStatus(202);
        }
        $refus = $this->actingAs($auteur)->postJson("/api/discussions/{$bugs[3]->id}/analyse-ia", ['consentement' => true]);

        $refus->assertStatus(429);
        $this->assertGreaterThan(86000, (int) $refus->headers->get('Retry-After'));
        $this->assertStringContainsString('3 analyses par 24 heures', $refus->json('message'));
        Queue::assertPushed(AnalyserBug::class, 3);

        $this->travel(25)->hours();
        $this->actingAs($auteur)->postJson("/api/discussions/{$bugs[3]->id}/analyse-ia", ['consentement' => true])->assertStatus(202);
    }

    public function test_une_seule_analyse_a_la_fois_par_bug(): void
    {
        Queue::fake();
        $discussion = $this->bug();

        $this->actingAs($discussion->auteur)->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => true])->assertStatus(202);
        $this->actingAs($discussion->auteur)->postJson("/api/discussions/{$discussion->id}/analyse-ia", ['consentement' => true])->assertConflict();
    }

    public function test_un_refus_du_fournisseur_donne_analyse_indisponible_et_previent_l_equipe_si_c_est_la_cle(): void
    {
        Event::fake([AnalyseIaTerminee::class]);
        Notification::fake();
        $discussion = $this->bug();
        $analyse = AnalyseIa::query()->create(['discussion_id' => $discussion->id, 'user_id' => $discussion->user_id, 'statut' => 'en_cours', 'consentement_le' => now()]);
        $this->app->instance(FournisseurIa::class, new class implements FournisseurIa
        {
            public function analyser(string $consigne, string $demande): ReponseIa
            {
                throw new IaRefus(402, 'Le solde du compte DeepSeek est épuisé.');
            }
        });

        (new AnalyserBug($analyse->id))->handle(app(FournisseurIa::class));

        $this->assertSame('echec', $analyse->fresh()->statut);
        $this->getJson("/api/discussions/{$discussion->id}")->assertJsonPath('analyse_ia.statut', 'echec')->assertJsonPath('analyse_ia.texte', null);
        Notification::assertSentOnDemand(AlerteAdmin::class);
        Event::assertDispatched(AnalyseIaTerminee::class);
    }

    public function test_une_panne_qui_dure_finit_en_analyse_indisponible(): void
    {
        Event::fake([AnalyseIaTerminee::class]);
        $discussion = $this->bug();
        $discussion->forceFill(['texte' => 'Le fournisseur tombe en panne [ia-indisponible].'])->save();
        $analyse = AnalyseIa::query()->create(['discussion_id' => $discussion->id, 'user_id' => $discussion->user_id, 'statut' => 'en_cours', 'consentement_le' => now()]);

        try {
            (new AnalyserBug($analyse->id))->handle(new IaSimulee);
            $this->fail('La panne simulée devait remonter pour un nouvel essai.');
        } catch (IaIndisponible) {
            // Le travail sera réessayé ; au dernier échec, failed() conclut.
        }
        (new AnalyserBug($analyse->id))->failed(new IaIndisponible('panne'));

        $this->assertSame('echec', $analyse->fresh()->statut);
        $this->assertSame('indisponible', $analyse->fresh()->erreur);
    }

    public function test_les_cles_du_code_sont_masquees_avant_l_envoi(): void
    {
        $discussion = $this->bug(code: "const cle = 'sk_live_abcdef1234567890';\n\$password = \"hunter2secret\";");

        $demande = DemandeAnalyse::pour($discussion);

        $this->assertStringNotContainsString('sk_live_abcdef1234567890', $demande);
        $this->assertStringNotContainsString('hunter2secret', $demande);
        $this->assertStringContainsString('[secret masqué]', $demande);
        $this->assertStringStartsWith('<bug>', $demande);
    }

    public function test_deepseek_recoit_le_bon_modele_et_rend_le_texte(): void
    {
        Http::fake(['api.deepseek.com/chat/completions' => Http::response([
            'model' => 'deepseek-flash',
            'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => '**Cause probable** : la commande est introuvable.']]],
            'usage' => ['prompt_tokens' => 420, 'completion_tokens' => 180],
        ])]);

        $reponse = (new DeepSeek('cle-de-test', 'deepseek-flash'))->analyser('consigne', '<bug>…</bug>');

        $this->assertSame('**Cause probable** : la commande est introuvable.', $reponse->texte);
        $this->assertSame(420, $reponse->jetonsEntree);
        Http::assertSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer cle-de-test')
            && $requete['model'] === 'deepseek-flash'
            && $requete['messages'][0]['role'] === 'system');
    }

    public function test_deepseek_distingue_panne_et_refus(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::sequence()->push([], 503)->push(['error' => ['message' => 'Insufficient Balance']], 402)->push(['choices' => [['finish_reason' => 'length', 'message' => ['content' => 'Cause…']]]])]);
        $deepseek = new DeepSeek('cle-de-test', 'deepseek-flash');

        $this->assertLance(IaIndisponible::class, fn () => $deepseek->analyser('c', 'd'));
        $this->assertLance(IaRefus::class, fn () => $deepseek->analyser('c', 'd'));
        $this->assertLance(IaRefus::class, fn () => $deepseek->analyser('c', 'd'));
    }

    private function assertLance(string $classe, callable $appel): void
    {
        try {
            $appel();
            $this->fail("{$classe} attendue.");
        } catch (\Throwable $erreur) {
            $this->assertInstanceOf($classe, $erreur);
        }
    }

    private function bug(?User $auteur = null, ?string $code = null): Discussion
    {
        return Discussion::factory()->create([
            'user_id' => ($auteur ?? User::factory()->create())->id,
            'etiquettes' => ['bug'],
            'titre' => 'Le callback MoMo renvoie 500',
            'texte' => 'Depuis la mise en production, le callback répond 500.',
            'bug' => [
                'erreur_obtenue' => 'Call to a member function update() on null',
                'comportement_attendu' => 'La commande passe en payée',
                'techno' => 'laravel',
                'code' => $code ?? '$commande = Commande::firstWhere(\'reference\', $request->externalId);',
                'difficulte' => 'moyen',
            ],
        ]);
    }
}
