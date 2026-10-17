<?php

declare(strict_types=1);

namespace App\Comptes;

use App\Enums\Profil;

final readonly class FrontUrl
{
    public function __construct(private string $base) {}

    public function apresConnexion(Profil $profil): string
    {
        $chemin = $profil === Profil::Client ? '/store' : '/echange';

        return $this->vers($chemin);
    }

    public function erreur(string $code): string
    {
        return $this->vers('/?erreur='.$code);
    }

    private function vers(string $chemin): string
    {
        return rtrim($this->base, '/').$chemin;
    }
}
