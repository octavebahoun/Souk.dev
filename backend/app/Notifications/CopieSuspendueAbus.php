<?php

namespace App\Notifications;

use App\Models\Deploiement;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Copie suspendue pour abus (minage, hameçonnage…) : conteneurs arrêtés, page neutre sur le
 * sous-domaine, volumes gardés. L'e-mail part toujours : le client doit savoir pourquoi sa
 * copie ne répond plus, et que ses données sont gardées.
 */
class CopieSuspendueAbus extends NotificationSouk
{
    public function __construct(public Deploiement $copie, public string $motif) {}

    public function type(): string
    {
        return 'suspension';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $nom = $this->copie->loadMissing('appli')->appli?->nom ?? 'votre copie';

        return [
            'titre' => "Copie de {$nom} suspendue",
            'texte' => 'Motif : '.$this->motif.'. Les données sont gardées. Pour contester, répondez à l’e-mail reçu.',
            'lien' => '/copies',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = $this->copie->loadMissing('appli')->appli?->nom ?? 'votre copie';

        return Courriel::nouveau("Votre copie de {$nom} est suspendue")
            ->line('Votre copie est suspendue pour abus. Motif : '.$this->motif)
            ->line('Ses conteneurs sont arrêtés et son adresse affiche une page neutre ; ses données sont gardées.')
            ->line('Pour contester cette décision, répondez à cet e-mail : l’équipe le relira.')
            ->line('Politique d’abus : '.Courriel::front('/legal/abus'));
    }
}
