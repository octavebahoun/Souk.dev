<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Copie payée qui n'a pas pu démarrer : le client sait que l'équipe s'en occupe et ne paie plus rien. */
class CopieEnIncident extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Abonnement $abonnement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return Courriel::nouveau("Votre copie de {$nom} n’a pas pu démarrer")
            ->line('Votre paiement est bien enregistré, mais la copie n’a pas pu démarrer.')
            ->line('Notre équipe est prévenue et revient vers vous rapidement. Aucune nouvelle facture ne vous sera envoyée d’ici là.')
            ->action('Voir mes copies', Courriel::front('/copies'));
    }
}
