<?php

use App\Http\Controllers\Guard\AlertController;
use App\Http\Controllers\Guard\GateController;
use App\Http\Controllers\Guard\IncidentController;
use App\Http\Controllers\Guard\RecordsController;
use App\Http\Controllers\Guard\SlotsController;
use App\Http\Controllers\Staff\IncidentPhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Guard console
|--------------------------------------------------------------------------
| Gate validation, incident reporting, records and a read only slots view.
| Built for a tablet at the gate and works on a phone too. Every route checks
| the guard role on the server.
*/
Route::prefix('staff/guard')->name('guard.')->middleware(['area:staff', 'role:guard', 'sweep'])->group(function () {
    Route::redirect('/', '/staff/guard/gate');

    Route::get('gate', [GateController::class, 'index'])->name('gate');
    Route::get('gate/feed', [GateController::class, 'feed'])->name('gate.feed')->withoutMiddleware('sweep');
    Route::get('gate/exit/{session}', [GateController::class, 'show'])->name('gate.show');
    Route::post('gate/exit/{session}/approve', [GateController::class, 'approve'])->name('gate.approve');
    Route::post('gate/exit/{session}/mismatch', [GateController::class, 'mismatch'])->name('gate.mismatch');

    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents');
    Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('incidents/long-parked', [IncidentController::class, 'longParked'])->name('incidents.long-parked');
    Route::post('incidents/long-parked', [IncidentController::class, 'storeLongParked'])->name('incidents.long-parked.store');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('incidents/{incident}/notes', [IncidentController::class, 'addNote'])->name('incidents.notes');
    Route::post('incidents/{incident}/status', [IncidentController::class, 'updateStatus'])->name('incidents.status');

    Route::get('records', [RecordsController::class, 'index'])->name('records');
    Route::get('slots', [SlotsController::class, 'index'])->name('slots');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts')->withoutMiddleware('sweep');
    Route::post('alerts/read', [AlertController::class, 'readAll'])->name('alerts.read')->withoutMiddleware('sweep');
});

// Incident photos are private files. Any signed in staff member may open them, nobody else.
Route::middleware('area:staff')->get('staff/incident-photos/{photo}', [IncidentPhotoController::class, 'show'])
    ->name('staff.incident-photos.show');

// Keeps a staff session alive while the idle warning is on screen.
Route::middleware('area:staff')->post('staff/keepalive', fn () => response()->noContent())->name('staff.keepalive');
