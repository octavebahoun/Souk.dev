<?php

namespace App\Notifications;

use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerte admin : une facture a été payée deux fois ; rembourser depuis SasPay, puis saisir un avoir. */
class TropPercu extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Facture $facture, public Paiement $paiement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Courriel::nouveau("[Admin] Paiement en double — facture {$this->facture->numero}")
            ->line('Un second paiement réussi a été reçu pour une facture déjà réglée ou annulée.')
            ->line('Montant : '.Courriel::fcfa($this->paiement->montant).', référence SasPay : '.($this->paiement->reference ?? 'inconnue').'.')
            ->line('À faire : rembourser le client depuis le tableau de bord SasPay, puis saisir un avoir dans Souk.dev.')
            ->action('Vue financière', Courriel::front('/admin/finances'));
    }
}
