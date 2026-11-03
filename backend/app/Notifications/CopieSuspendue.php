<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class CopieSuspendue extends NotificationSouk
{
    public function __construct(public Facture $facture) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $facture = $this->facture->loadMissing('abonnement.appli');
        $arret = now()->addDays((int) config('facturation.jours_avant_arret'));

        return [
            'titre' => "Copie de {$facture->abonnement->appli?->nom} suspendue",
            'texte' => "La facture {$facture->numero} n’est pas réglée. Les données sont gardées jusqu’au ".$arret->locale('fr')->isoFormat('D MMMM').', la copie repart dès le paiement.',
            'lien' => '/paiements/'.$facture->id,
        ];
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
