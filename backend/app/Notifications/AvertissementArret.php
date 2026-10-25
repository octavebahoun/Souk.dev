<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AvertissementArret extends Notification implements ShouldQueue
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
        $abonnement = $this->abonnement->loadMissing('appli');
        $facture = $abonnement->factures()->where('statut', 'a_payer')->oldest('echeance_le')->first();
        $arret = $abonnement->suspendu_le?->copy()->addDays((int) config('facturation.jours_avant_arret'));
        $message = Courriel::nouveau("Dernier rappel : votre copie de {$abonnement->appli?->nom} sera supprimée")
            ->line('Votre copie est suspendue depuis le '.$abonnement->suspendu_le?->locale('fr')->isoFormat('D MMMM').'.')
            ->line('Sans paiement avant le '.$arret?->locale('fr')->isoFormat('D MMMM YYYY').', elle sera arrêtée définitivement et ses données supprimées. Cette action est irréversible.');

        return $facture === null
            ? $message
            : $message->action('Payer et garder ma copie', URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]));
    }
}
