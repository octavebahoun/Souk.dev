<?php

namespace App\Paiement\Passerelles;

final readonly class DemandeReversement
{
    /**
     * @param  array<string, scalar>  $metadata
     */
    public function __construct(
        public int $montant,
        public string $pays,
        public string $reseau,
        public string $numero,
        public string $email,
        public string $nom,
        public string $description,
        public array $metadata = [],
    ) {}
}
