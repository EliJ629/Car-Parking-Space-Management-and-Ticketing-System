<?php

use App\Services\GateService;
use App\Services\LongParkingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Bayline scheduled jobs (process document, assumption AS-14)
|--------------------------------------------------------------------------
| They run when the Laravel scheduler runs: `php artisan schedule:work` while
| developing, or one cron entry on a server. The driver and guard screens also
| run them at most once per interval, so the lot stays correct without it.
*/
Artisan::command('bayline:reservations', function (ReservationService $reservations) {
    $expired = $reservations->expireDue();
    $held = $reservations->holdTodaysSlots();
    $this->info("Expired {$expired} reservation(s). Held {$held} slot(s) for today.");
})->purpose('Expire reservations past the grace period and hold today\'s reserved slots');

Artisan::command('bayline:exit-windows', function (GateService $gate) {
    $this->info('Reopened '.$gate->expireWindows().' session(s) whose exit window ended.');
})->purpose('Send paid sessions back to Active when the exit window ended');

Artisan::command('bayline:long-parking', function (LongParkingService $longParking) {
    $this->info('Created '.$longParking->detect().' long-parking incident(s).');
})->purpose('Detect vehicles parked past the long-parking limit');

Schedule::command('bayline:reservations')->everyMinute()->withoutOverlapping();
Schedule::command('bayline:exit-windows')->everyMinute()->withoutOverlapping();
Schedule::command('bayline:long-parking')->everyFiveMinutes()->withoutOverlapping();
