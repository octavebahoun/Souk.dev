<?php

namespace App\Notifications;

use App\Models\Correctif;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** À l'auteur d'une discussion : un dev y propose un correctif. */
class CorrectifRecu extends NotificationSouk
{
    public function __construct(public Correctif $correctif) {}

    public function type(): string
    {
        return 'correctif_propose';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $correctif = $this->correctif->loadMissing(['auteur', 'discussion']);

        return [
            'titre' => 'Correctif proposé',
            'texte' => Courriel::nom($correctif->auteur)." propose un correctif à «\u{00A0}{$correctif->discussion->titre}\u{00A0}».",
            'lien' => '/echange/'.$correctif->discussion_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correctif = $this->correctif->loadMissing(['auteur', 'discussion']);

        return Courriel::nouveau('Un correctif pour «'."\u{00A0}".Str::limit($correctif->discussion->titre, 60)."\u{00A0}".'»')
            ->line(Courriel::nom($correctif->auteur)." propose un correctif à votre discussion «\u{00A0}{$correctif->discussion->titre}\u{00A0}».")
            ->line(Str::limit($correctif->description, 300))
            ->action('Voir le correctif', Courriel::front('/echange/'.$correctif->discussion_id))
            ->line('Si le problème est réglé, acceptez-le : la discussion sera marquée résolue.');
    }
}
