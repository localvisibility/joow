<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email Joow (comptes, relances) sur le gabarit commun de notification. */
class JoowNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine, replyTo: [config('mail.from.address', 'contact@joow.fr')]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: $this->data + [
            'rows'  => [],
            'note'  => '',
            'brand' => ['name' => 'Joow', 'accent' => '#6366f1', 'url' => 'https://app.joow.fr', 'phone' => null, 'address' => null, 'email' => null],
        ]);
    }
}
