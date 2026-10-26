<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\EmailNotVerified;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Services\VerificationMailFailed;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        // Right email and password, but the link in the email was never clicked, so there is no account yet.
        if ($pending = $this->waitingSignUp($credentials['identity'], $credentials['password'])) {
            $request->session()->put(RegisterController::SESSION_KEY, $pending->id);

            return redirect()->route('driver.register.sent')
                ->with('status', 'Confirm your email first. Click the link we sent to '.EmailVerification::mask($pending->email).'.');
        }

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

    private function waitingSignUp(string $identity, string $password): ?PendingRegistration
    {
        $key = Rules::emailKey(Rules::email($identity));

        if ($key === '' || User::query()->where('email_key', $key)->exists()) {
            return null;
        }

        $pending = PendingRegistration::query()->where('email_key', $key)->where('expires_at', '>', now())->first();

        return $pending && Hash::check($password, $pending->password) ? $pending : null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signIn->signOut($request, SignInService::AREA_DRIVER);

        return redirect()->route('driver.login')->with('status', 'You have signed out.');
    }
}
