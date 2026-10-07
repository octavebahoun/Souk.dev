<?php

declare(strict_types=1);

namespace App\Soukdev;

interface SecretGenerator
{
    public function generate(): string;
}
