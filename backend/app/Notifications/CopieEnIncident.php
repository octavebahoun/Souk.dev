<?php

namespace App\Notifications;

use App\Models\Abonnement;
use Illuminate\Notifications\Messages\MailMessage;

/** Copie payée qui n'a pas pu démarrer : le client sait que l'équipe s'en occupe et ne paie plus rien. */
class CopieEnIncident extends NotificationSouk
{
    public function __construct(public Abonnement $abonnement) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return [
            'titre' => "Copie de {$nom} non démarrée",
            'texte' => 'Votre paiement est enregistré, mais la copie n’a pas pu démarrer. Notre équipe est prévenue.',
            'lien' => '/copies',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->abonnement->loadMissing('appli')->appli?->nom;

        return Courriel::nouveau("Votre copie de {$nom} n’a pas pu démarrer")
            ->line('Votre paiement est bien enregistré, mais la copie n’a pas pu démarrer.')
            ->line('Notre équipe est prévenue et revient vers vous rapidement. Aucune nouvelle facture ne vous sera envoyée d’ici là.')
            ->action('Voir mes copies', Courriel::front('/copies'));
    }
}
