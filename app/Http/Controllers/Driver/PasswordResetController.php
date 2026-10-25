<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordHistory;
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
 * Forgot password for drivers. Staff accounts are reset by a supervisor, so
 * every lookup here is limited to driver accounts.
 *
 * - Only an email that belongs to an existing driver account is accepted.
 * - The link goes to that account's own email and opens the new password form (new password and confirmation).
 * - An account that signs in with Google has no password to reset, so no link is sent to it.
 *
 * With MAIL_MAILER=log the reset email is written to storage/logs/laravel.log.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('driver.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->merge(['email' => Rules::email($request->input('email'))]);
        $request->validate(['email' => ['required', 'email:rfc', 'max:255']], [
            'email.required' => 'Enter the email you signed up with.',
            'email.email' => 'Enter a valid email, for example maria@gmail.com.',
            'email.max' => 'The email can have up to 255 characters.',
        ]);

        // Gmail ignores dots, so maria.santos@gmail.com finds the account saved as mariasantos@gmail.com.
        $user = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('email_key', Rules::emailKey($request->input('email')))
            ->first();

        if (! $user) {
            return back()->withInput()->withErrors([
                'email' => 'We could not find an account with that email. Check the spelling, or create an account.',
            ]);
        }

        if ($user->google_id !== null) {
            return back()->withInput()->withErrors([
                'email' => 'This account signs in with Google, so it has no password to reset. Use Continue to Google Sign-in.',
            ]);
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email, 'role' => User::ROLE_DRIVER]);
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

        ActivityLog::record('driver_password_link_sent', $user, $request, null, SignInService::AREA_DRIVER);

        return back()->with('status', 'We sent a link to '.EmailVerification::mask($user->email).'. It works for '
            .(int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60).' minutes. Open it to choose a new password.');
    }

    /** The link in the email. A link that is wrong, used or expired goes back to the request page instead of an empty form. */
    public function reset(Request $request, string $token): View|RedirectResponse
    {
        $email = (string) $request->query('email');
        $user = $email === '' ? null : User::query()->where('role', User::ROLE_DRIVER)->where('email', Rules::email($email))->first();

        if (! $user || $user->google_id !== null || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('driver.password.request')->withErrors([
                'email' => 'This link is not valid or has expired. Ask for a new one.',
            ]);
        }

        return view('driver.reset-password', [
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
            'password' => Rules::driverPassword(),
        ], Rules::driverPasswordMessages() + [
            'email.required' => 'This link is not valid. Ask for a new one.',
            'email.email' => 'This link is not valid. Ask for a new one.',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $request->input('email'),
                'role' => User::ROLE_DRIVER,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) use ($request) {
                if ($user->google_id !== null) {
                    throw ValidationException::withMessages(['email' => 'This account signs in with Google, so it has no password to reset.']);
                }

                // The new password must differ from the current one and the last 3 (screen DR-09).
                $recent = PasswordHistory::query()->where('user_id', $user->id)->latest('id')->limit(3)->pluck('password');

                if (Hash::check($password, $user->password) || $recent->contains(fn (string $hash) => Hash::check($password, $hash))) {
                    throw ValidationException::withMessages(['password' => 'Choose a password that is different from your last 3 passwords.']);
                }

                PasswordHistory::create(['user_id' => $user->id, 'password' => $user->password]);

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    // The link went to this address, so it is proven to be theirs.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                $user->clearFailedLogins();

                ActivityLog::record('driver_password_reset', $user, $request, null, SignInService::AREA_DRIVER);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('driver.password.request')
                ->withErrors(['email' => 'This link is not valid or has expired. Ask for a new one.']);
        }

        return redirect()->route('driver.login')->with('status', 'Your password was changed. Sign in with the new one.');
    }
}
