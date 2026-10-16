<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended driver (added feature A21) cannot open any driver screen.
 * They are sent to the account notice, which has a way back to sign in.
 */
class EnsureDriverActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended() && ! $request->routeIs('driver.suspended', 'driver.logout')) {
            return redirect()->route('driver.suspended');
        }

        return $next($request);
    }
}
