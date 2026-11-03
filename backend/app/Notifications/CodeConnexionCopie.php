<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Code de connexion d'un utilisateur à une appli déployée (pas un compte Souk.dev) : envoyé tout de suite. */
class CodeConnexionCopie extends Notification
{
    use Queueable;

    public function __construct(public string $code, public string $appli, public int $minutes)
    {
        // Le nom part dans le sujet : un retour à la ligne (nom saisi avant l'interdiction)
        // jetterait une 503 pour tous les utilisateurs de la copie (CVE-2026-45070).
        $this->appli = str_replace(["\r", "\n"], ' ', $this->appli);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Courriel::nouveau("{$this->code} : votre code pour {$this->appli}")
            ->line("Votre code de connexion à {$this->appli} est **{$this->code}**.")
            ->line("Il expire dans {$this->minutes} minutes. Si vous n'avez rien demandé, ignorez ce message.");
    }
}
