<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Csv;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Input rules and lot-time behaviour found in the system audit. */
class AuditValidationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_MANAGER, 'status' => User::STATUS_ACTIVE]);
    }

    private function driver(): User
    {
        return User::create([
            'name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '09175550142',
            'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function signUp(array $override = []): array
    {
        return $override + [
            'name' => 'Pedro Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '0917 123 4567', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!',
        ];
    }

    private function makeSession(string $ticket, \DateTimeInterface $enteredAt, string $plate = 'ABC1234'): ParkingSession
    {
        $slot = ParkingSlot::firstOrCreate(['code' => 'A-01'], ['zone' => 'Zone A', 'type' => ParkingSlot::TYPE_STANDARD, 'status' => ParkingSlot::STATUS_AVAILABLE]);

        return ParkingSession::create([
            'ticket_number' => $ticket, 'slot_id' => $slot->id, 'slot_code' => $slot->code, 'plate_number' => $plate,
            'vehicle_color' => 'White', 'vehicle_type' => 'Car', 'entered_at' => $enteredAt, 'status' => 'completed', 'exited_at' => $enteredAt->copy()->addHour(),
        ]);
    }

    public function test_sign_up_rejects_plates_names_phones_and_passwords_that_break_the_rules(): void
    {
        // The sign up route is rate limited; this test sends many bad forms in a row.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach ([
            ['plate_number' => 'A12'],
            ['plate_number' => 'ABCDEFGHI1'],
            ['plate_number' => '12345'],
            ['plate_number' => 'ABC 12 3 45'],
            ['name' => 'Pedro 99'],
            ['name' => 'P'],
            ['phone' => '12345'],
            ['email' => 'not-an-email'],
            ['color' => 'Plaid'],
            ['vehicle_type' => 'Tank'],
            ['password' => 'short1A', 'password_confirmation' => 'short1A'],
            ['password' => 'alllowercase1', 'password_confirmation' => 'alllowercase1'],
            ['password' => 'NoNumbersHere!', 'password_confirmation' => 'NoNumbersHere!'],
            ['password' => 'NoSpecial123x', 'password_confirmation' => 'NoSpecial123x'],
            ['email' => 'pedro@example.com'],
            ['password' => str_repeat('Aa1!', 30), 'password_confirmation' => str_repeat('Aa1!', 30)],
            ['password_confirmation' => 'Different123!'],
        ] as $bad) {
            $key = array_key_first($bad) === 'password_confirmation' ? 'password' : array_key_first($bad);
            $response = $this->post(route('driver.register'), $this->signUp($bad));
            try {
                $response->assertSessionHasErrors($key);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail('Not refused: '.json_encode($bad));
            }
        }

        $this->assertSame(0, User::where('role', User::ROLE_DRIVER)->count());
    }

    public function test_sign_up_accepts_short_numeric_and_government_style_plates(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach (['7', '12', 'SENATE 5', 'PNP 1234', 'ABC 1234', '1234 AB'] as $i => $plate) {
            $this->post(route('driver.register'), $this->signUp([
                'plate_number' => $plate, 'name' => 'Driver '.chr(65 + $i).'ero', 'email' => "vip.driver{$i}@gmail.com", 'phone' => '0917123456'.$i,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(6, Vehicle::count());
    }

    public function test_sign_up_stores_a_clean_record_and_refuses_a_plate_that_is_already_registered(): void
    {
        $this->post(route('driver.register'), $this->signUp(['name' => '  Pedro   Cruz ', 'email' => ' Pedro.Cruz@GMAIL.com ']))
            ->assertRedirect(route('driver.verify'));

        $user = User::where('email', 'pedro.cruz@gmail.com')->firstOrFail();
        $this->assertNull($user->email_verified_at, 'A new account stays unverified until the code is entered.');
        $this->assertSame('Pedro Cruz', $user->name);
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame('KLM4455', Vehicle::where('user_id', $user->id)->value('plate_number'));

        auth()->logout();
        $this->post(route('driver.register'), $this->signUp(['email' => 'other.person@gmail.com', 'name' => 'Other Person', 'plate_number' => 'klm-4455']))
            ->assertSessionHasErrors('plate_number');
        $this->assertSame(1, Vehicle::count());
    }

    public function test_driver_password_change_keeps_the_length_and_character_rules(): void
    {
        $driver = $this->driver();
        $this->actingAs($driver);

        foreach (['lowercase123', 'NoDigitsAtAll', 'Short1A'] as $weak) {
            $this->put(route('driver.account.password.update'), ['current_password' => 'Driver123_', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrors('password');
        }

        $this->put(route('driver.account.password.update'), ['current_password' => 'wrong', 'password' => 'Brandnew123!', 'password_confirmation' => 'Brandnew123!'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Driver123_', $driver->fresh()->password));
    }

    public function test_a_driver_profile_needs_a_real_name_email_and_mobile_number(): void
    {
        $driver = $this->driver();
        $this->actingAs($driver);

        $this->put(route('driver.account.profile.update'), ['name' => '12345', 'email' => 'maria@example.com', 'phone' => '09175550142'])->assertSessionHasErrors('name');
        $this->put(route('driver.account.profile.update'), ['name' => 'Maria Santos', 'email' => 'nope', 'phone' => '09175550142'])->assertSessionHasErrors('email');
        $this->put(route('driver.account.profile.update'), ['name' => 'Maria Santos', 'email' => 'maria@yahoo.com', 'phone' => '09175550142'])->assertSessionHasErrors('email');
        $this->put(route('driver.account.profile.update'), ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '555'])->assertSessionHasErrors('phone');
        \Illuminate\Support\Facades\Mail::fake();
        $this->put(route('driver.account.profile.update'), ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '+63 917 555 0199'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09175550199', $driver->fresh()->pending_phone, 'The number is held until the emailed code is entered.');
        $this->assertSame('09175550142', $driver->fresh()->phone);
    }

    public function test_the_forced_staff_password_change_enforces_length_and_cannot_be_repeated_later(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_ATTENDANT, 'status' => 'active', 'username' => 'temporary02',
            'password' => 'Temporary123456', 'must_change_password' => true,
        ]);
        $this->actingAs($staff);

        $this->put(route('staff.password.change.update'), ['password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->put(route('staff.password.change.update'), ['password' => 'onlyletterslong', 'password_confirmation' => 'onlyletterslong'])->assertSessionHasErrors('password');
        $this->put(route('staff.password.change.update'), ['password' => 'Temporary123456', 'password_confirmation' => 'Temporary123456'])->assertSessionHasErrors('password');
        $this->assertTrue($staff->fresh()->must_change_password);

        $this->put(route('staff.password.change.update'), ['password' => 'Fresh_Secret_2026', 'password_confirmation' => 'Fresh_Secret_2026'])->assertRedirect(route('staff.home'));
        $this->assertFalse($staff->fresh()->must_change_password);

        // Once changed, the screen is closed.
        $this->put(route('staff.password.change.update'), ['password' => 'Another_Secret_2026', 'password_confirmation' => 'Another_Secret_2026'])
            ->assertRedirect(route('staff.home'));
        $this->assertTrue(Hash::check('Fresh_Secret_2026', $staff->fresh()->password));
    }

    public function test_staff_accounts_check_names_usernames_contacts_and_duplicates(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $good = ['name' => 'Ana Reyes', 'username' => 'anareyes', 'email' => 'ana@example.com', 'phone' => '09171110000', 'role' => 'attendant'];

        foreach ([
            ['name' => 'A1'], ['username' => 'ab'], ['username' => 'has space'], ['email' => 'bad'], ['phone' => '123'], ['role' => 'driver'],
            ['email' => '', 'phone' => ''],
        ] as $bad) {
            $this->post(route('manager.accounts.store'), $bad + $good)->assertSessionHasErrors();
        }

        $this->post(route('manager.accounts.store'), $good)->assertSessionHasNoErrors();
        $this->post(route('manager.accounts.store'), ['username' => 'ANAREYES', 'email' => 'other@example.com', 'phone' => '09172220000'] + $good)->assertSessionHasErrors('username');
        $this->post(route('manager.accounts.store'), ['username' => 'anatwo', 'email' => 'ANA@example.com'] + $good)->assertSessionHasErrors('email');
    }

    public function test_settings_stay_inside_the_documented_ranges(): void
    {
        $manager = $this->manager();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->actingAs($manager);

        $base = SystemSetting::query()->pluck('value', 'key')->all();
        $send = fn (array $change) => $this->put(route('manager.settings.update'), ['settings' => array_merge($base, $change)]);

        foreach ([
            'idle_timeout_minutes' => [4, 61], 'idle_warning_seconds' => [9, 121], 'failed_sign_ins_before_lock' => [2, 11],
            'lock_duration_minutes' => [4, 61], 'long_parking_threshold_hours' => [0, 73],
        ] as $key => [$low, $high]) {
            $send([$key => $low])->assertSessionHasErrors("settings.{$key}");
            $send([$key => $high])->assertSessionHasErrors("settings.{$key}");
        }

        $send(['idle_timeout_minutes' => 5, 'idle_warning_seconds' => 300])->assertSessionHasErrors();
        $send(['lot_name' => ' '])->assertSessionHasErrors('settings.lot_name');
    }

    public function test_a_rate_change_checks_its_amounts_and_reads_its_start_in_lot_time(): void
    {
        $manager = $this->manager();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->actingAs($manager);
        $when = Lot::now()->addDay()->setTime(9, 0);
        $rate = ['first_hour_fee' => 40, 'additional_hour_fee' => 20, 'daily_maximum' => 300, 'lost_ticket_penalty' => 100, 'overstay_penalty' => 150,
            'effective_at' => $when->format('Y-m-d H:i:s'), 'reason' => 'Holiday adjustment'];

        $this->post(route('manager.rates.store'), ['daily_maximum' => 30] + $rate)->assertSessionHasErrors('daily_maximum');
        $this->post(route('manager.rates.store'), ['first_hour_fee' => -1] + $rate)->assertSessionHasErrors('first_hour_fee');
        $this->post(route('manager.rates.store'), ['first_hour_fee' => '12.345'] + $rate)->assertSessionHasErrors('first_hour_fee');
        $this->post(route('manager.rates.store'), ['effective_at' => Lot::now()->subDay()->format('Y-m-d H:i:s')] + $rate)->assertSessionHasErrors('effective_at');
        $this->post(route('manager.rates.store'), ['reason' => 'x'] + $rate)->assertSessionHasErrors('reason');

        $this->post(route('manager.rates.store'), $rate)->assertSessionHasNoErrors();
        $stored = \App\Models\ParkingRateSet::latest('id')->firstOrFail();
        $this->assertSame($when->copy()->setTimezone('UTC')->format('Y-m-d H:i'), $stored->effective_at->format('Y-m-d H:i'));
    }

    public function test_a_slot_with_an_open_booking_cannot_be_removed_or_taken_out_of_service(): void
    {
        $manager = $this->manager();
        $slot = ParkingSlot::create(['code' => 'C-01', 'zone' => 'Zone C', 'type' => ParkingSlot::TYPE_STANDARD, 'status' => ParkingSlot::STATUS_AVAILABLE]);
        ParkingReservation::create([
            'code' => 'RSV-7001', 'driver_id' => $this->driver()->id, 'slot_id' => $slot->id, 'slot_code' => 'C-01',
            'plate_number' => 'ABC1234', 'scheduled_at' => now()->addDays(2), 'status' => 'booked',
        ]);

        $this->actingAs($manager)->patch(route('manager.slots.service.update', $slot), ['reason' => 'Maintenance'])->assertSessionHasErrors('slot');
        $this->delete(route('manager.slots.destroy', $slot))->assertSessionHasErrors('slot');
        $this->assertSame(ParkingSlot::STATUS_AVAILABLE, $slot->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
    }

    public function test_manager_incidents_follow_the_documented_rules_and_get_sequential_codes(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $good = ['category' => 'Unauthorized parking', 'plate_number' => 'abc 1234', 'location' => 'Zone A lane', 'description' => 'A car is parked across two slots.'];

        $this->post(route('manager.incidents.store'), ['description' => 'too short'] + $good)->assertSessionHasErrors('description');
        $this->post(route('manager.incidents.store'), ['description' => str_repeat('a', 501)] + $good)->assertSessionHasErrors('description');
        $this->post(route('manager.incidents.store'), ['plate_number' => 'A1'] + $good)->assertSessionHasErrors('plate_number');
        $this->post(route('manager.incidents.store'), ['category' => 'Made up'] + $good)->assertSessionHasErrors('category');

        $this->post(route('manager.incidents.store'), $good)->assertSessionHasNoErrors();
        $this->post(route('manager.incidents.store'), $good)->assertSessionHasNoErrors();
        $this->assertSame(['INC-0001', 'INC-0002'], Incident::orderBy('id')->pluck('code')->all());
        $this->assertSame('ABC1234', Incident::first()->plate_number);

        $incident = Incident::first();
        $this->patch(route('manager.incidents.update', $incident), ['status' => 'under_review'])->assertSessionHasNoErrors();
        $this->patch(route('manager.incidents.update', $incident), ['status' => 'resolved'])->assertSessionHasErrors('resolution_note');
        $this->patch(route('manager.incidents.update', $incident), ['status' => 'resolved', 'resolution_note' => 'Owner moved the car.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertDatabaseHas('incident_notes', ['incident_id' => $incident->id, 'kind' => 'status']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'incident_status_updated', 'area' => 'manager']);
    }

    public function test_guard_incident_limits_match_the_document(): void
    {
        $guard = User::create(['name' => 'Ben Aquino', 'username' => 'guard9', 'email' => 'guard9@example.com', 'phone' => '09170009999',
            'password' => 'Pass123_x', 'role' => User::ROLE_GUARD, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($guard);
        $good = ['category' => 'Suspicious vehicle', 'description' => 'Someone is checking car doors.', 'location' => 'Zone B'];

        $this->post(route('guard.incidents.store'), ['description' => str_repeat('a', 501)] + $good)->assertSessionHasErrors('description');
        $this->post(route('guard.incidents.store'), ['plate_number' => 'ZZ'] + $good)->assertSessionHasErrors('plate_number');
        $this->post(route('guard.incidents.store'), ['location' => str_repeat('b', 121)] + $good)->assertSessionHasErrors('location');
        $this->post(route('guard.incidents.store'), ['session_id' => 99999] + $good)->assertSessionHasErrors('session_id');
        $this->post(route('guard.incidents.store'), $good)->assertSessionHasNoErrors();
    }

    public function test_manager_records_follow_the_lot_calendar_day_not_the_utc_day(): void
    {
        $manager = $this->manager();
        // 17:00 UTC on Oct 15 is 01:00 on Oct 16 in Manila.
        $this->makeSession('PK-LATE', \Illuminate\Support\Carbon::parse('2026-10-15 17:00:00', 'UTC'));
        $this->makeSession('PK-EARLY', \Illuminate\Support\Carbon::parse('2026-10-15 15:00:00', 'UTC'), 'DEF5678');

        $this->actingAs($manager)->get(route('manager.records.index', ['date' => '2026-10-16']))
            ->assertOk()->assertSee('PK-LATE')->assertDontSee('PK-EARLY');
        $this->get(route('manager.records.index', ['date' => '2026-10-15']))
            ->assertOk()->assertSee('PK-EARLY')->assertDontSee('PK-LATE');

        $this->get(route('manager.records.index', ['date' => 'not-a-date']))->assertSessionHasErrors('date');
        $this->get(route('manager.records.index', ['status' => 'whatever']))->assertSessionHasErrors('status');
        $this->get(route('manager.records.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_sales_totals_group_by_lot_day_and_refuse_unreasonable_ranges(): void
    {
        $manager = $this->manager();
        $session = $this->makeSession('PK-5001', \Illuminate\Support\Carbon::parse('2026-10-15 16:00:00', 'UTC'));
        ParkingPayment::create([
            'receipt_number' => 'RC-5001', 'session_id' => $session->id, 'received_by' => $manager->id, 'base_fee' => 30, 'additional_fee' => 0,
            'penalty' => 0, 'discount' => 0, 'total' => 30, 'method' => 'cash', 'paid_at' => '2026-10-15 17:30:00',
        ]);

        $this->actingAs($manager)->get(route('manager.sales.index', ['from' => '2026-10-16', 'to' => '2026-10-16']))->assertOk()->assertSee('Oct 16')->assertDontSee('No paid receipts');
        $this->get(route('manager.sales.index', ['from' => '2026-10-15', 'to' => '2026-10-15']))->assertOk()->assertSee('No paid receipts in this date range');
        $this->get(route('manager.sales.index', ['from' => '2026-10-16', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('manager.sales.index', ['from' => '2020-01-01', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('manager.sales.export', ['from' => '2026-10-16']))->assertSessionHasErrors('to');
    }

    public function test_csv_exports_neutralise_spreadsheet_formulas(): void
    {
        $this->assertSame("'=1+1", Csv::cell('=1+1'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame("'-2+3", Csv::cell('-2+3'));
        $this->assertSame('-5', Csv::cell('-5'));
        $this->assertSame(12.5, Csv::cell(12.5));
        $this->assertSame('PK-0001', Csv::cell('PK-0001'));

        $manager = $this->manager();
        $this->makeSession('=HYPERLINK("x")', \Illuminate\Support\Carbon::parse('2026-10-16 02:00:00', 'UTC'));

        $csv = $this->actingAs($manager)->get(route('manager.records.export', ['tab' => 'sessions', 'date' => '2026-10-16']))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('2026-10-16 10:00:00', $csv, 'The export shows lot time.');
    }

    public function test_upcoming_bookings_include_a_reservation_that_is_checked_in(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $driver = $this->driver();
        $slot = ParkingSlot::where('code', 'A-03')->first();
        ParkingReservation::create([
            'code' => 'RSV-8001', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-03',
            'plate_number' => 'ABC1234', 'scheduled_at' => now()->subMinutes(10), 'status' => 'checked_in',
        ]);

        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee('RSV-8001');
        $this->get(route('driver.bookings', ['tab' => 'past']))->assertOk()->assertDontSee('RSV-8001');
    }

    public function test_manager_screens_render_without_server_errors(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $this->actingAs($this->manager());

        foreach (['manager.dashboard', 'manager.slots.index', 'manager.slots.create', 'manager.accounts.index', 'manager.accounts.create',
            'manager.records.index', 'manager.incidents.index', 'manager.incidents.create', 'manager.settings.index', 'manager.sales.index',
            'manager.reports.index', 'manager.rates.index', 'manager.my-account'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }
}
