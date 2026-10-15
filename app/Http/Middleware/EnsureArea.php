<?php

namespace App\Http\Middleware;

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

        if (! $user->isActive()) {
            $this->signIn->signOut($request, $area);

            return redirect()->route($loginRoute)
                ->with('status', 'This account cannot sign in right now. Please ask your supervisor.');
        }

        if ($area === SignInService::AREA_STAFF) {
            $last = (int) $request->session()->get('staff_last_activity', now()->timestamp);

            if (now()->timestamp - $last > self::STAFF_IDLE_SECONDS) {
                $this->signIn->signOut($request, $area, 'idle_sign_out');

                return redirect()->route($loginRoute)
                    ->with('status', 'You were signed out after 15 minutes without activity. Please sign in again.');
            }

            $request->session()->put('staff_last_activity', now()->timestamp);
        }

        return $next($request);
    }
}
