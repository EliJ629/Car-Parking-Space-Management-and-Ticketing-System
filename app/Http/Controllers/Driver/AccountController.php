<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\DriverData;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        Rules::tidy($request, 'name');
        $request->merge([
            'email' => Rules::email($request->input('email')),
            'phone' => Lot::normalizePhone($request->input('phone')),
        ]);

        $data = $request->validate([
            'name' => Rules::personName(),
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => Rules::mobile(),
        ], Rules::personNameMessages() + [
            'email.required' => 'Enter your email.',
            'email.email' => 'Enter a valid email, for example maria@example.com.',
            'email.max' => 'The email can have up to 255 characters.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
        ]);

        $before = $user->only(['name', 'email', 'phone']);
        $user->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']]);
        ActivityLog::record('driver_profile_updated', $user, $request, null, 'driver', ['before' => $before, 'after' => $user->only(['name', 'email', 'phone'])]);

        return redirect()->route('driver.account')->with('toast', ['Profile saved', 'Your details were updated.']);
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

        return redirect()->route('driver.account')->with('toast', ['Password updated', 'Use your new password the next time you sign in.']);
    }
}
