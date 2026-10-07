<?php

declare(strict_types=1);

namespace App\Soukdev;

final readonly class RunningCopy
{
    public function __construct(
        public string $id,
        public string $directory,
        public Manifest $manifest,
    ) {}
}
