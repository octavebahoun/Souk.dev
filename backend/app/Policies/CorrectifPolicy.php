<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Correctif;
use App\Models\User;

final class CorrectifPolicy
{
    /**
     * Seul l'auteur de la discussion accepte un correctif.
     */
    public function accepter(User $compte, Correctif $correctif): bool
    {
        return $correctif->discussion()->where('user_id', $compte->id)->exists();
    }

    /**
     * Seul l'auteur du correctif déploie sa version corrigée.
     */
    public function deployer(User $compte, Correctif $correctif): bool
    {
        return $correctif->user_id === $compte->id;
    }
}
