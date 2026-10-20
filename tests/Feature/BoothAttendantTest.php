<?php

namespace Tests\Feature;

use App\Models\ParkingDiscount;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoothAttendantTest extends TestCase
{
    use RefreshDatabase;

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
    }

    private function attendant(): User
    {
        return User::create([
            'name' => 'Marco Villanueva',
            'username' => 'mvillanueva',
            'email' => 'attendant@test.com',
            'phone' => '09175550100',
            'password' => 'Staff123_',
            'role' => User::ROLE_ATTENDANT,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function manager(): User
    {
        return User::create([
            'name' => 'Elena Cruz',
            'username' => 'ecruz',
            'email' => 'manager@test.com',
            'phone' => '09175550200',
            'password' => 'Manager123_',
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function driver(): User
    {
        $driver = User::create([
            'name' => 'Paolo Reyes',
            'username' => 'preyes',
            'email' => 'driver@test.com',
            'phone' => '09175550123',
            'password' => 'Driver123_',
            'role' => User::ROLE_DRIVER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $driver->vehicles()->create([
            'plate_number' => 'GHI7765',
            'vehicle_type' => 'Motorcycle',
            'color' => 'Gray',
        ]);

        return $driver;
    }

    public function test_booth_attendant_can_view_home_dashboard(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        $response = $this->actingAs($attendant)->get(route('attendant.home'));

        $response->assertOk();
        $response->assertSee('Home');
        $response->assertSee('Shift started at');
        $response->assertSee('New walk-in entry');
        $response->assertSee('Check in a reservation');
    }

    public function test_walk_in_lookup_detects_registered_and_guest_vehicles(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $this->driver();

        // 1. Registered vehicle
        $response = $this->actingAs($attendant)->postJson(route('attendant.entry.walk-in.lookup'), [
            'plate' => 'GHI 7765',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'status' => 'registered',
            'vehicle' => [
                'driver_name' => 'Paolo Reyes',
                'type' => 'Motorcycle',
                'color' => 'Gray',
            ],
        ]);

        // 2. Guest vehicle
        $responseGuest = $this->actingAs($attendant)->postJson(route('attendant.entry.walk-in.lookup'), [
            'plate' => 'XYZ 1234',
        ]);

        $responseGuest->assertOk();
        $responseGuest->assertJson([
            'ok' => true,
            'status' => 'guest',
        ]);
    }

    public function test_walk_in_entry_creates_ticket_and_occupies_slot(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        $response = $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), [
            'plate' => 'XYZ 1234',
            'phone' => '09175550199',
            'color' => 'Blue',
            'type' => 'Car',
            'slot_code' => 'A-03',
        ]);

        $response->assertRedirect();

        $session = ParkingSession::query()->where('plate_number', 'XYZ1234')->first();
        $this->assertNotNull($session);
        $this->assertEquals('A-03', $session->slot_code);
        $this->assertEquals(ParkingSession::STATUS_ACTIVE, $session->status);

        $slot = ParkingSlot::query()->where('code', 'A-03')->first();
        $this->assertEquals(ParkingSlot::STATUS_OCCUPIED, $slot->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ticket_created',
            'area' => 'attendant',
        ]);
    }

    public function test_reserved_check_in_validates_and_creates_ticket(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $driver = $this->driver();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();
        $slot->update(['status' => ParkingSlot::STATUS_RESERVED]);

        $reservation = ParkingReservation::create([
            'code' => 'RSV-4F7K29',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'scheduled_at' => now()->addMinutes(10),
            'status' => ParkingReservation::STATUS_BOOKED,
        ]);

        // Lookup reservation
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-4F7K29',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson(['ok' => true, 'status' => 'confirmed']);

        // Check in reservation
        $checkInRes = $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), [
            'reservation_id' => $reservation->id,
            'entered_plate' => 'GHI 7765',
        ]);
        $checkInRes->assertRedirect();

        $reservation->refresh();
        $this->assertEquals(ParkingReservation::STATUS_CHECKED_IN, $reservation->status);

        $session = ParkingSession::query()->where('ticket_number', 'like', 'PK-%')->first();
        $this->assertNotNull($session);
        $this->assertEquals('A-04', $session->slot_code);
        $this->assertEquals(ParkingSlot::STATUS_OCCUPIED, $slot->fresh()->status);
    }

    public function test_exit_payment_calculates_fee_and_records_receipt(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-02')->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        $session = ParkingSession::create([
            'ticket_number' => 'PK-0016',
            'slot_id' => $slot->id,
            'slot_code' => 'A-02',
            'attendant_id' => $attendant->id,
            'plate_number' => 'HJK4471',
            'vehicle_color' => 'Silver',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2)->subMinutes(5),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        $seniorDiscount = ParkingDiscount::query()->where('name', 'Senior citizen')->first();

        // Process payment with senior citizen discount
        $payResponse = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 100.00,
            'discount_id' => $seniorDiscount->id,
            'discount_proof' => 'SC-0048215',
            'discount_note' => 'ID checked at the booth',
        ]);

        $payResponse->assertRedirect();

        $session->refresh();
        $this->assertEquals(ParkingSession::STATUS_PAID, $session->status);
        $this->assertEquals(ParkingSlot::STATUS_PENDING_EXIT, $slot->fresh()->status);

        $payment = ParkingPayment::query()->where('session_id', $session->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(56.00, (float) $payment->total);
        $this->assertEquals(14.00, (float) $payment->discount);
        $this->assertStringStartsWith('RC-', $payment->receipt_number);
    }

    public function test_manager_pin_approval_for_tenant_discount(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $manager = $this->manager();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-0016',
            'slot_code' => 'A-02',
            'plate_number' => 'HJK4471',
            'entered_at' => now()->subHours(1),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        // Wrong PIN
        $wrongRes = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session), [
            'manager_id' => $manager->id,
            'pin' => '00000',
        ]);
        $wrongRes->assertJson(['ok' => false]);

        // Correct PIN
        $correctRes = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session), [
            'manager_id' => $manager->id,
            'pin' => '12345',
        ]);
        $correctRes->assertJson(['ok' => true]);
    }

    public function test_manual_slot_correction_with_reason(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-08')->first();

        $response = $this->actingAs($attendant)->post(route('attendant.slots.correct', $slot), [
            'new_status' => 'out_of_service',
            'reason' => 'Oil spill on the bay, needs cleaning before use',
        ]);

        $response->assertRedirect(route('attendant.slots.index'));
        $slot->refresh();
        $this->assertEquals(ParkingSlot::STATUS_OUT_OF_SERVICE, $slot->status);
        $this->assertEquals('Oil spill on the bay, needs cleaning before use', $slot->out_of_service_reason);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'slot_corrected',
            'subject' => 'A-08',
            'area' => 'attendant',
        ]);
    }

    public function test_manual_slot_correction_blocked_when_slot_has_active_vehicle(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-02')->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        ParkingSession::create([
            'ticket_number' => 'PK-0016',
            'slot_id' => $slot->id,
            'slot_code' => 'A-02',
            'plate_number' => 'HJK4471',
            'entered_at' => now(),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($attendant)->post(route('attendant.slots.correct', $slot), [
            'new_status' => 'available',
            'reason' => 'Attempting to clear occupied slot',
        ]);

        $response->assertSessionHas('error_modal');
        $slot->refresh();
        $this->assertEquals(ParkingSlot::STATUS_OCCUPIED, $slot->status);
    }

    public function test_shift_reconciliation_and_close_shift(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        $response = $this->actingAs($attendant)->get(route('attendant.shift.index'));
        $response->assertOk();
        $response->assertSee('Shift summary');
        $response->assertSee('Opening float');

        // Close shift
        $closeRes = $this->actingAs($attendant)->post(route('attendant.shift.close'), [
            'cash_counted' => 500.00,
            'note' => 'Drawer balanced cleanly',
        ]);

        $closeRes->assertRedirect(route('attendant.shift.index'));
        $closeRes->assertSessionHas('closed_shift');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'shift_closed',
            'area' => 'attendant',
        ]);
    }
}
