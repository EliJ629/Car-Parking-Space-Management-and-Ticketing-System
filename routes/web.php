<?php

use App\Http\Controllers\Driver\LoginController as DriverLoginController;
use App\Http\Controllers\Driver\PasswordResetController;
use App\Http\Controllers\Driver\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Staff\LoginController as StaffLoginController;
use Illuminate\Support\Facades\Route;

// The first page people see is the driver page. Staff have their own address.
Route::redirect('/', '/driver');

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
| One landing page and one sign in page for every staff member. There is no
| Create account page: staff accounts are made inside the system.
*/
Route::get('/staff', [LandingController::class, 'staff'])->name('staff.landing');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [StaffLoginController::class, 'create'])->name('login');
        Route::post('login', [StaffLoginController::class, 'store'])->middleware('throttle:20,1');
    });

    Route::middleware('area:staff')->group(function () {
        Route::get('home', [HomeController::class, 'staff'])->name('home');
        Route::post('logout', [StaffLoginController::class, 'destroy'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Drivers
|--------------------------------------------------------------------------
*/
Route::get('/driver', [LandingController::class, 'driver'])->name('driver.landing');

Route::prefix('driver')->name('driver.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [DriverLoginController::class, 'create'])->name('login');
        Route::post('login', [DriverLoginController::class, 'store'])->middleware('throttle:20,1');

        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    });

    Route::middleware('area:driver')->group(function () {
        Route::get('home', [HomeController::class, 'driver'])->name('home');
        Route::post('logout', [DriverLoginController::class, 'destroy'])->name('logout');
    });
});

// Laravel's reset email links to a route named password.reset.
Route::get('/driver/reset-password/{token}', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.reset');
