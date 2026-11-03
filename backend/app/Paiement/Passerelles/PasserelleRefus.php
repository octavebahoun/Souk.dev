<?php

namespace App\Paiement\Passerelles;

use RuntimeException;

/**
 * Refus métier du prestataire (401 clé invalide, 403 compte ou IP refusés, 404, 409 conflit,
 * 410 session expirée, 422 opération impossible) : retenter tel quel ne sert à rien.
 */
final class PasserelleRefus extends RuntimeException
{
    public function __construct(
        public readonly int $statutHttp,
        public readonly ?string $codeErreur,
        string $message,
    ) {
        parent::__construct($message, $statutHttp);
    }
}
