<?php

use App\Services\GateService;
use App\Services\IncidentReminders;
use App\Services\LongParkingService;
use App\Services\ReservationReminders;
use App\Services\ReservationService;
use App\Services\TextBee;
use App\Services\TextBeeFailed;
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

Artisan::command('bayline:incident-reminders', function (IncidentReminders $reminders) {
    $this->info('Checked '.$reminders->send().' incident(s) that are unresolved after 24 hours or more.');
})->purpose('Tell the Guard and the Manager about incidents still unresolved after 24 hours, every 24 hours');

Artisan::command('bayline:reservation-reminders', function (ReservationReminders $reminders) {
    if (! $reminders->enabled()) {
        $this->line('SMS reminders are off. Set TEXTBEE_ENABLED=true, TEXTBEE_API_KEY and TEXTBEE_DEVICE_ID in .env.');

        return;
    }

    $r = $reminders->send();
    $this->info("Sent {$r['sent']} reminder(s). Failed {$r['failed']}. Skipped {$r['skipped']} (no usable mobile number).");
})->purpose('Text drivers a reminder shortly before their reservation time (TextBee)');

Artisan::command('bayline:test-sms {to : A Philippine mobile number, 09171234567 or +639171234567}', function (TextBee $textBee) {
    $this->line('TextBee settings in use (the key is never shown):');
    $this->line('  TEXTBEE_ENABLED   '.(config('textbee.enabled') ? 'true' : 'false (reminders are off, this test still sends)'));
    $this->line('  TEXTBEE_API_KEY   '.(filled(config('textbee.api_key')) ? substr((string) config('textbee.api_key'), 0, 4).'... ('.strlen((string) config('textbee.api_key')).' characters)' : '(empty)'));
    $this->line('  TEXTBEE_DEVICE_ID '.(config('textbee.device_id') ?: '(empty)'));
    $this->line('  Reminder time     '.config('textbee.reminder_minutes').' minutes before the reservation');
    $this->newLine();

    try {
        $batch = $textBee->send((string) $this->argument('to'), 'Bayline test message. If you can read this, SMS reminders will reach this phone.');
    } catch (TextBeeFailed $e) {
        $this->error('The message was not sent.');
        $this->line($e->getMessage());

        return 1;
    }

    $this->info('TextBee accepted the message'.($batch !== '' ? " (batch {$batch})" : '').'. It should arrive on the phone in a moment.');
    $this->line('If it does not arrive, open the TextBee app and check that the phone is online and the device is enabled.');

    return 0;
})->purpose('Send one test text through TextBee');

Schedule::command('bayline:reservations')->everyMinute()->withoutOverlapping();
Schedule::command('bayline:exit-windows')->everyMinute()->withoutOverlapping();
Schedule::command('bayline:long-parking')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bayline:incident-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('bayline:reservation-reminders')->everyMinute()->withoutOverlapping();
