<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\DriverData;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone' => Lot::normalizePhone($request->input('phone')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
        ], [
            'name.required' => 'Enter your full name.',
            'email.required' => 'Enter your email.',
            'email.email' => 'Enter a valid email, for example maria@example.com.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => 'Enter a mobile number like 0917 555 0142.',
        ]);

        $before = $user->only(['name', 'email', 'phone']);
        $user->update(['name' => trim($data['name']), 'email' => $data['email'], 'phone' => $data['phone']]);
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Enter your current password.',
            'password.required' => 'Choose a new password.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $recent = PasswordHistory::query()->where('user_id', $user->id)->latest('id')->limit(3)->pluck('password');

        if (Hash::check($data['password'], $user->password) || $recent->contains(fn (string $hash) => Hash::check($data['password'], $hash))) {
            return back()->withErrors(['password' => 'Choose a password that is different from your last 3 passwords.']);
        }

        PasswordHistory::create(['user_id' => $user->id, 'password' => $user->password]);
        $user->update(['password' => $data['password']]);
        ActivityLog::record('driver_password_changed', $user, $request, null, 'driver');

        return redirect()->route('driver.account')->with('toast', ['Password updated', 'Use your new password the next time you sign in.']);
    }
}
