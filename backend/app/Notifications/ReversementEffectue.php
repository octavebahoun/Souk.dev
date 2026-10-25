<?php

namespace App\Notifications;

use App\Models\Reversement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReversementEffectue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reversement $reversement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->reversement->statut === 'reussi'
            ? Courriel::nouveau('Vous avez reçu '.Courriel::fcfa($this->reversement->montant))
                ->line('Votre reversement de '.Courriel::fcfa($this->reversement->montant)." est arrivé sur votre Mobile Money ({$this->reversement->numero_masque}).")
                ->line('Les frais de transfert sont pris en charge par Souk.dev.')
                ->action('Voir mes revenus', Courriel::front('/revenus'))
            : Courriel::nouveau('Votre reversement n’a pas abouti')
                ->line('Le reversement de '.Courriel::fcfa($this->reversement->montant)." vers {$this->reversement->numero_masque} a été refusé par le réseau. Votre solde vous est rendu.")
                ->line('Vérifiez votre numéro Mobile Money, puis redemandez le reversement.')
                ->action('Vérifier mes coordonnées', Courriel::front('/revenus'));
    }
}
