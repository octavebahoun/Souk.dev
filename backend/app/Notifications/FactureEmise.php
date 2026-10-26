<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Facture de la période suivante, trois jours avant l'échéance, avec un lien de paiement signé. */
class FactureEmise extends Notification implements ShouldQueue
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
        $lien = URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]);

        return Courriel::nouveau("Votre facture {$facture->numero} — échéance le ".$facture->echeance_le->locale('fr')->isoFormat('D MMMM'))
            ->line("La copie de {$facture->abonnement->appli?->nom} se renouvelle le ".$facture->echeance_le->locale('fr')->isoFormat('dddd D MMMM').'.')
            ->line('Montant : '.Courriel::fcfa($facture->montant_total).' (les frais Mobile Money ou carte s’ajoutent au moment du paiement).')
            ->action('Payer maintenant', $lien)
            ->line('Sans paiement, la copie reste en ligne 7 jours après l’échéance, puis elle est suspendue jusqu’au paiement.');
    }
}
