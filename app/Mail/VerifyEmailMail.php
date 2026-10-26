<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The email with the six digit code and the one tap link. Sent through the mail service set in .env (Brevo). */
class VerifyEmailMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $code,
        public string $link,
        public int $minutes,
        public string $purpose = 'signup',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your Bayline verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-email', text: 'emails.verify-email-text');
    }
}
