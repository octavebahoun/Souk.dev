<?php

namespace App\Paiement\Passerelles;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Appels HTTP à l'API SasPay (https://docs.saspay.me). La clé ne quitte jamais le serveur et
 * n'est jamais journalisée ; les journaux ne gardent que la route, le statut et le code d'erreur.
 * Réponses dans l'enveloppe { success, data, code } (certains exemples de la doc l'omettent).
 */
final class ClientSasPay
{
    public function __construct(
        private string $cle,
        private string $url,
        private int $delai = 20,
    ) {}

    /**
     * @param  array<string, scalar>  $parametres
     * @return array<string, mixed>
     */
    public function get(string $chemin, array $parametres = []): array
    {
        return $this->envoyer('GET', $chemin, $parametres, null);
    }

    /**
     * @param  array<string, mixed>  $corps
     * @return array<string, mixed>
     */
    public function post(string $chemin, array $corps, ?string $cleIdempotence = null): array
    {
        return $this->envoyer('POST', $chemin, $corps, $cleIdempotence);
    }

    /** Un identifiant SasPay est un UUID : rien d'autre n'entre dans une URL. */
    public static function identifiant(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            throw new PasserelleRefus(400, 'identifiant_invalide', 'Identifiant SasPay invalide.');
        }

        return strtolower($id);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function envoyer(string $methode, string $chemin, array $donnees, ?string $cleIdempotence): array
    {
        if ($this->cle === '') {
            throw new PasserelleRefus(401, 'cle_absente', 'La clé SasPay est absente (SASPAY_SECRET_KEY).');
        }

        $requete = Http::baseUrl($this->url)
            ->withToken($this->cle)
            ->acceptJson()
            ->asJson()
            ->timeout($this->delai)
            ->connectTimeout(10);

        if ($cleIdempotence !== null) {
            $requete = $requete->withHeaders(['Idempotency-Key' => $cleIdempotence]);
        }

        try {
            $reponse = $methode === 'GET' ? $requete->get($chemin, $donnees) : $requete->post($chemin, $donnees);
        } catch (ConnectionException) {
            Log::warning('saspay: injoignable', ['methode' => $methode, 'chemin' => $chemin]);

            throw new PasserelleIndisponible('SasPay ne répond pas. Réessayez dans quelques minutes.');
        }

        if ($reponse->successful()) {
            return $this->contenu($reponse);
        }

        $erreur = $this->erreur($reponse);
        Log::warning('saspay: refus', ['methode' => $methode, 'chemin' => $chemin, 'statut' => $reponse->status(), 'code' => $erreur['code']]);

        if ($reponse->status() === 429 || $reponse->serverError()) {
            throw new PasserelleIndisponible($reponse->status() === 429
                ? 'SasPay limite le nombre de requêtes. Réessayez dans un instant.'
                : 'SasPay rencontre un problème. Réessayez dans quelques minutes.');
        }

        throw new PasserelleRefus($reponse->status(), $erreur['code'], $erreur['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function contenu(Response $reponse): array
    {
        $donnees = $reponse->json();

        if (! is_array($donnees)) {
            return [];
        }

        if (array_key_exists('success', $donnees) && array_key_exists('data', $donnees)) {
            return is_array($donnees['data']) ? $donnees['data'] : [];
        }

        return $donnees;
    }

    /**
     * @return array{code: string|null, message: string}
     */
    private function erreur(Response $reponse): array
    {
        $donnees = $reponse->json();
        $erreur = is_array($donnees) ? ($donnees['error'] ?? $donnees) : [];
        $erreur = is_array($erreur) ? $erreur : [];

        $code = $erreur['code'] ?? null;
        $message = $erreur['message'] ?? $erreur['detail'] ?? null;

        return [
            'code' => is_string($code) ? $code : null,
            'message' => is_string($message) && $message !== '' ? $message : 'SasPay a refusé la demande.',
        ];
    }
}
