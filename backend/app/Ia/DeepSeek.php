<?php

namespace App\Ia;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API officielle DeepSeek (format OpenAI, https://api-docs.deepseek.com) : POST /chat/completions.
 * Les journaux gardent le statut HTTP et la consommation, jamais la clé, la consigne ni le code.
 */
final class DeepSeek implements FournisseurIa
{
    public function __construct(
        private string $cle,
        private string $modele,
        private string $url = 'https://api.deepseek.com',
        private int $delai = 90,
    ) {}

    public function analyser(string $consigne, string $demande): ReponseIa
    {
        if ($this->cle === '') {
            throw new IaRefus(401, 'La clé DeepSeek est absente (DEEPSEEK_API_KEY).');
        }

        try {
            $reponse = Http::baseUrl($this->url)
                ->withToken($this->cle)
                ->acceptJson()
                ->asJson()
                ->timeout($this->delai)
                ->connectTimeout(10)
                ->post('/chat/completions', [
                    'model' => $this->modele,
                    'messages' => [
                        ['role' => 'system', 'content' => $consigne],
                        ['role' => 'user', 'content' => $demande],
                    ],
                    // Le raisonnement compte dans max_tokens : assez pour une réponse de 300 mots, pas de
                    // quoi écrire un roman si la fiche le demande (une réponse coupée compte dans la limite).
                    'reasoning_effort' => 'high',
                    'max_tokens' => 6000,
                    'stream' => false,
                ]);
        } catch (ConnectionException) {
            Log::warning('deepseek: injoignable');

            throw new IaIndisponible('DeepSeek ne répond pas.');
        }

        if ($reponse->status() === 429 || $reponse->serverError()) {
            Log::warning('deepseek: indisponible', ['statut' => $reponse->status()]);

            throw new IaIndisponible('DeepSeek est surchargé ou limite les requêtes.');
        }

        if (! $reponse->successful()) {
            Log::warning('deepseek: refus', ['statut' => $reponse->status()]);

            throw new IaRefus($reponse->status(), match ($reponse->status()) {
                401 => 'La clé DeepSeek est refusée.',
                402 => 'Le solde du compte DeepSeek est épuisé.',
                default => 'DeepSeek a refusé la demande.',
            });
        }

        $fin = $reponse->json('choices.0.finish_reason');
        $texte = trim((string) $reponse->json('choices.0.message.content', ''));
        Log::info('deepseek: analyse', [
            'modele' => $reponse->json('model'),
            'fin' => $fin,
            'jetons_entree' => $reponse->json('usage.prompt_tokens'),
            'jetons_sortie' => $reponse->json('usage.completion_tokens'),
        ]);

        if ($fin === 'insufficient_system_resource') {
            throw new IaIndisponible('DeepSeek manque de ressources pour le moment.');
        }

        if ($texte === '' || in_array($fin, ['content_filter', 'length', 'aborted'], true)) {
            throw new IaRefus(200, 'Le modèle n’a pas rendu d’analyse complète.');
        }

        return new ReponseIa(
            texte: $texte,
            modele: (string) ($reponse->json('model') ?: $this->modele),
            jetonsEntree: (int) $reponse->json('usage.prompt_tokens', 0),
            jetonsSortie: (int) $reponse->json('usage.completion_tokens', 0),
        );
    }
}
