<?php

namespace Tests\Feature;

use App\Models\ParkingReservation;
use App\Models\User;
use App\Services\ReservationReminders;
use App\Services\TextBee;
use App\Services\TextBeeFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Reservation reminders by text message through TextBee. */
class SmsReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'textbee.enabled' => true, 'textbee.api_key' => 'key-123456', 'textbee.device_id' => 'device-1',
            'textbee.base_url' => 'https://api.textbee.dev/api/v1', 'textbee.reminder_minutes' => 30,
        ]);
    }

    private function fakeTextBee(int $status = 200): void
    {
        // A fresh client each time, so a second fake replaces the first one.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.textbee.dev/*' => Http::response(
            $status === 200 ? ['data' => ['success' => true, 'message' => 'SMS added to queue for processing', 'smsBatchId' => 'batch-1', 'recipientCount' => 1]] : ['error' => 'x'],
            $status,
        )]);
    }

    private function driver(string $phone = '09175550142'): User
    {
        return User::create([
            'name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'phone' => $phone,
            'password' => 'Driver123_!', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function reservation(User $driver, array $override = []): ParkingReservation
    {
        return ParkingReservation::create($override + [
            'code' => 'RSV-'.strtoupper(substr(md5((string) microtime(true).random_int(0, 9999)), 0, 6)), 'driver_id' => $driver->id,
            'slot_code' => 'A-06', 'plate_number' => 'DEF2208', 'scheduled_at' => now()->addMinutes(20),
            'status' => ParkingReservation::STATUS_BOOKED, 'approval_status' => ParkingReservation::APPROVAL_APPROVED,
        ]);
    }

    public function test_numbers_are_written_the_way_textbee_needs(): void
    {
        $this->assertSame('+639171234567', TextBee::international('09171234567'));
        $this->assertSame('+639171234567', TextBee::international('+639171234567'));
        $this->assertNull(TextBee::international('0917 123 4567'));
        $this->assertNull(TextBee::international('12345'));
        $this->assertNull(TextBee::international(null));
    }

    public function test_the_request_to_textbee_has_the_key_the_device_the_number_and_the_text(): void
    {
        $this->fakeTextBee();

        $batch = app(TextBee::class)->send('09171234567', 'Hello');

        $this->assertSame('batch-1', $batch);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.textbee.dev/api/v1/gateway/send-sms'
                && $request->hasHeader('x-api-key', 'key-123456')
                && $request['deviceId'] === 'device-1'
                && $request['recipients'] === ['+639171234567']
                && $request['message'] === 'Hello';
        });
    }

    public function test_textbee_errors_say_what_to_check(): void
    {
        foreach ([401 => 'API key', 404 => 'device', 400 => 'phone online', 429 => 'plan limit'] as $status => $hint) {
            $this->fakeTextBee($status);

            try {
                app(TextBee::class)->send('09171234567', 'Hello');
                $this->fail("{$status} was accepted");
            } catch (TextBeeFailed $e) {
                $this->assertStringContainsString($hint, $e->getMessage(), (string) $status);
            }
        }
    }

    public function test_it_refuses_when_not_set_up_or_the_number_is_bad(): void
    {
        config(['textbee.api_key' => null]);
        $this->expectException(TextBeeFailed::class);
        app(TextBee::class)->send('09171234567', 'Hello');
    }

    public function test_a_reservation_close_to_its_time_gets_one_reminder(): void
    {
        $this->fakeTextBee();
        $reservation = $this->reservation($this->driver());

        $first = app(ReservationReminders::class)->send();
        $second = app(ReservationReminders::class)->send();

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 0], $first);
        $this->assertSame(['sent' => 0, 'failed' => 0, 'skipped' => 0], $second);
        $this->assertNotNull($reservation->fresh()->reminder_sent_at);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reservation_reminder_sent', 'subject' => $reservation->code]);
    }

    public function test_the_message_names_the_bay_the_time_and_stays_in_one_text(): void
    {
        $reservation = $this->reservation($this->driver(), ['scheduled_at' => now()->addMinutes(25)]);
        $message = app(ReservationReminders::class)->message($reservation->fresh(['slot', 'driver']));

        $this->assertStringContainsString('A-06', $message);
        $this->assertStringContainsString($reservation->code, $message);
        $this->assertStringContainsString(\App\Support\Lot::local($reservation->scheduled_at)->format('g:i A'), $message);
        $this->assertLessThanOrEqual(160, strlen($message));
        $this->assertSame(strlen($message), mb_strlen($message), 'plain characters only');
    }

    public function test_only_open_approved_upcoming_reservations_inside_the_window_are_reminded(): void
    {
        $this->fakeTextBee();
        $driver = $this->driver();

        $this->reservation($driver, ['scheduled_at' => now()->addHours(3)]);
        $this->reservation($driver, ['approval_status' => ParkingReservation::APPROVAL_PENDING]);
        $this->reservation($driver, ['scheduled_at' => now()->subMinutes(5)]);
        $this->reservation($driver, ['status' => ParkingReservation::STATUS_CANCELED]);
        $this->reservation($driver, ['status' => ParkingReservation::STATUS_CHECKED_IN]);

        $this->assertSame(0, app(ReservationReminders::class)->send()['sent']);
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_while_reminders_are_off_or_not_set_up(): void
    {
        $this->fakeTextBee();
        $this->reservation($this->driver());

        config(['textbee.enabled' => false]);
        $this->assertSame(0, app(ReservationReminders::class)->send()['sent']);

        config(['textbee.enabled' => true, 'textbee.device_id' => null]);
        $this->assertSame(0, app(ReservationReminders::class)->send()['sent']);

        Http::assertNothingSent();
        $this->assertNull(ParkingReservation::first()->reminder_sent_at);
    }

    public function test_a_driver_without_a_usable_number_is_skipped_once(): void
    {
        $this->fakeTextBee();
        $reservation = $this->reservation($this->driver('555'));

        $this->assertSame(1, app(ReservationReminders::class)->send()['skipped']);
        $this->assertSame(0, app(ReservationReminders::class)->send()['skipped']);
        Http::assertNothingSent();
        $this->assertNotNull($reservation->fresh()->reminder_sent_at);
    }

    public function test_a_failed_send_is_tried_again_on_the_next_run(): void
    {
        $this->fakeTextBee(401);
        $reservation = $this->reservation($this->driver());

        $this->assertSame(1, app(ReservationReminders::class)->send()['failed']);
        $this->assertNull($reservation->fresh()->reminder_sent_at);

        $this->fakeTextBee();
        $this->assertSame(1, app(ReservationReminders::class)->send()['sent']);
        $this->assertNotNull($reservation->fresh()->reminder_sent_at);
    }

    public function test_the_commands_report_clearly(): void
    {
        $this->fakeTextBee();
        config(['textbee.enabled' => false]);
        $this->artisan('bayline:reservation-reminders')->expectsOutputToContain('SMS reminders are off')->assertSuccessful();

        config(['textbee.enabled' => true]);
        $this->reservation($this->driver());
        $this->artisan('bayline:reservation-reminders')->expectsOutputToContain('Sent 1 reminder(s)')->assertSuccessful();

        $this->artisan('bayline:test-sms', ['to' => '09171234567'])
            ->expectsOutputToContain('key-...')
            ->doesntExpectOutputToContain('key-123456')
            ->expectsOutputToContain('TextBee accepted the message')
            ->assertSuccessful();

        $this->fakeTextBee(401);
        $this->artisan('bayline:test-sms', ['to' => '09171234567'])->expectsOutputToContain('refused the API key')->assertFailed();
    }

    public function test_the_scheduler_runs_the_reminders_every_minute(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'bayline:reservation-reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
    }
}
