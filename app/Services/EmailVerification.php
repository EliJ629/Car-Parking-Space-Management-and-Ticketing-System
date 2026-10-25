<?php

namespace App\Services;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The email check for driver accounts. A six digit code and a one tap link go to the Gmail address.
 * The same code confirms a new email or phone number when a driver edits their profile.
 * The mail goes out through the mail service set in .env (Brevo). If that service refuses the address, the
 * code step fails with VerificationMailFailed and the screen says so.
 */
class EmailVerification
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const LINK_MINUTES = 60;

    /** The address the code goes to: the new email when one is waiting, otherwise the account email. */
    public function address(User $user): string
    {
        return (string) ($user->pending_email ?: $user->email);
    }

    /** Make a new code, store its hash and email it. @throws VerificationMailFailed */
    public function issue(User $user, string $purpose = 'signup'): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'verification_code_hash' => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'verification_attempts' => 0,
            'verification_sent_at' => now(),
        ])->save();

        try {
            Mail::to($this->address($user))->send(new VerifyEmailMail($user->name, $code, $this->link($user), self::CODE_MINUTES, $purpose));
        } catch (Throwable $e) {
            report($e);

            throw new VerificationMailFailed('The code could not be sent.', 0, $e);
        }
    }

    /** True when a code is waiting and has not expired, so a login does not send a second email for nothing. */
    public function hasLiveCode(User $user): bool
    {
        return $user->verification_code_hash !== null
            && $user->verification_code_expires_at?->isFuture() === true
            && $user->verification_attempts < self::MAX_ATTEMPTS;
    }

    public function secondsUntilResend(User $user): int
    {
        if (! $user->verification_sent_at) {
            return 0;
        }

        return max(0, (int) ceil(self::RESEND_SECONDS - $user->verification_sent_at->diffInSeconds(now(), true)));
    }

    /** @return array{result:string,left:int} result is ok, wrong, expired, locked or missing */
    public function check(User $user, string $code): array
    {
        if ($user->verification_code_hash === null) {
            return ['result' => 'missing', 'left' => 0];
        }

        if ($user->verification_attempts >= self::MAX_ATTEMPTS) {
            return ['result' => 'locked', 'left' => 0];
        }

        if (! $user->verification_code_expires_at?->isFuture()) {
            return ['result' => 'expired', 'left' => 0];
        }

        if (! Hash::check(trim($code), $user->verification_code_hash)) {
            $user->forceFill(['verification_attempts' => $user->verification_attempts + 1])->save();
            $left = max(0, self::MAX_ATTEMPTS - $user->verification_attempts);

            return ['result' => $left === 0 ? 'locked' : 'wrong', 'left' => $left];
        }

        $this->complete($user);

        return ['result' => 'ok', 'left' => 0];
    }

    /** Mark the email as confirmed and apply the new email or phone that was waiting. */
    public function complete(User $user): void
    {
        $changes = [
            'verification_code_hash' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ];

        if ($user->pending_email) {
            $changes['email'] = $user->pending_email;
            $changes['pending_email'] = null;
            $changes['email_verified_at'] = now();
        }

        if ($user->pending_phone) {
            $changes['phone'] = $user->pending_phone;
            $changes['pending_phone'] = null;
        }

        $user->forceFill($changes)->save();
    }

    /** Drop a waiting change without applying it. */
    public function cancelChange(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_phone' => null,
            'verification_code_hash' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
        ])->save();
    }

    /** The one tap link in the email. It is signed, so it cannot be edited, and it expires. */
    public function link(User $user): string
    {
        return URL::temporarySignedRoute('driver.verify.link', now()->addMinutes(self::LINK_MINUTES), [
            'user' => $user->id,
            'hash' => sha1($this->address($user)),
        ]);
    }

    /** True when the link was made for the address that is waiting to be confirmed. */
    public function linkMatches(User $user, string $hash): bool
    {
        $waiting = ! $user->hasVerifiedEmail() || $user->pending_email !== null || $user->pending_phone !== null;

        return $waiting && hash_equals(sha1($this->address($user)), $hash);
    }

    /** "ma***@gmail.com" for the screen. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(2, mb_strlen($local) - 2)).'@'.$domain;
    }
}
