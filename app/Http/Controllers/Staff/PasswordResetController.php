<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Support\MailFailure;
use App\Support\Rules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Forgot password for the booth attendant and the security guard.
 *
 * - Only an email that belongs to an active attendant or guard account is accepted.
 * - The link goes to that account's own email and opens a form for a new password and its confirmation.
 * - A manager account is not reset here. A manager resets staff passwords from the Accounts screen, and an
 *   account without an email also goes through the manager.
 *
 * With MAIL_MAILER=log the email is written to storage/logs/laravel.log.
 */
class PasswordResetController extends Controller
{
    /** @var list<string> */
    private const ROLES = [User::ROLE_ATTENDANT, User::ROLE_GUARD];

    public function request(): View
    {
        return view('staff.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->merge(['email' => Rules::email($request->input('email'))]);
        $request->validate(['email' => ['required', 'email:rfc', 'max:255']], [
            'email.required' => 'Enter the email on your staff account.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'The email can have up to 255 characters.',
        ]);

        $user = $this->find((string) $request->input('email'));

        if (! $user) {
            return back()->withInput()->withErrors([
                'email' => 'We could not find a booth attendant or guard account with that email. Check the spelling, or ask your supervisor.',
            ]);
        }

        if (! $user->isActive()) {
            return back()->withInput()->withErrors(['email' => 'This account cannot sign in right now. Please ask your supervisor.']);
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email, 'role' => self::ROLES]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => 'We could not send the email. Try again in a moment.'.MailFailure::screenDetail($e)]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => 'A link was sent a moment ago. Wait a minute before asking for another.']);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withInput()->withErrors(['email' => 'We could not send the email. Try again in a moment.']);
        }

        ActivityLog::record('staff_password_link_sent', $user, $request, $user->username, SignInService::AREA_STAFF);

        return back()->with('status', 'We sent a link to '.EmailVerification::mask($user->email).'. It works for '
            .(int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60).' minutes. Open it to choose a new password.');
    }

    /** The link in the email. A link that is wrong, used or expired goes back to the request page. */
    public function reset(Request $request, string $token): View|RedirectResponse
    {
        $email = (string) $request->query('email');
        $user = $email === '' ? null : $this->find($email);

        if (! $user || ! $user->isActive() || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('staff.password.request')->withErrors([
                'email' => 'This link is not valid or has expired. Ask for a new one.',
            ]);
        }

        return view('staff.reset-password', [
            'token' => $token,
            'email' => $user->email,
            'maskedEmail' => EmailVerification::mask($user->email),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => Rules::email($request->input('email'))]);
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => Rules::staffPassword(),
        ], Rules::staffPasswordMessages() + [
            'email.required' => 'This link is not valid. Ask for a new one.',
            'email.email' => 'This link is not valid. Ask for a new one.',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $request->input('email'),
                'role' => self::ROLES,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) use ($request) {
                if (! $user->isActive()) {
                    throw ValidationException::withMessages(['email' => 'This account cannot sign in right now. Please ask your supervisor.']);
                }

                if (Hash::check($password, $user->password)) {
                    throw ValidationException::withMessages(['password' => 'Choose a password that is different from the current one.']);
                }

                // A reset replaces any temporary password, so the "choose a new password" step is done.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();
                $user->clearFailedLogins();

                ActivityLog::record('password_reset', $user, $request, $user->username, SignInService::AREA_STAFF);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('staff.password.request')
                ->withErrors(['email' => 'This link is not valid or has expired. Ask for a new one.']);
        }

        return redirect()->route('staff.login')->with('status', 'Your password was changed. Sign in with the new one.');
    }

    private function find(string $email): ?User
    {
        $email = Rules::email($email);

        return User::query()
            ->whereIn('role', self::ROLES)
            ->where(fn ($query) => $query->where('email_key', Rules::emailKey($email))->orWhereRaw('lower(email) = ?', [mb_strtolower($email)]))
            ->first();
    }
}
