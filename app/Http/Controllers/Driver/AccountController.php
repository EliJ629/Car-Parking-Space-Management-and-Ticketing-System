<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\DriverData;
use App\Services\EmailVerification;
use App\Services\VerificationMailFailed;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request, DriverData $data): View
    {
        $user = $request->user();

        return view('driver.app.account.show', [
            'user' => $user,
            'vehicleCount' => $user->vehicles()->count(),
            'sessionCount' => $data->sessions($user)->where('status', 'completed')->count(),
            'unread' => $data->unread($user),
        ]);
    }

    /** Shown instead of Home when the Parking Manager has suspended the account (A21). */
    public function suspended(Request $request): View|RedirectResponse
    {
        if (! $request->user()->isSuspended()) {
            return redirect()->route('driver.home');
        }

        return view('driver.app.account.suspended', ['user' => $request->user()]);
    }

    public function editProfile(Request $request): View
    {
        return view('driver.app.account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $request->user();
        Rules::tidy($request, 'name');
        $request->merge([
            'email' => Rules::email($request->input('email')),
            'phone' => Lot::normalizePhone($request->input('phone')),
        ]);

        $emailChanged = Rules::emailKey($request->input('email')) !== Rules::emailKey($user->email);

        // A new email must be a Gmail address nobody else uses. An email that is not changing is left alone,
        // so an account made before the Gmail rule can still edit its name and phone.
        $emailRules = $emailChanged
            ? Rules::driverEmail($user->id)
            : ['required', 'email:rfc', 'max:255'];

        $data = $request->validate([
            'name' => array_merge(Rules::personName(), [
                fn (string $attribute, mixed $value, \Closure $fail) => Rules::driverNameTaken((string) $value, $user->id)
                    ? $fail('An account with this name already exists.')
                    : null,
            ]),
            'email' => $emailRules,
            'phone' => Rules::mobile(),
        ], Rules::personNameMessages() + Rules::driverEmailMessages() + [
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
        ]);

        $phoneChanged = $data['phone'] !== $user->phone;
        $before = $user->only(['name', 'email', 'phone']);

        // The name is saved right away. A new email or phone waits until the driver types the code we email.
        $user->update(['name' => $data['name']]);

        if (! $emailChanged && ! $phoneChanged) {
            $verification->cancelChange($user);
            ActivityLog::record('driver_profile_updated', $user, $request, null, 'driver', ['before' => $before, 'after' => $user->only(['name', 'email', 'phone'])]);

            return redirect()->route('driver.account')->with('modal', [
                'title' => 'Profile saved',
                'body' => 'Your details were updated.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        $user->forceFill([
            'pending_email' => $emailChanged ? $data['email'] : null,
            'pending_phone' => $phoneChanged ? $data['phone'] : null,
        ])->save();

        try {
            $verification->issue($user, 'change');
        } catch (VerificationMailFailed) {
            $verification->cancelChange($user);

            return back()->withInput()->with('modal', [
                'title' => 'We could not send the code',
                'body' => 'The code could not be emailed to '.($emailChanged ? $data['email'] : $user->email).'. Check the address and try again in a moment. Nothing was changed.',
                'tone' => 'stop',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        ActivityLog::record('driver_profile_change_requested', $user, $request, null, 'driver', ['email' => $emailChanged, 'phone' => $phoneChanged]);

        return redirect()->route('driver.account.confirm');
    }

    /** The code step for a new email or phone number. */
    public function confirmChange(Request $request, EmailVerification $verification): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email && ! $user->pending_phone) {
            return redirect()->route('driver.account');
        }

        return view('driver.app.account.confirm', [
            'user' => $user,
            'sentTo' => EmailVerification::mask($verification->address($user)),
            'wait' => $verification->secondsUntilResend($user),
            'minutes' => EmailVerification::CODE_MINUTES,
            'logHint' => config('mail.default') === 'log' && ! app()->isProduction(),
        ]);
    }

    public function checkChange(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email && ! $user->pending_phone) {
            return redirect()->route('driver.account');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6 digit code from your email.',
            'code.digits' => 'The code has 6 digits.',
        ]);

        $outcome = $verification->check($user, $data['code']);

        if ($outcome['result'] !== 'ok') {
            return back()->withErrors(['code' => match ($outcome['result']) {
                'wrong' => 'That code is not right. You have '.$outcome['left'].' '.($outcome['left'] === 1 ? 'try' : 'tries').' left.',
                'expired' => 'That code has expired. Send a new one.',
                'locked' => 'Too many wrong codes. Send a new code.',
                default => 'There is no code waiting. Send a new one.',
            }]);
        }

        ActivityLog::record('driver_profile_updated', $user, $request, null, 'driver', ['after' => $user->fresh()->only(['name', 'email', 'phone'])]);

        return redirect()->route('driver.account')->with('modal', [
            'title' => 'Change confirmed',
            'body' => 'Your new details are saved.',
            'primary' => ['label' => 'OK', 'close' => true],
        ]);
    }

    public function resendChange(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email && ! $user->pending_phone) {
            return redirect()->route('driver.account');
        }

        $wait = $verification->secondsUntilResend($user);

        if ($wait > 0) {
            return back()->withErrors(['code' => "Wait {$wait} seconds before asking for another code."]);
        }

        try {
            $verification->issue($user, 'change');
        } catch (VerificationMailFailed) {
            return back()->withErrors(['code' => 'We could not send the code. Try again in a moment.']);
        }

        return back()->with('modal', [
            'title' => 'New code sent',
            'body' => 'A new code was sent to '.EmailVerification::mask($verification->address($user)).'.',
            'primary' => ['label' => 'OK', 'close' => true],
        ]);
    }

    public function cancelChange(Request $request, EmailVerification $verification): RedirectResponse
    {
        $verification->cancelChange($request->user());

        return redirect()->route('driver.account.profile')->with('modal', [
            'title' => 'Change canceled',
            'body' => 'Your email and phone number stay as they were.',
            'primary' => ['label' => 'OK', 'close' => true],
        ]);
    }

    public function editPassword(): View
    {
        return view('driver.app.account.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => Rules::driverPassword(),
        ], Rules::driverPasswordMessages() + [
            'current_password.required' => 'Enter your current password.',
        ]);

        // Both problems show together, as the screen design asks.
        $errors = [];

        if (! Hash::check($data['current_password'], $user->password)) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        $recent = PasswordHistory::query()->where('user_id', $user->id)->latest('id')->limit(3)->pluck('password');

        if (Hash::check($data['password'], $user->password) || $recent->contains(fn (string $hash) => Hash::check($data['password'], $hash))) {
            $errors['password'] = 'Choose a password that is different from your last 3 passwords.';
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        PasswordHistory::create(['user_id' => $user->id, 'password' => $user->password]);
        $user->update(['password' => $data['password']]);
        ActivityLog::record('driver_password_changed', $user, $request, null, 'driver');

        return redirect()->route('driver.account')->with('modal', ['title' => 'Password updated', 'body' => 'Use your new password the next time you sign in.', 'primary' => ['label' => 'OK', 'close' => true]]);
    }
}
