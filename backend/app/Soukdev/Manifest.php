<?php

declare(strict_types=1);

namespace App\Soukdev;

final readonly class Manifest
{
    /**
     * @param  list<Variable>  $variables
     */
    public function __construct(
        public int $version,
        public string $serviceWeb,
        public int $port,
        public string $backend,
        public ?string $migrations,
        public array $variables,
    ) {}
}
