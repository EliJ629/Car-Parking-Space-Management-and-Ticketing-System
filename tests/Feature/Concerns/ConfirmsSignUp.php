<?php

namespace Tests\Feature\Concerns;

use App\Mail\ConfirmRegistrationMail;
use Illuminate\Support\Facades\Mail;

/** Finds the confirmation link a sign up emailed. Call Mail::fake() before the sign up. */
trait ConfirmsSignUp
{
    protected function signUpLink(string $email): string
    {
        $link = null;
        Mail::assertSent(ConfirmRegistrationMail::class, function (ConfirmRegistrationMail $mail) use ($email, &$link) {
            if ($mail->hasTo($email)) {
                $link = $mail->link;

                return true;
            }

            return false;
        });

        return (string) $link;
    }

    /** Opens the link, like clicking it in the email. */
    protected function confirmSignUp(string $email)
    {
        return $this->get($this->signUpLink($email));
    }
}
