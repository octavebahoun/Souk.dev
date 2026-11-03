<?php

namespace App\Paiement\Passerelles;

final readonly class TransactionDistante
{
    public function __construct(
        public string $id,
        public ?string $reference,
        public int $montantDemande,
        public ?int $montantNet,
        public string $creeLe,
        public ?string $description = null,
    ) {}
}
