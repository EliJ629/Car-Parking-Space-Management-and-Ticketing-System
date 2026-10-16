<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'phone', 'password', 'role', 'status', 'last_login_at', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_MANAGER = 'manager';

    public const ROLE_ATTENDANT = 'attendant';

    public const ROLE_GUARD = 'guard';

    public const ROLE_DRIVER = 'driver';

    /** Roles that sign in through the shared staff page. */
    public const STAFF_ROLES = [self::ROLE_MANAGER, self::ROLE_ATTENDANT, self::ROLE_GUARD];

    public const STATUS_ACTIVE = 'active';

    /** Wrong passwords in a row before the account locks. */
    public const MAX_FAILED_LOGINS = 5;

    /** Minutes an account stays locked. */
    public const LOCK_MINUTES = 15;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function lockMinutesLeft(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($this->locked_until, true) / 60));
    }

    /**
     * Count a wrong password. The fifth one in a row locks the account.
     */
    public function recordFailedLogin(): void
    {
        $count = $this->failed_login_count + 1;
        $maxAttempts = (int) SystemSetting::value('failed_sign_ins_before_lock', self::MAX_FAILED_LOGINS);

        if ($count >= $maxAttempts) {
            $this->forceFill([
                'failed_login_count' => 0,
                'locked_until' => now()->addMinutes(
                    (int) SystemSetting::value('lock_duration_minutes', self::LOCK_MINUTES),
                ),
            ])->save();

            return;
        }

        $this->forceFill(['failed_login_count' => $count])->save();
    }

    public function clearFailedLogins(): void
    {
        if ($this->failed_login_count === 0 && $this->locked_until === null) {
            return;
        }

        $this->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();
    }

    /**
     * The name of the post, used only after sign in.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_MANAGER => 'Parking Manager',
            self::ROLE_ATTENDANT => 'Booth Attendant',
            self::ROLE_GUARD => 'Security Guard',
            default => 'Driver',
        };
    }
}
