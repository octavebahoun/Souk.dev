<?php

namespace App\Notifications;

use App\Models\MessagePrive;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** À l'autre dev d'une conversation privée : un nouveau message. */
class NouveauMessagePrive extends NotificationSouk
{
    public function __construct(public MessagePrive $message) {}

    public function type(): string
    {
        return 'message_prive';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $message = $this->message->loadMissing('auteur');

        return [
            'titre' => 'Message de '.Courriel::nom($message->auteur),
            'texte' => Str::limit($message->texte, 140),
            'lien' => '/messages?conversation='.$message->conversation_id,
        ];
    }

    /** @return array<string, int|string|null> */
    protected function contexte(): array
    {
        return ['conversation_id' => $this->message->conversation_id];
    }

    /** Un e-mail par conversation toutes les 30 minutes au plus. */
    protected function meriteUnEmail(User $compte): bool
    {
        return Cache::add("notification:conversation:{$compte->id}:{$this->message->conversation_id}", true, now()->addMinutes(30));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message->loadMissing('auteur');

        return Courriel::nouveau('Nouveau message de '.Courriel::nom($message->auteur))
            ->line(Str::limit($message->texte, 300))
            ->action('Répondre', Courriel::front('/messages?conversation='.$message->conversation_id))
            ->line('Pour ne pas encombrer votre boîte, une conversation n’envoie pas plus d’un e-mail par demi-heure.');
    }
}
