<?php

declare(strict_types=1);

namespace App\Soukdev;

use InvalidArgumentException;

final class InvalidManifestException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
