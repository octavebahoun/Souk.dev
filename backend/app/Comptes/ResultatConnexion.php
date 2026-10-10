<?php

declare(strict_types=1);

namespace App\Comptes;

use App\Models\User;

final readonly class ResultatConnexion
{
    private function __construct(
        public ?User $compte,
        public ?string $erreur,
    ) {}

    public static function ok(User $compte): self
    {
        return new self($compte, null);
    }

    public static function erreur(string $code): self
    {
        return new self(null, $code);
    }

    public function reussi(): bool
    {
        return $this->compte !== null;
    }
}
