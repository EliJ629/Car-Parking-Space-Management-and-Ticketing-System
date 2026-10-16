<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        if (! auth()->user()->must_change_password) {
            return redirect()->route('staff.home');
        }

        return view('staff.password-change', ['forced' => true]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // This screen is only for the first sign in or after a reset. Everyone else changes
        // the password from their own account page, where the current password is asked for.
        if (! $user->must_change_password) {
            return redirect()->route('staff.home');
        }

        $validated = $request->validate([
            'password' => Rules::staffPassword(),
        ], Rules::staffPasswordMessages());

        if (Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Choose a new password. It cannot be the temporary password.']);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();
        ActivityLog::record('password_changed', $user, $request, $user->username, 'staff');

        return redirect()->route('staff.home')->with('status', 'Your password was changed.');
    }
}
