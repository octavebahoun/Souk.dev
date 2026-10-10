<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Discussion;
use App\Models\User;

final class DiscussionPolicy
{
    public function update(User $compte, Discussion $discussion): bool
    {
        return $discussion->user_id === $compte->id;
    }

    public function delete(User $compte, Discussion $discussion): bool
    {
        return $compte->est_admin || $discussion->user_id === $compte->id;
    }
}
