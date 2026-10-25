<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class FactureEnRetard extends Notification implements ShouldQueue
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
        $suspension = $facture->echeance_le->copy()->addDays((int) config('facturation.jours_grace'));

        return Courriel::nouveau("Facture {$facture->numero} en attente de paiement")
            ->line("La facture de votre copie de {$facture->abonnement->appli?->nom} n’est pas encore réglée.")
            ->line('Votre copie reste en ligne jusqu’au '.$suspension->locale('fr')->isoFormat('D MMMM').' ; elle sera suspendue ensuite, jusqu’au paiement.')
            ->action('Payer '.Courriel::fcfa($facture->montant_total), URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]));
    }
}
