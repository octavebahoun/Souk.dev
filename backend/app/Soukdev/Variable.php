<?php

declare(strict_types=1);

namespace App\Soukdev;

final readonly class Variable
{
    public function __construct(
        public string $nom,
        public string $source,
        public ?string $libelle = null,
        public ?bool $requis = null,
        public ?string $valeur = null,
    ) {}
}
