<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\SignInService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staff pages for staff accounts and the driver pages for driver accounts.
 *
 * Use it as "area:staff" or "area:driver". Staff sessions also end after
 * 15 minutes without activity.
 */
class EnsureArea
{
    /** Seconds of silence before a staff session ends. */
    public const STAFF_IDLE_SECONDS = 15 * 60;

    public function __construct(private SignInService $signIn) {}

    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();
        $loginRoute = $area === SignInService::AREA_STAFF ? 'staff.login' : 'driver.login';

        if (! $user) {
            return redirect()->route($loginRoute);
        }

        $belongs = $area === SignInService::AREA_STAFF ? $user->isStaff() : $user->isDriver();

        if (! $belongs) {
            return redirect()->route($user->isStaff() ? 'staff.home' : 'driver.home');
        }

        // A suspended driver may stay signed in, but only sees the account notice (EnsureDriverActive).
        $suspendedDriver = $area === SignInService::AREA_DRIVER && $user->isSuspended();

        if (! $user->isActive() && ! $suspendedDriver) {
            $this->signIn->signOut($request, $area);

            return redirect()->route($loginRoute)
                ->with('status', 'This account cannot sign in right now. Please ask your supervisor.');
        }

        if ($area === SignInService::AREA_STAFF) {
            $last = (int) $request->session()->get('staff_last_activity', now()->timestamp);

            $idleTimeout = (int) SystemSetting::value('idle_timeout_minutes', 15) * 60;

            if (now()->timestamp - $last > $idleTimeout) {
                $this->signIn->signOut($request, $area, 'idle_sign_out');

                return redirect()->route($loginRoute)
                    ->with('status', 'You were signed out after 15 minutes without activity. Please sign in again.');
            }

            // Background polling must not keep an unattended tablet signed in.
            if (! $request->headers->has('X-Bayline-Poll')) {
                $request->session()->put('staff_last_activity', now()->timestamp);
            }

            if ($user->must_change_password && ! $request->routeIs('staff.password.change', 'staff.password.change.update', 'staff.logout')) {
                return redirect()->route('staff.password.change');
            }
        }

        return $next($request);
    }
}
