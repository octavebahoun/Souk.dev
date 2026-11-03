<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

/**
 * Une mission est privée : seuls le client et l'auteur la voient et y écrivent.
 * Seul le client la termine en notant l'auteur.
 */
final class MissionPolicy
{
    public function view(User $compte, Mission $mission): bool
    {
        return $mission->concerne($compte);
    }

    public function ecrire(User $compte, Mission $mission): bool
    {
        return $mission->concerne($compte);
    }

    public function noter(User $compte, Mission $mission): bool
    {
        return $mission->client_id === $compte->id;
    }
}
