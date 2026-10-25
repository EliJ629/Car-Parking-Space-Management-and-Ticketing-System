<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The email with the link to choose a new password. Sent through the mail service set in .env (Brevo). */
class ResetPasswordMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $link,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Choose a new password for Bayline');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset-password', text: 'emails.reset-password-text');
    }
}
