<?php

declare(strict_types=1);

namespace App\Soukdev;

use RuntimeException;

final class LaunchFailedException extends RuntimeException
{
    public function __construct(string $message = 'Impossible de lancer la copie.')
    {
        parent::__construct($message);
    }
}
