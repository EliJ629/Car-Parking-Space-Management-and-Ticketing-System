<?php

use App\Http\Middleware\EnsureArea;
use App\Http\Middleware\EnsureDriverActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RunSweeper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/driver.php',
            __DIR__.'/../routes/guard.php',
            __DIR__.'/../routes/attendant.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'area' => EnsureArea::class,
            'role' => EnsureRole::class,
            'driver.active' => EnsureDriverActive::class,
            'sweep' => RunSweeper::class,
        ]);

        // Someone who is already signed in and opens a sign in page goes to their own home.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isStaff()
            ? route('staff.home')
            : route('driver.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // A photo bigger than PHP's post_max_size empties the whole form, so Laravel never sees the fields.
        // Send the driver back to the reservation pop-up with a clear reason instead of an error page.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('driver/reserve')) {
                return null;
            }

            $back = (string) $request->headers->get('referer');
            $path = $back !== '' && str_starts_with($back, $request->getSchemeAndHttpHost().'/driver/reserve')
                ? strtok($back, '#')
                : route('driver.reserve', ['step' => 1]);
            $path = preg_replace('/([?&])photo=[^&]*&?/', '$1', $path);
            $path = rtrim($path, '?&');

            return redirect($path.(str_contains($path, '?') ? '&' : '?').'photo=too_large');
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
