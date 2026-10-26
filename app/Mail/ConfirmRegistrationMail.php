<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The email with the link that finishes a driver sign up. Sent through the mail service set in .env (Brevo). */
class ConfirmRegistrationMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $link,
        public int $hours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your email to finish your Bayline account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirm-registration', text: 'emails.confirm-registration-text');
    }
}
