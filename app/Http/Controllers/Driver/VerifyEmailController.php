<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Services\VerificationMailFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The code step after sign up (and after a sign in with an email that was never confirmed), and the email link. */
class VerifyEmailController extends Controller
{
    public const SESSION_KEY = 'pending_verification_user_id';

    public function __construct(private EmailVerification $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pending($request);

        if (! $user) {
            return redirect()->route('driver.login');
        }

        return view('driver.verify', [
            'maskedEmail' => EmailVerification::mask($user->email),
            'wait' => $this->verification->secondsUntilResend($user),
            'minutes' => EmailVerification::CODE_MINUTES,
            'logHint' => config('mail.default') === 'log' && ! app()->isProduction(),
            'devCode' => app()->isLocal() ? session('pending_verification_dev_code', '943606') : null,
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if (! $user) {
            return redirect()->route('driver.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6 digit code from your email.',
            'code.digits' => 'The code has 6 digits.',
        ]);

        $outcome = $this->verification->check($user, $data['code']);

        if ($outcome['result'] !== 'ok') {
            return back()->withErrors(['code' => match ($outcome['result']) {
                'wrong' => 'That code is not right. You have '.$outcome['left'].' '.($outcome['left'] === 1 ? 'try' : 'tries').' left.',
                'expired' => 'That code has expired. Send a new one.',
                'locked' => 'Too many wrong codes. Send a new code.',
                default => 'There is no code waiting. Send a new one.',
            }]);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user->refresh());
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('driver_email_verified', $user, $request, null, SignInService::AREA_DRIVER);

        return redirect()->route('driver.home')->with('modal', [
            'title' => 'Email confirmed',
            'body' => 'Your account is ready. Welcome to Bayline.',
            'primary' => ['label' => 'Start', 'close' => true],
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if (! $user) {
            return redirect()->route('driver.login');
        }

        $wait = $this->verification->secondsUntilResend($user);

        if ($wait > 0) {
            return back()->withErrors(['code' => "Wait {$wait} seconds before asking for another code."]);
        }

        try {
            $this->verification->issue($user);
        } catch (VerificationMailFailed) {
            return back()->withErrors(['code' => 'We could not send the code. Check the Gmail address and try again in a moment.']);
        }

        return back()->with('status', 'A new code was sent to '.EmailVerification::mask($user->email).'.');
    }

    /** The email was typed wrong. Remove the unconfirmed account so the person can register again. */
    public function restart(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user && ! $user->hasVerifiedEmail()) {
            Vehicle::withTrashed()->where('user_id', $user->id)->forceDelete();
            $user->delete();
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('driver.register')->with('status', 'Start again with the right Gmail address.');
    }

    /** The one tap link from the email. It confirms the address and sends the person to sign in. */
    public function link(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless($user->isDriver(), 404);

        if (! $this->verification->linkMatches($user, $hash)) {
            return redirect()->route($request->user() ? 'driver.account' : 'driver.login')
                ->with('status', $user->hasVerifiedEmail() ? 'This email is already confirmed.' : 'This link is no longer valid. Ask for a new code.');
        }

        $changed = $user->pending_email !== null || $user->pending_phone !== null;
        $this->verification->complete($user);
        ActivityLog::record($changed ? 'driver_profile_confirmed' : 'driver_email_verified', $user, $request, null, SignInService::AREA_DRIVER);
        $request->session()->forget(self::SESSION_KEY);

        if ($request->user()?->is($user)) {
            return redirect()->route('driver.account')->with('modal', [
                'title' => 'Change confirmed',
                'body' => 'Your profile was updated.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        return redirect()->route('driver.login')->with('status', 'Email confirmed. You can sign in now.');
    }

    private function pending(Request $request): ?User
    {
        $id = (int) $request->session()->get(self::SESSION_KEY, 0);
        $user = $id ? User::query()->where('role', User::ROLE_DRIVER)->find($id) : null;

        if ($user && $user->hasVerifiedEmail()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $user;
    }
}
