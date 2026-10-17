<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class EvenementAnnuleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $titre) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Événement annulé — Souk.dev');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.evenement-annule');
    }
}
