<?php

namespace App\Paiement\Passerelles;

final readonly class DemandeSession
{
    /**
     * @param  array<string, scalar>  $metadata
     */
    public function __construct(
        public int $montant,
        public string $description,
        public string $email,
        public string $nom,
        public string $urlRetour,
        public array $metadata = [],
        public ?string $pays = null,
    ) {}
}
