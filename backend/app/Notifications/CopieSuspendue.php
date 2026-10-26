<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CopieSuspendue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Facture $facture) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $facture = $this->facture->loadMissing('abonnement.appli');
        $arret = now()->addDays((int) config('facturation.jours_avant_arret'));

        return Courriel::nouveau("Votre copie de {$facture->abonnement->appli?->nom} est suspendue")
            ->line("La facture {$facture->numero} n’a pas été réglée : la copie est suspendue. Ses données sont gardées.")
            ->line('Elle repart dès le paiement. Sans paiement avant le '.$arret->locale('fr')->isoFormat('D MMMM YYYY').', elle sera arrêtée définitivement et ses données supprimées.')
            ->action('Payer et réactiver', URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]));
    }
}
