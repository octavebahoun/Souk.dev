<?php

namespace App\Paiement\Passerelles;

/** Statut d'une session : PENDING, PAID, CANCELLED, EXPIRED ; la transaction une fois le client passé. */
final readonly class StatutSession
{
    public function __construct(
        public string $statut,
        public ?string $transactionId,
        public ?string $statutTransaction,
    ) {}
}
