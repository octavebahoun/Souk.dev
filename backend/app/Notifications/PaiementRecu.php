<?php

namespace App\Notifications;

use App\Models\Facture;
use Illuminate\Notifications\Messages\MailMessage;

/** Reçu : la facture est payée (vérifié auprès de SasPay). */
class PaiementRecu extends NotificationSouk
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

        return [
            'titre' => 'Paiement reçu',
            'texte' => Courriel::fcfa($facture->montant_total)." pour la facture {$facture->numero} ({$facture->abonnement->appli?->nom}).",
            'lien' => '/paiements/'.$facture->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $facture = $this->facture->loadMissing('abonnement.appli');
        $premiere = $facture->abonnement->periode_debut?->equalTo($facture->periode_debut) ?? false;

        return Courriel::nouveau("Paiement reçu — facture {$facture->numero}")
            ->line('Nous avons bien reçu le paiement de '.Courriel::fcfa($facture->montant_total)." pour la facture {$facture->numero}.")
            ->line($premiere
                ? "Votre copie de {$facture->abonnement->appli?->nom} démarre : vous la verrez en ligne dans « Mes copies » d'ici quelques minutes."
                : "Votre copie de {$facture->abonnement->appli?->nom} reste en ligne jusqu'au ".$facture->periode_fin->locale('fr')->isoFormat('D MMMM YYYY').'.')
            ->action('Voir le reçu', Courriel::front('/paiements/'.$facture->id));
    }
}
