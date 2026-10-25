<?php

use App\Http\Controllers\Driver\LoginController as DriverLoginController;
use App\Http\Controllers\Driver\PasswordResetController;
use App\Http\Controllers\Driver\RegisterController;
use App\Http\Controllers\Driver\VerifyEmailController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Manager\AccountController;
use App\Http\Controllers\Manager\ActivityLogController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\IncidentController;
use App\Http\Controllers\Manager\ParkingSlotController;
use App\Http\Controllers\Manager\RatePolicyController;
use App\Http\Controllers\Manager\RecordsController;
use App\Http\Controllers\Manager\SalesReportController;
use App\Http\Controllers\Manager\SettingsController;
use App\Http\Controllers\Staff\LoginController as StaffLoginController;
use App\Http\Controllers\Staff\PasswordChangeController;
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
        Route::get('change-password', [PasswordChangeController::class, 'edit'])->name('password.change');
        Route::put('change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');
    });

});

Route::prefix('staff/manager')->name('manager.')->middleware(['area:staff', 'role:manager'])->group(function () {
    Route::get('/', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::get('slots', [ParkingSlotController::class, 'index'])->name('slots.index');
    Route::get('slots/create', [ParkingSlotController::class, 'create'])->name('slots.create');
    Route::post('slots', [ParkingSlotController::class, 'store'])->name('slots.store');
    Route::get('slots/{slot}/edit', [ParkingSlotController::class, 'edit'])->name('slots.edit');
    Route::patch('slots/{slot}', [ParkingSlotController::class, 'update'])->name('slots.update');
    Route::get('slots/{slot}/service', [ParkingSlotController::class, 'editServiceStatus'])
        ->name('slots.service.edit');
    Route::patch('slots/{slot}/service', [ParkingSlotController::class, 'updateServiceStatus'])
        ->name('slots.service.update');
    Route::delete('slots/{slot}', [ParkingSlotController::class, 'destroy'])->name('slots.destroy');

    Route::get('rates', [RatePolicyController::class, 'index'])->name('rates.index');
    Route::post('rates', [RatePolicyController::class, 'storeRate'])->name('rates.store');
    Route::put('rates/manager-pin', [RatePolicyController::class, 'saveManagerPin'])
        ->middleware('throttle:5,1')
        ->name('rates.manager-pin');
    Route::post('discounts', [RatePolicyController::class, 'storeDiscount'])->name('discounts.store');
    Route::patch('discounts/{discount}', [RatePolicyController::class, 'updateDiscount'])->name('discounts.update');
    Route::delete('discounts/{discount}', [RatePolicyController::class, 'destroyDiscount'])->name('discounts.destroy');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('my-account', [AccountController::class, 'myAccount'])->name('my-account');
    Route::put('my-account', [AccountController::class, 'updateMyAccount'])->name('my-account.update');
    Route::put('my-account/password', [AccountController::class, 'changeOwnPassword'])->name('my-account.password');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('accounts/{account}/status', [AccountController::class, 'toggleStatus'])->name('accounts.status');
    Route::post('accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
    Route::get('drivers', [AccountController::class, 'drivers'])->name('drivers.index');
    Route::get('drivers/{account}', [AccountController::class, 'showDriver'])->name('drivers.show');

    Route::get('records', [RecordsController::class, 'index'])->name('records.index');
    Route::get('records/export', [RecordsController::class, 'export'])->name('records.export');
    Route::get('records/sessions/{session}', [RecordsController::class, 'showSession'])->name('records.sessions.show');

    Route::get('sales', [SalesReportController::class, 'sales'])->name('sales.index');
    Route::get('sales/export', [SalesReportController::class, 'salesExport'])->name('sales.export');
    Route::get('reports', [SalesReportController::class, 'report'])->name('reports.index');
    Route::get('reports/export', [SalesReportController::class, 'reportExport'])->name('reports.export');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('activity/{activityLog}', [ActivityLogController::class, 'show'])->name('activity.show');

    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::patch('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
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

        // The code step after sign up, and after a sign in with an email that was never confirmed.
        Route::get('verify', [VerifyEmailController::class, 'show'])->name('verify');
        Route::post('verify', [VerifyEmailController::class, 'check'])->middleware('throttle:15,1')->name('verify.check');
        Route::post('verify/resend', [VerifyEmailController::class, 'resend'])->middleware('throttle:5,1')->name('verify.resend');
        Route::post('verify/restart', [VerifyEmailController::class, 'restart'])->middleware('throttle:5,1')->name('verify.restart');

        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
    });

    // The link in the email. Signed, so it works only as sent. Open to a signed in driver too (profile changes).
    Route::get('verify-email/{user}/{hash}', [VerifyEmailController::class, 'link'])->middleware(['signed', 'throttle:20,1'])->name('verify.link');

    Route::middleware('area:driver')->group(function () {
        Route::get('home', [HomeController::class, 'driver'])->name('home');
        Route::post('logout', [DriverLoginController::class, 'destroy'])->name('logout');
    });
});

// Laravel's reset email links to a route named password.reset.
Route::get('/driver/reset-password/{token}', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.reset');
