<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = $request->user();
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
