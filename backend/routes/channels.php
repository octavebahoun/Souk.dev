<?php

use App\Models\Deploiement;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Un compte suspendu ne reçoit plus rien en temps réel : sa session reste valide et
// POST /broadcasting/auth ne passe pas par le middleware CompteActif (routes api/
// seules), donc chaque canal refuse ici, comme l'API répond 403.
Broadcast::channel('deploiement.{id}', function (User $user, string $id) {
    return ! $user->estSuspendu() && Deploiement::query()
        ->whereKey($id)
        ->where('user_id', $user->id)
        ->exists();
});

// Fil privé d'une mission : le client et l'auteur seulement.
Broadcast::channel('mission.{id}', function (User $user, string $id) {
    return ! $user->estSuspendu() && Mission::query()
        ->whereKey($id)
        ->where(fn ($mission) => $mission->where('client_id', $user->id)->orWhere('auteur_id', $user->id))
        ->exists();
});

// Compte connecté : ses factures (émise, payée après vérification, annulée, remboursée).
Broadcast::channel('utilisateur.{id}', function (User $user, string $id) {
    return ! $user->estSuspendu() && (int) $id === $user->id;
});
