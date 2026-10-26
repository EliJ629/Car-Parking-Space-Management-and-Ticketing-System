<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A driver sign up that is waiting for the link in the email to be clicked. It is not an account yet. */
class PendingRegistration extends Model
{
    /** Hours the link in the email works. */
    public const HOURS = 24;

    public const RESEND_SECONDS = 60;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function secondsUntilResend(): int
    {
        if (! $this->sent_at) {
            return 0;
        }

        return max(0, (int) ceil(self::RESEND_SECONDS - $this->sent_at->diffInSeconds(now(), true)));
    }
}
