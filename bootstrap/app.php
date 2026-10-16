<?php

use App\Http\Middleware\EnsureArea;
use App\Http\Middleware\EnsureDriverActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RunSweeper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/driver.php',
            __DIR__.'/../routes/guard.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
