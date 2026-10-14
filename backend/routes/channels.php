<?php

use App\Models\Deploiement;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('deploiement.{id}', function (User $user, string $id) {
    return Deploiement::query()
        ->whereKey($id)
        ->where('user_id', $user->id)
        ->exists();
});
