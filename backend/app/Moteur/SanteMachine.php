<?php

namespace App\Moteur;

/** Résultat de la sonde d'une machine : joignable, mémoire totale, version du démon. */
final readonly class SanteMachine
{
    public function __construct(
        public bool $joignable,
        public ?int $memoireTotaleMo = null,
        public ?string $versionDocker = null,
        public ?string $erreur = null,
    ) {}

    public static function injoignable(string $erreur): self
    {
        return new self(false, null, null, $erreur);
    }
}
