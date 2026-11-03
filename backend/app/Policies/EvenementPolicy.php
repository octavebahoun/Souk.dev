<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Evenement;
use App\Models\User;

final class EvenementPolicy
{
    public function update(User $compte, Evenement $evenement): bool
    {
        return $evenement->user_id === $compte->id;
    }

    public function delete(User $compte, Evenement $evenement): bool
    {
        return $compte->est_admin || $evenement->user_id === $compte->id;
    }

    /**
     * Un avis seulement par un inscrit, et après la date de l'événement.
     */
    public function noter(User $compte, Evenement $evenement): bool
    {
        return $evenement->date !== null
            && $evenement->date->isPast()
            && $evenement->inscriptions()->where('user_id', $compte->id)->exists();
    }

    public function voirParticipants(User $compte, Evenement $evenement): bool
    {
        return $evenement->user_id === $compte->id
            || $evenement->inscriptions()->where('user_id', $compte->id)->exists();
    }
}
