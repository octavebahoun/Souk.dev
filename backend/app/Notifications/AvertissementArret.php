<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class AvertissementArret extends NotificationSouk
{
    public function __construct(public Abonnement $abonnement) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $abonnement = $this->abonnement->loadMissing('appli');
        $facture = $abonnement->factures()->where('statut', 'a_payer')->oldest('echeance_le')->first();
        $arret = $abonnement->suspendu_le?->copy()->addDays((int) config('facturation.jours_avant_arret'));

        return [
            'titre' => 'Dernier rappel avant suppression',
            'texte' => "Sans paiement, votre copie de {$abonnement->appli?->nom} et ses données seront supprimées le ".$arret?->locale('fr')->isoFormat('D MMMM').'.',
            'lien' => $facture !== null ? '/paiements/'.$facture->id : '/copies',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $abonnement = $this->abonnement->loadMissing('appli');
        $facture = $abonnement->factures()->where('statut', 'a_payer')->oldest('echeance_le')->first();
        $arret = $abonnement->suspendu_le?->copy()->addDays((int) config('facturation.jours_avant_arret'));
        $message = Courriel::nouveau("Dernier rappel : votre copie de {$abonnement->appli?->nom} sera supprimée")
            ->line('Votre copie est suspendue depuis le '.$abonnement->suspendu_le?->locale('fr')->isoFormat('D MMMM').'.')
            ->line('Sans paiement avant le '.$arret?->locale('fr')->isoFormat('D MMMM YYYY').', elle sera arrêtée définitivement et ses données supprimées. Cette action est irréversible.');

        return $facture === null
            ? $message
            : $message->action('Payer et garder ma copie', URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]));
    }
}
