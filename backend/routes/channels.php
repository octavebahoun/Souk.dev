<?php

use App\Models\Deploiement;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('deploiement.{id}', function (User $user, string $id) {
    return Deploiement::query()
        ->whereKey($id)
        ->where('user_id', $user->id)
        ->exists();
});

// Fil privé d'une mission : le client et l'auteur seulement.
Broadcast::channel('mission.{id}', function (User $user, string $id) {
    return Mission::query()
        ->whereKey($id)
        ->where(fn ($mission) => $mission->where('client_id', $user->id)->orWhere('auteur_id', $user->id))
        ->exists();
});

// Compte connecté : ses factures (émise, payée après vérification, annulée, remboursée).
Broadcast::channel('utilisateur.{id}', function (User $user, string $id) {
    return (int) $id === $user->id;
});
