<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Alerte à l'équipe (ADMIN_EMAIL) : un cas d'argent ou d'exploitation qu'aucun automatisme ne
 * doit trancher seul (montant à vérifier, copie payée qui ne démarre pas, reversement bloqué…).
 */
class AlerteAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lignes
     */
    public function __construct(public string $sujet, public array $lignes, public string $chemin = '/admin/finances') {}

    /**
     * Envoie l'alerte à l'adresse de l'équipe.
     *
     * @param  list<string>  $lignes
     */
    public static function envoyer(string $sujet, array $lignes, string $chemin = '/admin/finances'): void
    {
        Notifications::route('mail', (string) config('app.admin_email'))->notify(new self($sujet, $lignes, $chemin));
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = Courriel::nouveau('[Admin] '.$this->sujet);

        foreach ($this->lignes as $ligne) {
            $message->line($ligne);
        }

        return $message->action('Ouvrir l’administration', Courriel::front($this->chemin));
    }
}
