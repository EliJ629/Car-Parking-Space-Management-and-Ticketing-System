<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\EmailNotVerified;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Services\VerificationMailFailed;
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

        try {
            $this->signIn->attempt($request, SignInService::AREA_DRIVER, $credentials['identity'], $credentials['password']);
        } catch (EmailNotVerified $e) {
            // Right password, but the email was never confirmed. Send the code step.
            $verification = app(EmailVerification::class);

            try {
                if (! $verification->hasLiveCode($e->user)) {
                    $verification->issue($e->user);
                }
            } catch (VerificationMailFailed) {
                return back()->withInput($request->only('identity'))->withErrors([
                    'identity' => 'Your email is not confirmed yet and we could not send the code. Try again in a moment.',
                ]);
            }

            $request->session()->put(VerifyEmailController::SESSION_KEY, $e->user->id);

            return redirect()->route('driver.verify')->with('status', 'Confirm your email first. We sent a code to '.EmailVerification::mask($e->user->email).'.');
        }

        return redirect()->route('driver.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signIn->signOut($request, SignInService::AREA_DRIVER);

        return redirect()->route('driver.login')->with('status', 'You have signed out.');
    }
}
