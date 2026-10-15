<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The one sign in page for all staff. It does not ask for a role and does not
 * name any. The account decides what opens afterwards.
 */
class LoginController extends Controller
{
    public function __construct(private SignInService $signIn) {}

    public function create(): View
    {
        return view('staff.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'identity.required' => 'Enter your username or email.',
            'password.required' => 'Enter your password.',
        ]);

        $this->signIn->attempt($request, SignInService::AREA_STAFF, $credentials['identity'], $credentials['password']);

        return redirect()->route('staff.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signIn->signOut($request, SignInService::AREA_STAFF);

        return redirect()->route('staff.login')->with('status', 'You have signed out.');
    }
}
