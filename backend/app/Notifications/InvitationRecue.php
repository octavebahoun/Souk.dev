<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Au dev invité : un autre dev veut échanger avec lui en privé. */
class InvitationRecue extends NotificationSouk
{
    public function __construct(public Conversation $conversation) {}

    public function type(): string
    {
        return 'message_prive';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $conversation = $this->conversation->loadMissing('demandeur');

        return [
            'titre' => Courriel::nom($conversation->demandeur).' vous invite à échanger',
            'texte' => Str::limit($conversation->invitation, 140),
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
        $conversation = $this->conversation->loadMissing('demandeur');

        return Courriel::nouveau(Courriel::nom($conversation->demandeur).' vous invite à échanger sur Souk.dev')
            ->line(Str::limit($conversation->invitation, 500))
            ->action('Voir l’invitation', Courriel::front('/messages?conversation='.$conversation->id))
            ->line('Personne ne peut vous écrire en privé sans votre accord : acceptez pour ouvrir la conversation, ou déclinez.');
    }
}
