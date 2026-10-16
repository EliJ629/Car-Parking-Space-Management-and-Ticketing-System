<?php

use App\Http\Controllers\Driver\AccountController;
use App\Http\Controllers\Driver\BookingController;
use App\Http\Controllers\Driver\NoticeController;
use App\Http\Controllers\Driver\ParkingController;
use App\Http\Controllers\Driver\SessionController;
use App\Http\Controllers\Driver\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver app
|--------------------------------------------------------------------------
| The signed in screens of the driver app: find parking, reserve, bookings,
| session, history, notifications, vehicles and account. A phone-sized web
| interface with a tab bar at the bottom. Sign in, register and the Home route
| itself live in web.php.
*/
Route::prefix('driver')->name('driver.')->middleware(['area:driver', 'driver.active', 'sweep'])->group(function () {
    Route::get('suspended', [AccountController::class, 'suspended'])->name('suspended')->withoutMiddleware('sweep');

    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles');
    Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');

    Route::get('find', [ParkingController::class, 'find'])->name('find');
    Route::get('reserve', [ParkingController::class, 'reserve'])->name('reserve');
    Route::post('reserve', [ParkingController::class, 'store'])->name('reserve.store');

    Route::get('bookings', [BookingController::class, 'index'])->name('bookings');
    Route::get('bookings/{reservation}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{reservation}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('session', [SessionController::class, 'show'])->name('session');
    Route::get('session/locate', [SessionController::class, 'locate'])->name('session.locate');
    Route::get('history', [SessionController::class, 'history'])->name('history');
    Route::get('history/{payment}', [SessionController::class, 'receipt'])->name('receipt');

    Route::get('notifications', [NoticeController::class, 'index'])->name('notices');
    Route::get('notifications/count', [NoticeController::class, 'count'])->name('notices.count')->withoutMiddleware('sweep');
    Route::post('notifications/read-all', [NoticeController::class, 'readAll'])->name('notices.read-all');
    Route::get('notifications/{notice}', [NoticeController::class, 'show'])->name('notices.show');

    Route::get('account', [AccountController::class, 'show'])->name('account');
    Route::get('account/profile', [AccountController::class, 'editProfile'])->name('account.profile');
    Route::put('account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});
