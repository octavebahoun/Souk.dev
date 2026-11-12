<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;

/** Au dev qui a invité : son invitation est acceptée, la conversation est ouverte. */
class InvitationAcceptee extends NotificationSouk
{
    public function __construct(public Conversation $conversation) {}

    public function type(): string
    {
        return 'message_prive';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $conversation = $this->conversation->loadMissing('destinataire');

        return [
            'titre' => 'Invitation acceptée',
            'texte' => Courriel::nom($conversation->destinataire).' a accepté votre invitation : vous pouvez lui écrire.',
            'lien' => '/messages?conversation='.$conversation->id,
        ];
    }

    /** @return array<string, int|string|null> */
    protected function contexte(): array
    {
        return ['conversation_id' => $this->conversation->id];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->conversation->loadMissing('destinataire');

        return Courriel::nouveau(Courriel::nom($conversation->destinataire).' a accepté votre invitation')
            ->line('La conversation est ouverte : vous pouvez lui écrire.')
            ->action('Ouvrir la conversation', Courriel::front('/messages?conversation='.$conversation->id));
    }
}
