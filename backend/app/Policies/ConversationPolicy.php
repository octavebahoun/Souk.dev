<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Une conversation est privée : pour tout autre compte que ses deux devs, elle n'existe pas
 * (404). Seul l'invité répond à l'invitation ; on n'écrit qu'après son accord.
 */
final class ConversationPolicy
{
    public function view(User $compte, Conversation $conversation): Response
    {
        return $conversation->concerne($compte) ? Response::allow() : Response::denyAsNotFound();
    }

    public function repondre(User $compte, Conversation $conversation): Response
    {
        if (! $conversation->concerne($compte)) {
            return Response::denyAsNotFound();
        }

        return $conversation->destinataire_id === $compte->id
            ? Response::allow()
            : Response::deny('Seule la personne invitée répond à l’invitation.');
    }

    public function ecrire(User $compte, Conversation $conversation): Response
    {
        if (! $conversation->concerne($compte)) {
            return Response::denyAsNotFound();
        }

        return $conversation->estAcceptee()
            ? Response::allow()
            : Response::deny('L’invitation doit d’abord être acceptée.');
    }
}
