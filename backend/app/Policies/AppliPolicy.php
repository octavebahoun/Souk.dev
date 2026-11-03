<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appli;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * La fiche d'une appli est publique ; seul son auteur la modifie, change ses captures ou la retire.
 * Seul un client qui en a eu une copie payée la note.
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

    public function noter(User $compte, Appli $appli): Response
    {
        return $appli->peutEtreNoteePar($compte)
            ? Response::allow()
            : Response::deny('Seul un client qui a eu une copie payée de cette appli peut la noter.');
    }
}
