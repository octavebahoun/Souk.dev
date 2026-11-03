<?php

namespace App\Notifications;

use App\Models\Correctif;
use Illuminate\Notifications\Messages\MailMessage;

/** À l'auteur d'un correctif : l'auteur de la discussion l'a accepté. */
class CorrectifRetenu extends NotificationSouk
{
    public function __construct(public Correctif $correctif) {}

    public function type(): string
    {
        return 'correctif_accepte';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $correctif = $this->correctif->loadMissing('discussion');

        return [
            'titre' => 'Correctif accepté',
            'texte' => "Votre correctif pour «\u{00A0}{$correctif->discussion->titre}\u{00A0}» est accepté : la discussion est résolue.",
            'lien' => '/echange/'.$correctif->discussion_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correctif = $this->correctif->loadMissing('discussion.auteur');

        return Courriel::nouveau('Votre correctif est accepté')
            ->line(Courriel::nom($correctif->discussion->auteur)." a accepté votre correctif pour «\u{00A0}{$correctif->discussion->titre}\u{00A0}». La discussion est résolue.")
            ->line('Il compte dans votre profil public : correctifs acceptés et Souk Score.')
            ->action('Voir la discussion', Courriel::front('/echange/'.$correctif->discussion_id));
    }
}
