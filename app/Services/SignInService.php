<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * One place for the sign in rules shared by the staff and driver pages.
 *
 * - A wrong username and a wrong password give the same message, so nobody can
 *   find out which usernames exist.
 * - Five wrong passwords in a row lock the account for 15 minutes.
 * - Only active accounts get in.
 * - Every sign in, failed sign in, lock and sign out is written to the activity log.
 */
class SignInService
{
    public const AREA_STAFF = 'staff';

    public const AREA_DRIVER = 'driver';

    /**
     * Sign the user in or throw a ValidationException with a message for the form.
     */
    public function attempt(Request $request, string $area, string $identity, string $password): User
    {
        $identity = trim($identity);
        $user = $this->findUser($area, $identity);

        if ($user && $user->isLocked()) {
            ActivityLog::record('sign_in_failed', $user, $request, $identity, $area);
            $this->fail($this->lockedMessage($user));
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            if (! $user) {
                // Spend the same time as a real check so the response time does not give the username away.
                Hash::check($password, Hash::make('bayline-no-such-user'));
            }

            $user?->recordFailedLogin();
            ActivityLog::record('sign_in_failed', $user, $request, $identity, $area);

            if ($user && $user->isLocked()) {
                ActivityLog::record('account_locked', $user, $request, $identity, $area);
                $this->fail($this->lockedMessage($user));
            }

            $this->fail($area === self::AREA_STAFF
                ? 'Username or password is incorrect.'
                : 'Email or password is incorrect.');
        }

        if (! $user->isActive()) {
            ActivityLog::record('sign_in_failed', $user, $request, $identity, $area);
            $this->fail('This account cannot sign in right now. Please ask your supervisor.');
        }

        $user->clearFailedLogins();

        Auth::login($user);
        $request->session()->regenerate();

        if ($area === self::AREA_STAFF) {
            $request->session()->put('staff_last_activity', now()->timestamp);
        }

        ActivityLog::record('sign_in', $user, $request, null, $area);

        return $user;
    }

    /**
     * Sign the current user out and write it to the log.
     */
    public function signOut(Request $request, string $area, string $action = 'sign_out'): void
    {
        $user = $request->user();

        if ($user) {
            ActivityLog::record($action, $user, $request, null, $area);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function findUser(string $area, string $identity): ?User
    {
        if ($identity === '') {
            return null;
        }

        if ($area === self::AREA_STAFF) {
            return User::query()
                ->whereIn('role', User::STAFF_ROLES)
                ->where(function ($query) use ($identity) {
                    $query->whereRaw('lower(username) = ?', [mb_strtolower($identity)])
                        ->orWhereRaw('lower(email) = ?', [mb_strtolower($identity)]);
                })
                ->first();
        }

        return User::query()
            ->where('role', User::ROLE_DRIVER)
            ->whereRaw('lower(email) = ?', [mb_strtolower($identity)])
            ->first();
    }

    private function lockedMessage(User $user): string
    {
        $minutes = $user->lockMinutesLeft();

        return 'Too many wrong passwords. This account is locked for '.$minutes.' more '
            .($minutes === 1 ? 'minute' : 'minutes').'.';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['identity' => $message]);
    }
}
