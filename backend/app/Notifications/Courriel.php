<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** Mise en forme commune des e-mails : salutation, signature, lien vers le front. */
final class Courriel
{
    public static function nouveau(string $sujet): MailMessage
    {
        return (new MailMessage)
            ->subject($sujet)
            ->greeting('Bonjour,')
            ->salutation("L'équipe Souk.dev");
    }

    public static function front(string $chemin): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$chemin;
    }

    public static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }
}
