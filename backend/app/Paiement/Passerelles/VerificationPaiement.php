<?php

namespace App\Paiement\Passerelles;

/** Résultat de GET /payments/{id}/verify/ : SUCCESS, FAILED, PENDING ou CANCELLED. */
final readonly class VerificationPaiement
{
    public function __construct(
        public string $statut,
        public int $montantDemande,
        public ?int $fraisClient,
        public ?int $montantDebite,
        public ?int $montantNet,
        public ?string $reference,
        public ?string $devise,
    ) {}

    public function reussi(): bool
    {
        return $this->statut === 'SUCCESS';
    }

    public function echoue(): bool
    {
        return in_array($this->statut, ['FAILED', 'CANCELLED'], true);
    }
}
