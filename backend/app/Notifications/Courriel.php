<?php

namespace App\Notifications;

use App\Models\User;
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

    /** Nom affiché d'un compte : son nom, sinon son identifiant GitHub. */
    public static function nom(?User $compte): string
    {
        return $compte?->name ?: ($compte?->username ?: 'Un membre de Souk.dev');
    }

    public static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }
}
