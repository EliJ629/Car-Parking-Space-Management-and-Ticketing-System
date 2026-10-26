<?php

namespace Tests\Feature;

use App\Models\ParkingDiscount;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use App\Support\PlateNumber;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        // Lookup reservation with standard code, without dash, and without RSV prefix
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-4F7K29',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson(['ok' => true, 'status' => 'confirmed']);

        $lookupResNoDash = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV4F7K29',
        ]);
        $lookupResNoDash->assertOk();
        $lookupResNoDash->assertJson(['ok' => true, 'status' => 'confirmed']);

        $lookupResRaw = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => '4F7K29',
        ]);
        $lookupResRaw->assertOk();
        $lookupResRaw->assertJson(['ok' => true, 'status' => 'confirmed']);

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

    public function test_discount_applies_to_total_including_lost_ticket_and_overstay_penalties(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-0099',
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'ABC1234',
            'vehicle_color' => 'Red',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2)->subMinutes(5),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        $seniorDiscount = ParkingDiscount::query()->where('name', 'Senior citizen')->first();
        // Parking charge = 30 + 40 = 70.
        // Penalties: lost_ticket (100) + overstay (150) = 250.
        // Total before discount = 70 + 250 = 320.
        // Discount 20% on 320 = 64.00.
        // Final total = 320 - 64 = 256.00.

        $payResponse = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 300.00,
            'discount_id' => $seniorDiscount->id,
            'discount_proof' => 'SC-999999',
            'discount_note' => 'Senior ID verified with penalties',
            'lost_ticket' => '1',
            'overstay' => '1',
        ]);

        $payResponse->assertRedirect();

        $payment = ParkingPayment::query()->where('session_id', $session->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(250.00, (float) $payment->penalty);
        $this->assertEquals(64.00, (float) $payment->discount);
        $this->assertEquals(256.00, (float) $payment->total);
        $this->assertEquals(44.00, (float) ($payment->receipt_snapshot['change_given'] ?? 0));
    }

    public function test_penalty_can_be_removed_before_payment(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-05')->first();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-0100',
            'slot_id' => $slot->id,
            'slot_code' => 'A-05',
            'plate_number' => 'NKL5555',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2)->subMinutes(5),
            'status' => ParkingSession::STATUS_ACTIVE,
            'lost_ticket' => false,
        ]);

        // Attendant undid/removed penalty before confirming payment: lost_ticket = '0', overstay = '0'
        $payResponse = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 100.00,
            'lost_ticket' => '0',
            'overstay' => '0',
        ]);

        $payResponse->assertRedirect();

        $payment = ParkingPayment::query()->where('session_id', $session->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(0.00, (float) $payment->penalty);
        $this->assertEquals(70.00, (float) $payment->total);
        $this->assertEquals(30.00, (float) ($payment->receipt_snapshot['change_given'] ?? 0));
        $this->assertFalse($session->fresh()->lost_ticket);
    }

    public function test_senior_and_pwd_discount_id_regex_validation(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $slot = ParkingSlot::query()->where('code', 'A-06')->first();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-0101',
            'slot_id' => $slot->id,
            'slot_code' => 'A-06',
            'plate_number' => 'SEN8888',
            'entered_at' => now()->subHours(1),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        $seniorDiscount = ParkingDiscount::query()->where('name', 'Senior citizen')->firstOrFail();
        $pwdDiscount = ParkingDiscount::query()->where('name', 'PWD')->firstOrFail();

        // Invalid Senior ID (too short / bogus)
        $invalidSenior = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 100.00,
            'discount_id' => $seniorDiscount->id,
            'discount_proof' => '123',
        ]);
        $invalidSenior->assertSessionHasErrors(['discount_proof']);

        // Invalid PWD ID (dummy string)
        $invalidPwd = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 100.00,
            'discount_id' => $pwdDiscount->id,
            'discount_proof' => 'test',
        ]);
        $invalidPwd->assertSessionHasErrors(['discount_proof']);

        // Valid PWD ID succeeds
        $validPwd = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 100.00,
            'discount_id' => $pwdDiscount->id,
            'discount_proof' => 'PWD-2024-00142',
        ]);
        $validPwd->assertRedirect();
        $this->assertDatabaseHas('parking_payments', [
            'session_id' => $session->id,
            'discount' => 6.00,
        ]);
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

        $manager->forceFill(['manager_pin_hash' => Hash::make('987654')])->save();

        // Wrong PIN attempt 1 (2 left)
        $wrong1 = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session), [
            'manager_id' => $manager->id,
            'pin' => '000000',
        ]);
        $wrong1->assertJson(['ok' => false, 'attempts_left' => 2]);

        // Wrong PIN attempt 2 (1 left)
        $wrong2 = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session), [
            'manager_id' => $manager->id,
            'pin' => '000000',
        ]);
        $wrong2->assertJson(['ok' => false, 'attempts_left' => 1]);

        // Wrong PIN attempt 3 (blocked)
        $wrong3 = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session), [
            'manager_id' => $manager->id,
            'pin' => '000000',
        ]);
        $wrong3->assertJson(['ok' => false, 'blocked' => true, 'attempts_left' => 0]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'manager_pin_failed']);

        // Session 2 for testing successful PIN verification
        $session2 = ParkingSession::create([
            'ticket_number' => 'PK-0017',
            'slot_code' => 'A-03',
            'plate_number' => 'XYZ9999',
            'entered_at' => now()->subHours(1),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        // There is no demo or master PIN: the old shipped PINs and the account password are refused, and a manager
        // who has not set a PIN cannot approve anything.
        foreach (['741963', '123456'] as $demo) {
            $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session2), ['manager_id' => $manager->id, 'pin' => $demo])
                ->assertJson(['ok' => false]);
        }

        // Manager's configured 6-digit PIN
        $manager->forceFill(['manager_pin_hash' => Hash::make('987654')])->save();
        $configuredRes = $this->actingAs($attendant)->postJson(route('attendant.exit.verify-pin', $session2), [
            'manager_id' => $manager->id,
            'pin' => '987654',
        ]);
        $configuredRes->assertJson(['ok' => true]);
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

    public function test_all_philippine_plate_formats_and_protocol_plates_are_supported(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        // Typed form => how it is shown. The same list drives PlateNumberTest.
        $validPlates = [
            'ABC 1234' => 'ABC 1234',     // current standard four-wheel (LLL DDDD)
            'asd1234' => 'ASD 1234',      // typed without a space, in lower case
            'a123bc' => 'A 123 BC',       // current standard motorcycle (L DDD LL)
            '123 abc' => '123 ABC',       // current standard motorcycle (DDD LLL)
            'ABC 123' => 'ABC 123',       // legacy four-wheel (LLL DDD)
            'AB 1234' => 'AB 1234',       // legacy motorcycle or tricycle (LL DDDD)
            '1234 AB' => '1234 AB',       // legacy motorcycle or tricycle (DDDD LL)
            'A-1234-BC' => 'A 1234 BC',   // vintage (L DDDD LL)
            '123-ABC' => '123 ABC',       // vintage (DDD LLL)
            'AB 123' => 'AB 123',         // pre-1981 (LL DDD)
            'A 12345' => 'A 12345',       // pre-1981 (L DDDDD)
            '12-3456' => '12-3456',       // pre-1981 (DD-DDDD)
            'ZEN 88' => 'ZEN 88',         // vanity (LLL DD)
            '1234567' => '123 4567',      // foreign and diplomatic (DDD DDDD)
            '123 456' => '123 456',       // foreign and diplomatic (DDD DDD)
            'T 1234' => 'T 1234',         // temporary transit, cars (L DDDD)
            '1234-12345678901' => '1234-12345678901', // temporary transit, motorcycles
            '1' => '1',                   // government protocol: President
            '7' => '7',                   // government protocol
            '17' => '17',                 // government protocol
        ];

        foreach ($validPlates as $rawPlate => $expectedDisplay) {
            $norm = Vehicle::normalizePlate($rawPlate);
            $this->assertTrue(PlateNumber::isValid($rawPlate), "Failed on {$rawPlate}");
            $this->assertMatchesRegularExpression(Vehicle::PLATE_PATTERN, $norm, "Failed on {$rawPlate}");
            $this->assertSame($expectedDisplay, Lot::plate($norm), "Formatting mismatch on {$rawPlate}");

            // Can lookup via walk-in API
            $res = $this->actingAs($attendant)->postJson(route('attendant.entry.walk-in.lookup'), [
                'plate' => $rawPlate,
            ]);
            $res->assertOk();
            $res->assertJson(['ok' => true, 'status' => 'guest']);
        }

        // Test president can park!
        $presEntry = $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), [
            'plate' => '1',
            'phone' => '09175550199',
            'color' => 'Black',
            'type' => 'SUV',
            'slot_code' => 'A-01',
        ]);
        $presEntry->assertRedirect();

        $presSession = ParkingSession::query()->where('plate_number', '1')->first();
        $this->assertNotNull($presSession);
        $this->assertSame('1', $presSession->plate_number);
        $this->assertSame('1', Lot::plate($presSession->plate_number));

        // Invalid plate rejection. Government plates stop at 17; AB 12345 and PANGULO are not on the current list.
        $invalidPlates = ['AB12', 'ABCD123', 'A12', '0', '18', '99', 'ZZ', 'AB C 1234', 'ABC 12 34', 'ABC@1234', 'AB 12345', 'PANGULO', '1234'];
        foreach ($invalidPlates as $badPlate) {
            $this->assertFalse(PlateNumber::isValid($badPlate), "Should reject {$badPlate}");

            $res = $this->actingAs($attendant)->postJson(route('attendant.entry.walk-in.lookup'), [
                'plate' => $badPlate,
            ]);
            $res->assertStatus(422);
        }
    }

    public function test_walk_in_entry_phone_validation(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        // 1. Invalid phone numbers (non-Philippine or wrong digits)
        foreach (['12345', 'abcdefghijk', '08123456789', '0917-123', '091712345678'] as $badPhone) {
            $res = $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), [
                'plate' => 'ABC 1234',
                'phone' => $badPhone,
                'color' => 'White',
                'type' => 'Car',
                'slot_code' => 'A-01',
            ]);
            $res->assertSessionHasErrors('phone');
        }

        // 2. Valid 11-digit mobile starting with 09
        $goodRes = $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), [
            'plate' => 'ABC 1234',
            'phone' => '09175550199',
            'color' => 'White',
            'type' => 'Car',
            'slot_code' => 'A-01',
        ]);
        $goodRes->assertRedirect();
        $this->assertDatabaseHas('parking_sessions', [
            'plate_number' => 'ABC1234',
        ]);
    }

    public function test_close_shift_requires_note_when_difference_exists(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        // Counted is 450, expected is 500 (short by 50) without note -> rejected with error modal
        $resWithoutNote = $this->actingAs($attendant)->post(route('attendant.shift.close'), [
            'cash_counted' => 450.00,
            'note' => '',
        ]);
        $resWithoutNote->assertSessionHas('error_modal');
        $modal = session('error_modal');
        $this->assertSame('close_shift_note_required', $modal['type']);
        $this->assertEquals(-50.0, $modal['difference']);

        // Counted is 450, with explanation note -> accepted
        $resWithNote = $this->actingAs($attendant)->post(route('attendant.shift.close'), [
            'cash_counted' => 450.00,
            'note' => 'Gave ₱50 change advance for customer bills',
        ]);
        $resWithNote->assertRedirect(route('attendant.shift.index'));
        $resWithNote->assertSessionHas('closed_shift');
    }

    public function test_reserved_check_in_rejects_canceled_reservation(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $driver = $this->driver();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();

        $reservation = ParkingReservation::create([
            'code' => 'RSV-CANC01',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'scheduled_at' => now()->addMinutes(10),
            'status' => ParkingReservation::STATUS_CANCELED,
            'canceled_at' => now()->subMinutes(5),
        ]);

        // 1. Lookup returns canceled status
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-CANC01',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson([
            'ok' => false,
            'status' => 'canceled',
            'reservation' => [
                'code' => 'RSV-CANC01',
                'status' => 'CANCELED',
            ],
        ]);

        // 2. Direct check-in attempt is rejected with reservation_not_bookable modal
        $checkInRes = $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), [
            'reservation_id' => $reservation->id,
            'entered_plate' => 'GHI 7765',
        ]);
        $checkInRes->assertSessionHas('error_modal');
        $modal = session('error_modal');
        $this->assertSame('reservation_not_bookable', $modal['type']);
        $this->assertSame('RSV-CANC01', $modal['code']);

        // Assert no parking session was created
        $this->assertDatabaseMissing('parking_sessions', [
            'plate_number' => 'GHI7765',
        ]);
    }

    public function test_reserved_check_in_rejects_completed_or_exited_reservation(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $driver = $this->driver();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();

        $reservation = ParkingReservation::create([
            'code' => 'RSV-EXIT01',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'scheduled_at' => now()->subHours(2),
            'status' => ParkingReservation::STATUS_COMPLETED,
        ]);

        // 1. Lookup returns already_used with COMPLETED (EXITED) status
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-EXIT01',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson([
            'ok' => false,
            'status' => 'already_used',
            'reservation' => [
                'code' => 'RSV-EXIT01',
                'status' => 'COMPLETED (EXITED)',
            ],
        ]);

        // 2. Direct check-in attempt is rejected
        $checkInRes = $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), [
            'reservation_id' => $reservation->id,
            'entered_plate' => 'GHI 7765',
        ]);
        $checkInRes->assertSessionHas('error_modal');
        $modal = session('error_modal');
        $this->assertSame('reservation_not_bookable', $modal['type']);

        $reservation->refresh();
        $this->assertSame(ParkingReservation::STATUS_COMPLETED, $reservation->status);
    }

    public function test_reserved_check_in_rejects_already_checked_in_reservation(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $driver = $this->driver();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();

        $reservation = ParkingReservation::create([
            'code' => 'RSV-IN0001',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'scheduled_at' => now()->subMinutes(15),
            'status' => ParkingReservation::STATUS_CHECKED_IN,
        ]);

        // 1. Lookup returns already_used
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-IN0001',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson([
            'ok' => false,
            'status' => 'already_used',
            'reservation' => [
                'code' => 'RSV-IN0001',
                'status' => 'ALREADY CHECKED IN',
            ],
        ]);

        // 2. Direct check-in attempt is rejected
        $checkInRes = $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), [
            'reservation_id' => $reservation->id,
            'entered_plate' => 'GHI 7765',
        ]);
        $checkInRes->assertSessionHas('error_modal');
        $this->assertSame('reservation_not_bookable', session('error_modal')['type']);
    }

    public function test_exit_checkout_has_zero_penalty_by_default_and_hides_remove_penalty(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-9991',
            'slot_code' => 'A-01',
            'plate_number' => 'ABC1234',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2),
            'status' => ParkingSession::STATUS_ACTIVE,
            'exit_window_expired_at' => now()->subMinutes(10), // Had prior expired window
        ]);

        $res = $this->actingAs($attendant)->get(route('attendant.exit.show', $session));
        $res->assertOk();

        // Must show Add penalty and hide Remove penalty
        $res->assertSee('Add penalty');
        $res->assertSee('style="display:none;"', false);
        $res->assertSee('Penalties');
        $res->assertSee('None');
    }

    public function test_exit_payment_reuses_existing_receipt_number_without_unique_constraint_violation(): void
    {
        $this->lot();
        $attendant = $this->attendant();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-9992',
            'slot_code' => 'A-01',
            'plate_number' => 'ABC1234',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2),
            'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        // Pre-existing payment for this session
        $existingPayment = ParkingPayment::create([
            'receipt_number' => 'RC-0003',
            'session_id' => $session->id,
            'received_by' => $attendant->id,
            'base_fee' => 30,
            'additional_fee' => 0,
            'penalty' => 0,
            'discount' => 0,
            'total' => 30,
            'method' => 'cash',
            'paid_at' => now()->subHour(),
        ]);

        // Another payment exists with RC-0006 on another session
        $session2 = ParkingSession::create([
            'ticket_number' => 'PK-9993',
            'slot_code' => 'A-02',
            'plate_number' => 'DEF5678',
            'vehicle_color' => 'Black',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(3),
            'status' => ParkingSession::STATUS_PAID,
        ]);

        ParkingPayment::create([
            'receipt_number' => 'RC-0006',
            'session_id' => $session2->id,
            'received_by' => $attendant->id,
            'base_fee' => 50,
            'additional_fee' => 0,
            'penalty' => 0,
            'discount' => 0,
            'total' => 50,
            'method' => 'cash',
            'paid_at' => now()->subHour(),
        ]);

        // Take payment for session - should update existing payment without generating RC-0006 or crashing
        $payRes = $this->actingAs($attendant)->post(route('attendant.exit.pay', $session), [
            'cash_received' => 200,
        ]);

        $payRes->assertRedirect(route('attendant.exit.receipt', $existingPayment));
        $existingPayment->refresh();
        $this->assertSame('RC-0003', $existingPayment->receipt_number);
    }

    public function test_reserved_check_in_rejects_pending_exit_reservation(): void
    {
        $this->lot();
        $attendant = $this->attendant();
        $driver = $this->driver();
        $slot = ParkingSlot::query()->where('code', 'A-04')->first();

        $session = ParkingSession::create([
            'ticket_number' => 'PK-8888',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'vehicle_color' => 'Gray',
            'vehicle_type' => 'Motorcycle',
            'entered_at' => now()->subHour(),
            'status' => ParkingSession::STATUS_PAID,
        ]);

        $reservation = ParkingReservation::create([
            'code' => 'RSV-PEND01',
            'driver_id' => $driver->id,
            'slot_id' => $slot->id,
            'slot_code' => 'A-04',
            'plate_number' => 'GHI7765',
            'scheduled_at' => now()->subHours(2),
            'status' => ParkingReservation::STATUS_CHECKED_IN,
            'session_id' => $session->id,
        ]);

        // 1. Lookup returns already_used with PAID (PENDING EXIT)
        $lookupRes = $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), [
            'code' => 'RSV-PEND01',
        ]);
        $lookupRes->assertOk();
        $lookupRes->assertJson([
            'ok' => false,
            'status' => 'already_used',
            'reservation' => [
                'code' => 'RSV-PEND01',
                'status' => 'PAID (PENDING EXIT)',
                'is_pending_exit' => true,
            ],
        ]);

        // 2. Direct check-in attempt is rejected
        $checkInRes = $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), [
            'reservation_id' => $reservation->id,
            'entered_plate' => 'GHI 7765',
        ]);
        $checkInRes->assertSessionHas('error_modal');
        $modal = session('error_modal');
        $this->assertSame('reservation_not_bookable', $modal['type']);
        $this->assertSame('PAID (PENDING EXIT)', $modal['status']);
    }

    public function test_shift_summary_accurately_reflects_cash_discounts_and_penalties_even_after_relogin(): void
    {
        // The sessions start 2 and 3 hours ago. Fix the clock at 2 PM Manila time so they stay inside the same day.
        $this->travelTo(Lot::now()->setTime(14, 0));
        $this->lot();
        $attendant = $this->attendant();

        $session1 = ParkingSession::create([
            'ticket_number' => 'PK-7001',
            'slot_code' => 'A-01',
            'plate_number' => 'ABC1234',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Car',
            'attendant_id' => $attendant->id,
            'entered_at' => now()->subHours(3),
            'status' => ParkingSession::STATUS_PAID,
        ]);

        $session2 = ParkingSession::create([
            'ticket_number' => 'PK-7002',
            'slot_code' => 'A-02',
            'plate_number' => 'XYZ9876',
            'vehicle_color' => 'Black',
            'vehicle_type' => 'SUV',
            'attendant_id' => $attendant->id,
            'entered_at' => now()->subHours(2),
            'status' => ParkingSession::STATUS_PAID,
            'lost_ticket' => true,
        ]);

        // Payment 1: with Senior discount
        ParkingPayment::create([
            'receipt_number' => 'RC-7001',
            'session_id' => $session1->id,
            'received_by' => $attendant->id,
            'base_fee' => 30,
            'additional_fee' => 20,
            'penalty' => 0,
            'discount' => 10,
            'total' => 40,
            'method' => 'cash',
            'receipt_snapshot' => [
                'discount_name' => 'Senior citizen',
                'discount_amount' => 10,
            ],
            'paid_at' => now()->subMinutes(90),
        ]);

        // Payment 2: with Lost ticket penalty
        ParkingPayment::create([
            'receipt_number' => 'RC-7002',
            'session_id' => $session2->id,
            'received_by' => $attendant->id,
            'base_fee' => 30,
            'additional_fee' => 40,
            'penalty' => 100,
            'discount' => 0,
            'total' => 170,
            'method' => 'cash',
            'receipt_snapshot' => [
                'penalties' => 100,
            ],
            'paid_at' => now()->subMinutes(45),
        ]);

        // Attendant logs in again now (last_login_at is now, after payments)
        $attendant->update(['last_login_at' => now()]);

        $res = $this->actingAs($attendant)->get(route('attendant.shift.index'));
        $res->assertOk();

        // Must see 2 transactions, 210 cash collected (40 + 170), 10 discount, 100 penalties
        $res->assertSee('2'); // 2 transactions
        $res->assertSee('₱210.00'); // cash collected
        $res->assertSee('₱10.00'); // discounts
        $res->assertSee('₱100.00'); // penalties
        $res->assertSee('RC-7001');
        $res->assertSee('RC-7002');
    }
}
