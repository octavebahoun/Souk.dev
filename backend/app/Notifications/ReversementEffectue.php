<?php

namespace App\Notifications;

use App\Models\Reversement;
use Illuminate\Notifications\Messages\MailMessage;

class ReversementEffectue extends NotificationSouk
{
    public function __construct(public Reversement $reversement) {}

    public function type(): string
    {
        return 'reversement';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $montant = Courriel::fcfa($this->reversement->montant);

        return $this->reversement->statut === 'reussi'
            ? [
                'titre' => 'Reversement reçu',
                'texte' => "{$montant} envoyés sur votre Mobile Money ({$this->reversement->numero_masque}).",
                'lien' => '/revenus',
            ]
            : [
                'titre' => 'Reversement refusé',
                'texte' => "Le réseau a refusé {$montant} vers {$this->reversement->numero_masque} : votre solde vous est rendu.",
                'lien' => '/revenus',
            ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->reversement->statut === 'reussi'
            ? Courriel::nouveau('Vous avez reçu '.Courriel::fcfa($this->reversement->montant))
                ->line('Votre reversement de '.Courriel::fcfa($this->reversement->montant)." est arrivé sur votre Mobile Money ({$this->reversement->numero_masque}).")
                ->line('Les frais de transfert sont pris en charge par Souk.dev.')
                ->action('Voir mes revenus', Courriel::front('/revenus'))
            : Courriel::nouveau('Votre reversement n’a pas abouti')
                ->line('Le reversement de '.Courriel::fcfa($this->reversement->montant)." vers {$this->reversement->numero_masque} a été refusé par le réseau. Votre solde vous est rendu.")
                ->line('Vérifiez votre numéro Mobile Money, puis redemandez le reversement.')
                ->action('Vérifier mes coordonnées', Courriel::front('/revenus'));
    }
}
