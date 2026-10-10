<?php

use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('deploiement.{id}', function (User $user, string $id) {
    $deploiement = Deploiement::query()->find($id);

    return $deploiement !== null && $deploiement->user_id === $user->id;
});
