<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

final class MessagePolicy
{
    public function update(User $compte, Message $message): bool
    {
        return $message->user_id === $compte->id;
    }

    public function delete(User $compte, Message $message): bool
    {
        return $compte->est_admin || $message->user_id === $compte->id;
    }
}
