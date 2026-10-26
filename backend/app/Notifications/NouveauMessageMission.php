<?php

namespace App\Notifications;

use App\Models\MissionMessage;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** À l'autre personne d'une mission : un message dans le fil privé. */
class NouveauMessageMission extends NotificationSouk
{
    public function __construct(public MissionMessage $message) {}

    public function type(): string
    {
        return 'message_mission';
    }

    /** @return array{titre: string, texte: string, lien: string|null} */
    public function contenu(): array
    {
        $message = $this->message->loadMissing('auteur');

        return [
            'titre' => 'Message de '.Courriel::nom($message->auteur),
            'texte' => Str::limit($message->texte, 140),
            'lien' => '/missions?mission='.$message->mission_id,
        ];
    }

    /** @return array<string, int|string|null> */
    protected function contexte(): array
    {
        return ['mission_id' => $this->message->mission_id];
    }

    /** Un e-mail par fil toutes les 30 minutes au plus : une conversation n'inonde pas la boîte. */
    protected function meriteUnEmail(User $compte): bool
    {
        return Cache::add("notification:mission:{$compte->id}:{$this->message->mission_id}", true, now()->addMinutes(30));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message->loadMissing('auteur');

        return Courriel::nouveau('Nouveau message de '.Courriel::nom($message->auteur).' sur votre mission')
            ->line(Str::limit($message->texte, 300))
            ->action('Répondre', Courriel::front('/missions?mission='.$message->mission_id))
            ->line('Pour ne pas encombrer votre boîte, ce fil n’envoie pas plus d’un e-mail par demi-heure : les messages suivants vous attendent dans Souk.dev.');
    }
}
