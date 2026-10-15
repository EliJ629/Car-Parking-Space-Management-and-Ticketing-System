<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
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
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Enter the email you signed up with.',
            'email.email' => 'Enter a valid email.',
        ]);

        Password::broker()->sendResetLink([
            'email' => mb_strtolower(trim($request->input('email'))),
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
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.required' => 'Choose a new password.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($request->input('email'))),
                'role' => User::ROLE_DRIVER,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) {
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
