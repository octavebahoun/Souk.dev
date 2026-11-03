<?php

namespace App\Notifications;

use App\Models\CoordonneesReversement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Le lien qui confirme le numéro de reversement : sans lui, aucun argent ne part. */
class ConfirmerCoordonneesReversement extends Notification
{
    use Queueable;

    public function __construct(public CoordonneesReversement $coordonnees, public string $jeton) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Courriel::nouveau('Confirmez votre numéro de reversement')
            ->line("Vous avez indiqué le numéro Mobile Money {$this->coordonnees->numero_masque} pour recevoir vos revenus.")
            ->action('Confirmer ce numéro', url('/reversement/confirmer/'.$this->jeton))
            ->line('Ce lien expire dans 24 heures. Si vous n’êtes pas à l’origine de ce changement, ignorez cet e-mail et changez votre accès GitHub.');
    }
}
