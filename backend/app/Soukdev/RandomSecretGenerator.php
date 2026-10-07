<?php

declare(strict_types=1);

namespace App\Soukdev;

final class RandomSecretGenerator implements SecretGenerator
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function generate(): string
    {
        $length = strlen(self::ALPHABET);
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= self::ALPHABET[random_int(0, $length - 1)];
        }

        return $secret;
    }
}
