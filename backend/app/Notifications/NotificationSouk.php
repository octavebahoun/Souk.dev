<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification du centre : enregistrée dans Souk.dev et diffusée en direct sur le canal privé du
 * compte, envoyée par e-mail, selon les préférences du compte pour son type.
 */
abstract class NotificationSouk extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> Contenu diffusé en direct, préparé par toBroadcast(). */
    public array $diffusion = [];

    /** Clé du type (TypesNotification::TYPES). */
    abstract public function type(): string;

    /**
     * Ce que montre le centre : un titre, une phrase, un chemin du front vers l'écran concerné.
     *
     * @return array{titre: string, texte: string, lien: string|null}
     */
    abstract public function contenu(): array;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $canaux = [];
        if ($notifiable->recoitNotification($this->type(), 'application')) {
            array_push($canaux, 'database', 'broadcast');
        }
        if ($notifiable->joignableParEmail() && $notifiable->recoitNotification($this->type(), 'email') && $this->meriteUnEmail($notifiable)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /** Un type peut espacer ses e-mails : un fil de messages n'en envoie pas un par message. */
    protected function meriteUnEmail(User $compte): bool
    {
        return true;
    }

    /**
     * Données gardées avec la notification sans être montrées (identifiants utiles au regroupement).
     *
     * @return array<string, int|string|null>
     */
    protected function contexte(): array
    {
        return [];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [...$this->contenu(), ...$this->contexte()];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $this->diffusion = [
            'id' => $this->id,
            'type' => $this->type(),
            ...$this->contenu(),
            'lue' => false,
            'cree_le' => now()->toIso8601String(),
        ];

        return new BroadcastMessage($this->diffusion);
    }

    public function broadcastAs(): string
    {
        return 'notification.creee';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->diffusion;
    }
}
