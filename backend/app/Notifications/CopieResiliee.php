<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CopieResiliee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Abonnement $abonnement, public bool $jamaisPayee = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return $this->jamaisPayee
            ? Courriel::nouveau("Commande de {$nom} annulée")
                ->line("La première facture de votre copie de {$nom} n’a pas été payée : la commande est annulée. Rien ne vous sera demandé.")
                ->action('Revoir l’appli', Courriel::front('/store/'.$this->abonnement->appli_id))
            : Courriel::nouveau("Votre copie de {$nom} est arrêtée")
                ->line('Après 30 jours de suspension sans paiement, la copie est arrêtée définitivement et ses données supprimées.')
                ->line('Vous pouvez commander une nouvelle copie à tout moment depuis le Store.')
                ->action('Aller au Store', Courriel::front('/store'));
    }
}
