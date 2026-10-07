<?php

declare(strict_types=1);

namespace App\Soukdev;

interface GitCloner
{
    /**
     * Clone superficiel de $remote dans $destination, qui ne doit pas encore exister.
     */
    public function checkout(string $remote, ?string $branch, string $destination): void;
}
