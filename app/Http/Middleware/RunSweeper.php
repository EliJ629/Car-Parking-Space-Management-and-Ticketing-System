<?php

namespace App\Http\Middleware;

use App\Services\Sweeper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the reservation expiry, exit window and long-parking jobs when a driver or
 * guard screen opens, at most once per interval. It never stops a page from loading.
 */
class RunSweeper
{
    public function __construct(private Sweeper $sweeper) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->sweeper->run();
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
