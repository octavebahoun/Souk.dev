<?php

declare(strict_types=1);

namespace App\Reputation;

final readonly class Reputation
{
    /**
     * @param  string  $niveau  debutant, contributeur, confirme ou expert
     * @param  list<string>  $badges
     */
    public function __construct(
        public int $xp,
        public string $niveau,
        public array $badges,
    ) {}
}
