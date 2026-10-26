<?php

namespace App\Notifications;

use App\Models\Deploiement;
use App\Models\VersionAppli;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Au client d'une copie : une nouvelle version de l'appli est publiée ; il l'applique quand il veut. */
class NouvelleVersion extends NotificationSouk
{
    public function __construct(public VersionAppli $version, public Deploiement $copie) {}

    public function type(): string
    {
        return 'mise_a_jour';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $nom = $this->version->loadMissing('appli')->appli?->nom;

        return [
            'titre' => "Version {$this->version->numero} de {$nom}",
            'texte' => Str::limit((string) $this->version->notes, 140).' À appliquer quand vous le souhaitez.',
            'lien' => '/copies',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->version->loadMissing('appli')->appli?->nom;

        return Courriel::nouveau("Nouvelle version de {$nom}")
            ->line("L'auteur de {$nom} a publié la version {$this->version->numero} :")
            ->line(Str::limit((string) $this->version->notes, 500))
            ->line('Votre copie reste sur sa version actuelle tant que vous ne l’appliquez pas. Avant la mise à jour, ses données sont sauvegardées ; si la nouvelle version ne démarre pas, elle revient d’elle-même à la version actuelle.')
            ->action('Voir mes copies', Courriel::front('/copies'));
    }
}
