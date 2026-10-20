<?php

use App\Http\Controllers\Attendant\AlertController;
use App\Http\Controllers\Attendant\EntryController;
use App\Http\Controllers\Attendant\ExitController;
use App\Http\Controllers\Attendant\HomeController;
use App\Http\Controllers\Attendant\RecordsController;
use App\Http\Controllers\Attendant\ShiftController;
use App\Http\Controllers\Attendant\SlotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Booth Attendant Console
|--------------------------------------------------------------------------
| Check in walk-in and reserved vehicles, issue tickets, calculate fees,
| take cash and print receipts, view and manually correct slots,
| search parking records, and reconcile shifts.
*/
Route::prefix('staff/attendant')->name('attendant.')->middleware(['area:staff', 'role:attendant', 'sweep'])->group(function () {
    Route::redirect('/', '/staff/attendant/home');
    Route::get('home', [HomeController::class, 'index'])->name('home');

    // New Entry (Walk-in & Reserved)
    Route::get('entry', [EntryController::class, 'index'])->name('entry.index');
    Route::get('entry/walk-in', [EntryController::class, 'walkIn'])->name('entry.walk-in');
    Route::post('entry/walk-in/lookup', [EntryController::class, 'lookupPlate'])->name('entry.walk-in.lookup');
    Route::post('entry/walk-in/store', [EntryController::class, 'storeWalkIn'])->name('entry.walk-in.store');
    Route::get('entry/ticket/{session}', [EntryController::class, 'ticket'])->name('entry.ticket');

    Route::get('entry/reserved', [EntryController::class, 'reserved'])->name('entry.reserved');
    Route::post('entry/reserved/lookup', [EntryController::class, 'lookupReservation'])->name('entry.reserved.lookup');
    Route::post('entry/reserved/check-in', [EntryController::class, 'checkInReservation'])->name('entry.reserved.check-in');

    // Exit and Payment
    Route::get('exit', [ExitController::class, 'index'])->name('exit.index');
    Route::get('exit/session/{session}', [ExitController::class, 'show'])->name('exit.show');
    Route::post('exit/session/{session}/verify-pin', [ExitController::class, 'verifyPin'])->name('exit.verify-pin');
    Route::post('exit/session/{session}/add-penalty', [ExitController::class, 'addPenalty'])->name('exit.add-penalty');
    Route::post('exit/session/{session}/pay', [ExitController::class, 'pay'])->name('exit.pay');
    Route::get('exit/receipt/{payment}', [ExitController::class, 'receipt'])->name('exit.receipt');

    // Parking Slots Board
    Route::get('slots', [SlotController::class, 'index'])->name('slots.index');
    Route::post('slots/{slot}/correct', [SlotController::class, 'correctStatus'])->name('slots.correct');

    // Parking Records
    Route::get('records', [RecordsController::class, 'index'])->name('records.index');
    Route::get('records/{session}', [RecordsController::class, 'show'])->name('records.show');

    // Shift Summary & Reconciliation
    Route::get('shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::post('shift/close', [ShiftController::class, 'closeShift'])->name('shift.close');

    // Alerts
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts')->withoutMiddleware('sweep');
    Route::post('alerts/read', [AlertController::class, 'readAll'])->name('alerts.read')->withoutMiddleware('sweep');
});
