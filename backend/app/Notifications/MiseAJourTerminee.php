<?php

namespace App\Notifications;

use App\Models\MiseAJour;
use Illuminate\Notifications\Messages\MailMessage;

/** Au client : sa mise à jour a réussi, ou la copie est revenue à la version précédente. */
class MiseAJourTerminee extends NotificationSouk
{
    public function __construct(public MiseAJour $miseAJour) {}

    public function type(): string
    {
        return 'mise_a_jour';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $mise = $this->miseAJour->loadMissing(['deploiement.appli', 'versVersion', 'deVersion']);
        $nom = $mise->deploiement?->appli?->nom;

        return match ($mise->statut) {
            'reussie' => [
                'titre' => "{$nom} est à jour",
                'texte' => "Votre copie tourne sur la version {$mise->versVersion?->numero}, avec ses données.",
                'lien' => '/copies',
            ],
            'retablie' => [
                'titre' => "Mise à jour de {$nom} annulée",
                'texte' => "La version {$mise->versVersion?->numero} n’a pas démarré : votre copie est revenue à la version {$mise->deVersion?->numero}, avec ses données.",
                'lien' => '/copies',
            ],
            default => [
                'titre' => "Mise à jour de {$nom} en échec",
                'texte' => 'La copie n’a pas pu repartir. Notre équipe est prévenue et la remet en ligne.',
                'lien' => '/copies',
            ],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contenu = $this->contenu();
        $message = Courriel::nouveau($contenu['titre'])->line($contenu['texte']);

        if ($this->miseAJour->statut === 'retablie' && $this->miseAJour->erreur !== null) {
            $message->line('Ce qui a bloqué : '.$this->miseAJour->erreur);
        }

        return $message->action('Voir mes copies', Courriel::front('/copies'));
    }
}
