<?php

namespace App\Securite;

use App\Jobs\AnalyserSecrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * L'API ne parle pas à Docker : elle confie la recherche de secrets au moteur (file « secrets »)
 * et attend sa réponse quelques secondes. Sans réponse, la publication n'a pas lieu (on ne
 * publie jamais un dépôt qui n'a pas été vérifié).
 */
class VerificationSecrets
{
    /**
     * @return list<array{fichier: string, ligne: int, regle: string, description: string}>
     */
    public function secrets(string $depotUrl): array
    {
        $cle = 'securite:secrets:'.Str::uuid();
        $attente = (int) config('moteur.delai_analyse_secrets', 90);
        AnalyserSecrets::dispatch($depotUrl, $cle, time() + $attente + 5);

        // La requête attend plus que les 30 secondes par défaut de PHP : sinon elle mourrait avant la réponse.
        set_time_limit($attente + 30);
        $limite = microtime(true) + $attente;

        do {
            $resultat = Cache::pull($cle);

            if (is_array($resultat)) {
                if (($resultat['statut'] ?? null) !== 'termine') {
                    throw new ServiceUnavailableHttpException(60, 'L’analyse de sécurité du dépôt n’a pas pu se faire. Réessayez dans quelques minutes.');
                }

                return $resultat['secrets'];
            }

            usleep(500_000);
        } while (microtime(true) < $limite);

        throw new ServiceUnavailableHttpException(60, 'L’analyse de sécurité du dépôt prend plus de temps que prévu. Réessayez dans quelques minutes.');
    }

    /**
     * « config.js, ligne 3 (stripe-access-token) » : de quoi retrouver le secret, jamais le secret.
     *
     * @param  list<array{fichier: string, ligne: int, regle: string, description: string}>  $secrets
     */
    public static function message(array $secrets): string
    {
        $lieux = array_map(fn (array $secret) => "{$secret['fichier']}, ligne {$secret['ligne']} ({$secret['regle']})", array_slice($secrets, 0, 3));
        $autres = count($secrets) - 3;
        $reste = $autres > 0 ? ' et '.$autres.' '.($autres > 1 ? 'autres' : 'autre') : '';

        return count($secrets) > 1
            ? count($secrets).' secrets ont été trouvés dans le dépôt : '.implode("\u{00A0}; ", $lieux).$reste.'. Retirez-les, changez les clés concernées (elles sont publiques), puis réessayez.'
            : 'Un secret a été trouvé dans le dépôt : '.implode("\u{00A0}; ", $lieux).'. Retirez-le, changez la clé concernée (elle est publique), puis réessayez.';
    }
}
