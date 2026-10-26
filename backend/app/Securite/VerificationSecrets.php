<?php

namespace App\Securite;

use App\Jobs\AnalyserSecrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * L'API ne parle pas à Docker : elle confie la recherche de secrets au moteur (file « analyse »)
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
        AnalyserSecrets::dispatch($depotUrl, $cle);

        $limite = microtime(true) + (int) config('moteur.delai_analyse_secrets', 90);

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
        $reste = count($secrets) > 3 ? ' et '.(count($secrets) - 3).' autre(s)' : '';

        return 'Un secret a été trouvé dans le dépôt : '.implode(' ; ', $lieux).$reste.'. Retirez-le, changez la clé concernée (elle est publique), puis réessayez.';
    }
}
