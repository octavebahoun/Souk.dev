<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class FactureEnRetard extends NotificationSouk
{
    public function __construct(public Facture $facture) {}

    public function type(): string
    {
        return 'echeance';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $facture = $this->facture->loadMissing('abonnement.appli');
        $suspension = $facture->echeance_le->copy()->addDays((int) config('facturation.jours_grace'));

        return [
            'titre' => "Facture {$facture->numero} en retard",
            'texte' => "Sans paiement, votre copie de {$facture->abonnement->appli?->nom} sera suspendue le ".$suspension->locale('fr')->isoFormat('D MMMM').'.',
            'lien' => '/paiements/'.$facture->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $facture = $this->facture->loadMissing('abonnement.appli');
        $suspension = $facture->echeance_le->copy()->addDays((int) config('facturation.jours_grace'));

        return Courriel::nouveau("Facture {$facture->numero} en attente de paiement")
            ->line("La facture de votre copie de {$facture->abonnement->appli?->nom} n’est pas encore réglée.")
            ->line('Votre copie reste en ligne jusqu’au '.$suspension->locale('fr')->isoFormat('D MMMM').' ; elle sera suspendue ensuite, jusqu’au paiement.')
            ->action('Payer '.Courriel::fcfa($facture->montant_total), URL::temporarySignedRoute('paiement.facture', now()->addDays(30), ['id' => $facture->id]));
    }
}
