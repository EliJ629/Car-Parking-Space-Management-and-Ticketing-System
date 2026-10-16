<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Support\Rules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Forgot password for drivers. Staff accounts are reset by a supervisor, so
 * every lookup here is limited to driver accounts.
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
            'email.email' => 'Enter a valid email, for example maria@example.com.',
            'email.max' => 'The email can have up to 255 characters.',
        ]);

        Password::broker()->sendResetLink([
            'email' => $request->input('email'),
            'role' => User::ROLE_DRIVER,
        ]);

        // Same answer whether or not the email exists, so nobody can check who has an account.
        return back()->with('status', 'If an account uses that email, we sent a link to reset the password.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('driver.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
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
            'email.required' => 'Enter the email you signed up with.',
            'email.email' => 'Enter a valid email, for example maria@example.com.',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $request->input('email'),
                'role' => User::ROLE_DRIVER,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) {
                // The new password must differ from the current one and the last 3 (screen DR-09).
                $recent = PasswordHistory::query()->where('user_id', $user->id)->latest('id')->limit(3)->pluck('password');

                if (Hash::check($password, $user->password) || $recent->contains(fn (string $hash) => Hash::check($password, $hash))) {
                    throw ValidationException::withMessages(['password' => 'Choose a password that is different from your last 3 passwords.']);
                }

                PasswordHistory::create(['user_id' => $user->id, 'password' => $user->password]);

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->clearFailedLogins();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This reset link is not valid or has expired. Ask for a new one.']);
        }

        return redirect()->route('driver.login')->with('status', 'Your password was changed. Sign in with the new one.');
    }
}
