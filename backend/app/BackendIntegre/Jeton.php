<?php

namespace App\BackendIntegre;

/** JWT HS256, le format que vérifie PostgREST avec le secret de la copie. */
final class Jeton
{
    /**
     * @param  array<string, mixed>  $charge
     */
    public static function signer(array $charge, string $secret): string
    {
        $entete = self::base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $corps = self::base64Url((string) json_encode($charge, JSON_THROW_ON_ERROR));

        return $entete.'.'.$corps.'.'.self::base64Url(hash_hmac('sha256', $entete.'.'.$corps, $secret, true));
    }

    private static function base64Url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }
}
