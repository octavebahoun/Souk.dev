<?php

declare(strict_types=1);

namespace App\Reputation;

final readonly class SoukScore
{
    public function __construct(
        public int $total,
        public int $entraide,
        public int $projets,
        public int $clients,
        public int $securite,
        public int $activite,
    ) {}
}
