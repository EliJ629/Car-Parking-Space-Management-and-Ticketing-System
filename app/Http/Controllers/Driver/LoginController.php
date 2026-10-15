<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private SignInService $signIn) {}

    public function create(): View
    {
        return view('driver.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'identity.required' => 'Enter the email you signed up with.',
            'password.required' => 'Enter your password.',
        ]);

        $this->signIn->attempt($request, SignInService::AREA_DRIVER, $credentials['identity'], $credentials['password']);

        return redirect()->route('driver.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signIn->signOut($request, SignInService::AREA_DRIVER);

        return redirect()->route('driver.login')->with('status', 'You have signed out.');
    }
}
