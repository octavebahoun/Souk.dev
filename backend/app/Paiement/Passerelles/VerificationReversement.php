<?php

namespace App\Paiement\Passerelles;

/** Résultat de GET /payouts/{id}/verify/ : SUCCESS, FAILED ou PENDING ; frais payés par Souk.dev. */
final readonly class VerificationReversement
{
    public function __construct(
        public string $statut,
        public ?int $frais,
        public ?string $erreur = null,
    ) {}
}
