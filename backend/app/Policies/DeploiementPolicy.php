<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Un déploiement (copie client, copie de test, version corrigée) est privé :
 * seul le compte qui l'a lancé le voit et l'arrête.
 */
final class DeploiementPolicy
{
    public function view(User $compte, Deploiement $deploiement): bool
    {
        return $deploiement->user_id === $compte->id;
    }

    public function delete(User $compte, Deploiement $deploiement): bool
    {
        return $deploiement->user_id === $compte->id;
    }

    /** Seul le client propriétaire met sa copie à jour ; pour un autre compte, la copie n'existe pas. */
    public function mettreAJour(User $compte, Deploiement $deploiement): Response
    {
        return $deploiement->user_id === $compte->id ? Response::allow() : Response::denyAsNotFound();
    }
}
