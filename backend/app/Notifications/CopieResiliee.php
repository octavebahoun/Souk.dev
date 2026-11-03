<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Notifications\Messages\MailMessage;

class CopieResiliee extends NotificationSouk
{
    public function __construct(public Abonnement $abonnement, public bool $jamaisPayee = false) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return $this->jamaisPayee
            ? [
                'titre' => "Commande de {$nom} annulée",
                'texte' => 'La première facture n’a pas été payée : rien ne vous sera demandé.',
                'lien' => '/store/'.$this->abonnement->appli_id,
            ]
            : [
                'titre' => "Copie de {$nom} arrêtée",
                'texte' => 'Après 30 jours de suspension sans paiement, la copie et ses données sont supprimées.',
                'lien' => '/store',
            ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return $this->jamaisPayee
            ? Courriel::nouveau("Commande de {$nom} annulée")
                ->line("La première facture de votre copie de {$nom} n’a pas été payée : la commande est annulée. Rien ne vous sera demandé.")
                ->action('Revoir l’appli', Courriel::front('/store/'.$this->abonnement->appli_id))
            : Courriel::nouveau("Votre copie de {$nom} est arrêtée")
                ->line('Après 30 jours de suspension sans paiement, la copie est arrêtée définitivement et ses données supprimées.')
                ->line('Vous pouvez commander une nouvelle copie à tout moment depuis le Store.')
                ->action('Aller au Store', Courriel::front('/store'));
    }
}
