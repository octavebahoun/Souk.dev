<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appli;
use App\Models\User;

/**
 * La fiche d'une appli est publique ; seul son auteur la modifie, change ses captures ou la retire.
 */
final class AppliPolicy
{
    public function update(User $compte, Appli $appli): bool
    {
        return $appli->user_id === $compte->id;
    }

    public function delete(User $compte, Appli $appli): bool
    {
        return $appli->user_id === $compte->id;
    }
}
