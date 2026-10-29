<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Compte suspendu par l'équipe (modération) : l'e-mail part toujours, comme toute suspension,
 * pour que le compte sache pourquoi et puisse contester.
 */
class CompteSuspendu extends NotificationSouk
{
    public function __construct(public string $motif) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        return [
            'titre' => 'Votre compte est suspendu',
            'texte' => 'Motif : '.$this->motif.'. Vos données sont gardées. Pour contester, répondez à cet e-mail.',
            'lien' => null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Courriel::nouveau('Votre compte Souk.dev est suspendu')
            ->line('Votre compte est suspendu. Motif : '.$this->motif)
            ->line('Vos données sont gardées. Pour contester cette décision, répondez à cet e-mail : l’équipe le relira.')
            ->line('Politique d’abus : '.Courriel::front('/legal/abus'));
    }
}
