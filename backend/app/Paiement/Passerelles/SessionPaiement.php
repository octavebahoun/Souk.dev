<?php

namespace App\Paiement\Passerelles;

final readonly class SessionPaiement
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $urlPaiement,
    ) {}
}
