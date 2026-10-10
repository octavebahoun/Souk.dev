<?php

namespace App\Moteur;

final readonly class CopieLocale
{
    /**
     * @param  array<string, mixed>  $manifeste
     * @param  array<string, string>  $env
     */
    public function __construct(
        public string $id,
        public string $repertoire,
        public array $manifeste,
        public array $env,
        public bool $lancee,
    ) {}
}
