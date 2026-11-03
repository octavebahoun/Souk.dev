<?php

namespace App\Notifications;

use App\Models\Mission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Au dev : un client lui demande une mission (version sur mesure d'une appli, ou travail direct). */
class NouvelleMission extends NotificationSouk
{
    public function __construct(public Mission $mission) {}

    public function type(): string
    {
        return 'message_mission';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $mission = $this->mission->loadMissing('client');

        return [
            'titre' => 'Nouvelle mission de '.Courriel::nom($mission->client),
            'texte' => Str::limit($mission->message, 140).($mission->budget !== null ? ' Budget : '.Courriel::fcfa($mission->budget).'.' : ''),
            'lien' => '/missions?mission='.$mission->id,
        ];
    }

    /** @return array<string, int|string|null> */
    protected function contexte(): array
    {
        return ['mission_id' => $this->mission->id];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mission = $this->mission->loadMissing('client');
        $message = Courriel::nouveau(Courriel::nom($mission->client).' vous propose une mission')
            ->line(Str::limit($mission->message, 500));

        if ($mission->budget !== null) {
            $message->line('Budget proposé : '.Courriel::fcfa($mission->budget).'.');
        }

        return $message
            ->action('Voir la mission', Courriel::front('/missions?mission='.$mission->id))
            ->line('Répondez dans le fil privé de la mission : le client y est prévenu de votre réponse.');
    }
}
