<?php

namespace Tests\Feature;

use App\Models\ExitAuthorization;
use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\GateService;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Exit permissions: issue on payment, expire on time, tell the driver once, never charge twice, release the slot only on exit. */
class ExitAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, string $name): User
    {
        return User::create(['name' => $name, 'username' => strtolower(str_replace(' ', '', $name)), 'email' => strtolower(str_replace(' ', '.', $name)).'@test.com',
            'phone' => '0917'.random_int(1000000, 9999999), 'password' => 'Staff123_', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function active(string $plate = 'HJK4471', string $slotCode = 'A-02', int $hoursAgo = 2, ?User $driver = null, ?string $phone = null): ParkingSession
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        return ParkingSession::create([
            'ticket_number' => 'PK-0016', 'driver_id' => $driver?->id, 'driver_phone' => $phone, 'slot_id' => $slot->id, 'slot_code' => $slotCode,
            'plate_number' => $plate, 'vehicle_color' => 'Silver', 'vehicle_type' => 'Car',
            'entered_at' => now()->subHours($hoursAgo), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);
    }

    private function pay(User $by, ParkingSession $session, float $cash = 500): \Illuminate\Testing\TestResponse
    {
        // Tests move the clock past the idle limit, so each call starts a fresh sign-in.
        $this->flushSession();

        return $this->actingAs($by)->post(route('attendant.exit.pay', $session), ['cash_received' => $cash]);
    }

    public function test_a_payment_issues_one_exit_permission_with_the_managers_window(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active();
        \App\Models\SystemSetting::updateOrCreate(['key' => 'exit_window_minutes'], ['value' => 20, 'group' => 'parking']);

        $this->pay($attendant, $session)->assertRedirect();

        $auth = ExitAuthorization::sole();
        $this->assertSame('EXA-000001', $auth->code);
        $this->assertSame(ExitAuthorization::ACTIVE, $auth->status);
        $this->assertSame(1, $auth->sequence);
        $this->assertSame(20, (int) $auth->issued_at->diffInMinutes($auth->expires_at));
        $payment = ParkingPayment::sole();
        $this->assertEquals((float) $payment->total, (float) $auth->amount);
        $this->assertSame($payment->id, $auth->payment_id);
        $this->assertSame(ParkingSession::STATUS_PAID, $session->fresh()->status);
        $this->assertSame(ParkingSlot::STATUS_PENDING_EXIT, ParkingSlot::where('code', 'A-02')->value('status'));
    }

    public function test_a_double_submit_does_not_charge_or_issue_twice(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active();

        $this->pay($attendant, $session)->assertRedirect();
        $total = (float) ParkingPayment::sole()->total;
        $second = $this->pay($attendant, $session);

        $second->assertRedirect(route('attendant.exit.receipt', ParkingPayment::sole()));
        $this->assertSame(1, ParkingPayment::count());
        $this->assertSame(1, ExitAuthorization::count());
        $this->assertEquals($total, (float) ParkingPayment::sole()->total);
    }

    public function test_a_session_that_already_exited_cannot_be_charged(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active();
        $session->update(['status' => ParkingSession::STATUS_COMPLETED, 'exited_at' => now()]);

        $this->pay($attendant, $session)->assertRedirect(route('attendant.exit.show', $session));
        $this->assertSame(0, ParkingPayment::count());
        $this->assertSame(0, ExitAuthorization::count());
    }

    public function test_short_cash_issues_no_permission(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active();

        $this->pay($attendant, $session, 1)->assertSessionHas('error_modal');
        $this->assertSame(0, ExitAuthorization::count());
        $this->assertSame(ParkingSession::STATUS_ACTIVE, $session->fresh()->status);
    }

    public function test_an_expired_permission_is_closed_queued_out_and_the_slot_stays_held(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $driver = User::create(['name' => 'Paolo Reyes', 'username' => 'preyes', 'email' => 'driver@test.com', 'phone' => '09175550123', 'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE]);
        $session = $this->active(driver: $driver);
        $this->pay($attendant, $session)->assertRedirect();
        $gate = app(GateService::class);

        $this->assertSame(0, $gate->expireWindows(), 'Still inside the window.');
        $this->assertCount(1, $gate->pendingExits());

        $this->travel(16)->minutes();
        $this->assertSame(1, $gate->expireWindows());

        $auth = ExitAuthorization::sole();
        $this->assertSame(ExitAuthorization::EXPIRED, $auth->status);
        $this->assertNotNull($auth->expired_at);
        $this->assertCount(0, $gate->pendingExits(), 'Removed from the Guard queue.');
        $this->assertSame(ParkingSession::STATUS_ACTIVE, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->exit_window_expired_at);
        $this->assertSame(ParkingSlot::STATUS_OCCUPIED, ParkingSlot::where('code', 'A-02')->value('status'), 'Not released: the vehicle has not left.');
        $this->assertSame(1, ParkingPayment::count(), 'History is kept.');
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('group_key', "exit-expired-{$auth->id}")->count());
        $this->assertSame('app', $auth->fresh()->notify_channel);
    }

    public function test_running_the_expiry_job_again_changes_nothing(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $driver = User::create(['name' => 'Paolo Reyes', 'username' => 'preyes', 'email' => 'driver@test.com', 'phone' => '09175550123', 'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE]);
        $session = $this->active(driver: $driver);
        $this->pay($attendant, $session);
        $this->travel(16)->minutes();
        $gate = app(GateService::class);

        $this->assertSame(1, $gate->expireWindows());
        $this->assertSame(0, $gate->expireWindows());
        $this->assertSame(0, $gate->expireOne($session->id) ? 1 : 0);
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'exit_window_ended')->count());
    }

    public function test_a_walk_in_gets_one_text_message_when_the_permission_expires(): void
    {
        config(['textbee.enabled' => true, 'textbee.api_key' => 'key-123456', 'textbee.device_id' => 'device-1', 'textbee.base_url' => 'https://api.textbee.dev/api/v1']);
        Http::swap(new Factory);
        Http::fake(['api.textbee.dev/*' => Http::response(['data' => ['success' => true, 'smsBatchId' => 'b-1']], 200)]);

        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active(plate: 'QRS7788', phone: '09171234567');
        $this->pay($attendant, $session);
        $this->travel(16)->minutes();
        $gate = app(GateService::class);

        $gate->expireWindows();
        $gate->expireWindows();
        $this->assertSame(1, Http::recorded(fn ($request) => str_contains((string) $request['message'], 'exit window'))->count());
        $auth = ExitAuthorization::sole();
        $this->assertSame('sms', $auth->notify_channel);
        $this->assertNotNull($auth->notified_at);
    }

    public function test_a_failed_text_is_recorded_and_not_sent_again(): void
    {
        config(['textbee.enabled' => true, 'textbee.api_key' => 'key-123456', 'textbee.device_id' => 'device-1', 'textbee.base_url' => 'https://api.textbee.dev/api/v1']);
        Http::swap(new Factory);
        Http::fake(['api.textbee.dev/*' => Http::response(['error' => 'x'], 400)]);

        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active(plate: 'QRS7788', phone: '09171234567');
        $this->pay($attendant, $session);
        $this->travel(16)->minutes();
        $gate = app(GateService::class);

        $this->assertSame(1, $gate->expireWindows());
        $this->assertSame(0, $gate->expireWindows());
        $this->assertSame(1, Http::recorded(fn ($request) => str_contains((string) $request['message'], 'exit window'))->count());
        $auth = ExitAuthorization::sole();
        $this->assertNull($auth->notified_at);
        $this->assertStringContainsString('not sent', $auth->notify_result);
        $this->assertSame(ExitAuthorization::EXPIRED, $auth->status, 'A failed text never blocks the expiry.');
    }

    public function test_a_new_permission_has_a_new_code_and_only_the_difference_is_charged(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active(hoursAgo: 1);
        $this->pay($attendant, $session);
        $first = (float) ParkingPayment::sole()->total;
        $this->assertGreaterThan(0, $first);

        // The window ends, the car stays another two hours, then the driver pays again.
        $this->travel(16)->minutes();
        app(GateService::class)->expireWindows();
        $this->travel(2)->hours();
        $this->pay($attendant, $session->fresh());

        $codes = ExitAuthorization::orderBy('sequence')->pluck('code', 'sequence')->all();
        $this->assertSame([1 => 'EXA-000001', 2 => 'EXA-000002'], $codes);
        $this->assertSame([ExitAuthorization::EXPIRED, ExitAuthorization::ACTIVE], ExitAuthorization::orderBy('sequence')->pluck('status')->all());
        $this->assertSame(1, ParkingPayment::count());

        $rows = ExitAuthorization::orderBy('sequence')->get();
        $secondAmount = (float) $rows[1]->amount;
        $this->assertGreaterThan(0, $secondAmount);
        $this->assertEquals(round($first + $secondAmount, 2), (float) $rows[1]->cumulative_paid);
        // The bill for the whole stay is paid exactly once: first + second equals the fee for the full stay.
        $bill = app(\App\Services\FeeCalculator::class)->estimate($session->fresh(), now())['parking_charge'];
        $this->assertEquals((float) $bill, round($first + $secondAmount, 2));
        $this->assertEquals($secondAmount, (float) ParkingPayment::sole()->total, 'The payment row holds the latest payment, so reports count each payment once.');
    }

    public function test_paying_again_with_nothing_more_owed_charges_nothing_extra(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $session = $this->active(hoursAgo: 0);
        $this->pay($attendant, $session);
        $this->travel(16)->minutes();
        app(GateService::class)->expireWindows();
        $this->pay($attendant, $session->fresh());

        $rows = ExitAuthorization::orderBy('sequence')->get();
        $this->assertEquals(0.0, (float) $rows[1]->amount, 'Same billable hour: nothing more is owed, so nothing more is charged.');
        $this->assertEquals((float) $rows[0]->amount, (float) $rows[1]->cumulative_paid);
        $this->assertSame(ExitAuthorization::ACTIVE, $rows[1]->status);
    }

    public function test_the_guard_confirming_the_exit_uses_the_permission_and_only_then_releases_the_slot(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');
        $session = $this->active();
        $this->pay($attendant, $session);
        $this->assertSame(ParkingSlot::STATUS_PENDING_EXIT, ParkingSlot::where('code', 'A-02')->value('status'));

        $this->flushSession();
        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]])->assertRedirect();

        $this->assertSame(ParkingSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertSame(ParkingSlot::STATUS_AVAILABLE, ParkingSlot::where('code', 'A-02')->value('status'));
        $auth = ExitAuthorization::sole();
        $this->assertSame(ExitAuthorization::USED, $auth->status);
        $this->assertSame($guard->id, $auth->used_by);

        // The used permission cannot be used again, and the expiry job leaves it alone.
        $this->travel(30)->minutes();
        $this->assertSame(0, app(GateService::class)->expireWindows());
        $this->assertSame(ExitAuthorization::USED, $auth->fresh()->status);
    }

    public function test_the_guard_cannot_confirm_an_exit_after_the_permission_ended(): void
    {
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');
        $session = $this->active();
        $this->pay($attendant, $session);
        $this->travel(16)->minutes();

        $this->flushSession();
        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]]);

        $this->assertNotSame(ParkingSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertSame(ExitAuthorization::EXPIRED, ExitAuthorization::sole()->status);
        $this->assertSame(ParkingSlot::STATUS_OCCUPIED, ParkingSlot::where('code', 'A-02')->value('status'));
    }
}
