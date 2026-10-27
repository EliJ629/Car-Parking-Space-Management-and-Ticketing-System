<?php

namespace Tests\Feature;

use App\Models\BannedPlate;
use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BanService;
use App\Services\LongParkingService;
use App\Services\VehicleBanned;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The three-incident ban: what counts, against which plate, who confirms, and where it is enforced. */
class IncidentBanTest extends TestCase
{
    use RefreshDatabase;

    private int $ticket = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function user(string $role, string $name): User
    {
        return User::create(['name' => $name, 'username' => strtolower(str_replace(' ', '', $name)), 'email' => strtolower(str_replace(' ', '.', $name)).'@test.com',
            'phone' => '0917'.random_int(1000000, 9999999), 'password' => 'Staff123_', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function driverWithCar(string $plate = 'ABC1234'): User
    {
        $driver = $this->user(User::ROLE_DRIVER, 'Pedro Cruz');
        $driver->vehicles()->create(['plate_number' => $plate, 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios']);

        return $driver;
    }

    private function park(string $plate, string $slotCode, int $minutesAgo = 30, ?User $driver = null): ParkingSession
    {
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        return ParkingSession::create(['ticket_number' => 'PK-'.str_pad((string) $this->ticket++, 4, '0', STR_PAD_LEFT), 'driver_id' => $driver?->id, 'driver_phone' => '09171234567',
            'slot_id' => $slot->id, 'slot_code' => $slotCode, 'plate_number' => $plate, 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subMinutes($minutesAgo), 'status' => ParkingSession::STATUS_ACTIVE]);
    }

    /** A confirmed "Unauthorized parking" incident against a plate, made the way a Guard would and confirmed by a Manager. */
    private function violation(string $plate, string $slot, User $manager): Incident
    {
        $incident = Incident::create(['code' => 'INC-'.str_pad((string) (Incident::count() + 1), 4, '0', STR_PAD_LEFT), 'category' => 'Unauthorized parking', 'source' => 'manual',
            'status' => 'open', 'plate_number' => $plate, 'attributed_plate' => $plate, 'location' => $slot, 'description' => 'Parked across two bays.',
            'reported_by' => $manager->id, 'reported_at' => now(), 'confirmation' => Incident::PENDING]);
        app(BanService::class)->confirm($incident, $manager);

        return $incident->fresh();
    }

    public function test_damage_counts_against_the_responsible_vehicle_not_the_damaged_one(): void
    {
        $guard = $this->user(User::ROLE_GUARD, 'Ben Aquino');
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $this->park('DEF5678', 'A-01');
        $this->park('GHI9999', 'A-02');
        $this->get('/'); // no-op, keeps the app booted

        $this->actingAs($guard)->post(route('guard.incidents.store'), [
            'category' => 'Vehicle damage', 'plate_number' => 'DEF 5678', 'responsible_plate' => 'GHI 9999', 'location' => 'A-01', 'description' => 'Dent on the rear door.',
        ])->assertSessionHasNoErrors();

        $incident = Incident::sole();
        $this->assertSame('DEF5678', $incident->damaged_plate);
        $this->assertSame('GHI9999', $incident->responsible_plate);
        $this->assertSame('GHI9999', $incident->attributed_plate);
        $this->assertSame(Incident::PENDING, $incident->confirmation);

        $bans = app(BanService::class);
        $this->assertSame(0, $bans->count('GHI9999'), 'A report waiting for review does not count.');

        app(BanService::class)->confirm($incident, $manager);
        $this->assertSame(1, $bans->count('GHI9999'));
        $this->assertSame(0, $bans->count('DEF5678'), 'The damaged vehicle is never penalised.');
    }

    public function test_pending_and_dismissed_reports_never_count_and_a_dismissed_one_cannot_be_confirmed_later(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $bans = app(BanService::class);
        $make = fn (string $code) => Incident::create(['code' => $code, 'category' => 'Violation', 'source' => 'manual', 'status' => 'open', 'plate_number' => 'ABC1234',
            'attributed_plate' => 'ABC1234', 'description' => 'Blocked the lane.', 'reported_at' => now(), 'confirmation' => Incident::PENDING]);

        $pending = $make('INC-0001');
        $dismissed = $make('INC-0002');
        $this->assertSame(0, $bans->count('ABC1234'));

        $this->assertTrue($bans->dismiss($dismissed, $manager, 'Wrong vehicle.'));
        $this->assertSame(0, $bans->count('ABC1234'));
        $this->assertFalse($bans->confirm($dismissed->fresh(), $manager)['changed'], 'A dismissed report stays dismissed.');
        $this->assertSame(0, $bans->count('ABC1234'));
        $this->assertSame(Incident::PENDING, $pending->fresh()->confirmation);
    }

    public function test_the_third_confirmed_incident_bans_the_plate_and_warnings_come_first(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $driver = $this->driverWithCar('ABC1234');
        $this->park('ABC1234', 'A-01', driver: $driver);

        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'incident_warning')->count());

        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->assertSame(2, Notice::where('user_id', $driver->id)->where('kind', 'incident_warning')->count());

        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertTrue(app(BanService::class)->isBanned('abc 1234'), 'Spacing and case do not matter.');
        $ban = BannedPlate::sole();
        $this->assertSame('ABC1234', $ban->plate_number);
        $this->assertSame(3, $ban->incident_count);
        $this->assertSame($driver->id, $ban->user_id, 'Linked because the plate is a saved vehicle of this account.');
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'vehicle_banned')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'vehicle_banned']);
        $this->assertSame(User::STATUS_ACTIVE, $driver->fresh()->status, 'The account itself is not disabled.');
    }

    public function test_confirming_twice_or_in_a_race_counts_once(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $incident = Incident::create(['code' => 'INC-0001', 'category' => 'Violation', 'source' => 'manual', 'status' => 'open', 'plate_number' => 'ABC1234',
            'attributed_plate' => 'ABC1234', 'description' => 'Blocked the lane.', 'reported_at' => now(), 'confirmation' => Incident::PENDING]);
        $bans = app(BanService::class);

        $first = $bans->confirm($incident, $manager);
        $second = $bans->confirm($incident, $manager);

        $this->assertTrue($first['changed']);
        $this->assertFalse($second['changed']);
        $this->assertSame(1, $bans->count('ABC1234'));
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'incident_confirmed')->count());
    }

    public function test_the_same_damage_reported_twice_on_one_day_is_one_incident(): void
    {
        $guard = $this->user(User::ROLE_GUARD, 'Ben Aquino');
        $this->park('DEF5678', 'A-01');
        $this->park('GHI9999', 'A-02');
        $payload = ['category' => 'Vehicle damage', 'plate_number' => 'DEF5678', 'responsible_plate' => 'GHI9999', 'location' => 'A-01', 'description' => 'Dent on the rear door.'];

        $this->actingAs($guard)->post(route('guard.incidents.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('guard.incidents.store'), array_merge($payload, ['description' => 'Same dent, second guard.']))->assertSessionHasNoErrors();

        $this->assertSame(1, Incident::count(), 'The second report is a follow-up, not a second incident.');
        $this->assertDatabaseHas('incident_notes', ['incident_id' => Incident::first()->id, 'kind' => 'followup']);
    }

    public function test_the_threshold_comes_from_the_managers_setting(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        SystemSetting::updateOrCreate(['key' => 'ban_incident_threshold'], ['value' => 2, 'group' => 'parking']);

        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertTrue(app(BanService::class)->isBanned('ABC1234'));
    }

    public function test_a_banned_vehicle_cannot_reserve_walk_in_check_in_or_register(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $attendant = $this->user(User::ROLE_ATTENDANT, 'Marco Villanueva');
        $driver = $this->driverWithCar('ABC1234');
        $slot = ParkingSlot::where('code', 'A-03')->first();
        $at = Lot::now()->addHours(3);
        $reservationForm = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'slot' => $slot->id, 'vehicle' => $driver->vehicles()->first()->id, 'understand' => '1'];

        // A reservation made before the ban cannot be checked in after it.
        $this->actingAs($driver)->post(route('driver.reserve.store'), $reservationForm)->assertRedirect();
        $reservation = ParkingReservation::sole();

        foreach ([1, 2, 3] as $n) {
            $this->violation('ABC1234', 'B-0'.$n, $manager);
        }
        $this->assertTrue(app(BanService::class)->isBanned('ABC1234'));

        // 1. The banned driver is signed out at the next page, so no new reservation can be made.
        $reservation->update(['status' => ParkingReservation::STATUS_CANCELED]);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $reservationForm)->assertRedirect(route('driver.login'));
        $this->assertGuest();
        $this->assertSame(1, ParkingReservation::count());

        // The reservation rule itself still refuses the banned plate.
        try {
            app(\App\Services\ReservationService::class)->create($driver, $driver->vehicles()->first(), $slot, $at);
            $this->fail('A banned vehicle must not reserve.');
        } catch (\App\Services\ReservationException $e) {
            $this->assertSame('vehicle_banned', $e->reason);
        }

        // 2. A walk-in at the booth, the lookup first and then the entry itself.
        $this->actingAs($attendant)->postJson(route('attendant.entry.walk-in.lookup'), ['plate' => 'abc 1234'])->assertStatus(422)->assertJson(['status' => 'banned']);
        $this->post(route('attendant.entry.walk-in.store'), ['plate' => 'ABC 1234', 'phone' => '09175550199', 'color' => 'Blue', 'type' => 'Car', 'slot_code' => 'A-04'])
            ->assertSessionHasErrors('plate');
        $this->assertSame(0, ParkingSession::where('plate_number', 'ABC1234')->count());

        // 3. A different account cannot register or save the plate either.
        Mail::fake();
        auth()->logout();
        $this->flushSession();
        $this->post(route('driver.register'), [
            'name' => 'Juan Dela Cruz', 'email' => 'juan@gmail.com', 'phone' => '09171230000', 'plate_number' => 'ABC-1234', 'vehicle_type' => 'Car',
            'make_model' => 'Honda City', 'color' => 'Black', 'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!',
        ])->assertSessionHasErrors('plate_number');
    }

    public function test_lifting_a_ban_restores_access_and_only_newer_incidents_count_again(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        foreach ([1, 2, 3] as $n) {
            $this->violation('ABC1234', 'A-0'.$n, $manager);
        }
        $ban = BannedPlate::sole();
        $this->assertTrue(app(BanService::class)->isBanned('ABC1234'));

        $this->travel(1)->minutes();
        $this->assertTrue(app(BanService::class)->lift($ban, $manager, 'Paid the fine.'));
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->assertSame(0, app(BanService::class)->count('ABC1234'), 'The three old incidents stay on file but no longer count.');
        $this->assertSame(3, Incident::where('attributed_plate', 'ABC1234')->count());
        $this->assertFalse(app(BanService::class)->lift($ban->fresh(), $manager, 'Again.'), 'A lifted ban cannot be lifted twice.');

        $this->travel(1)->minutes();
        $this->violation('ABC1234', 'A-01', $manager);
        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->violation('ABC1234', 'A-01', $manager);
        $this->assertTrue(app(BanService::class)->isBanned('ABC1234'));
        $this->assertSame(1, BannedPlate::count(), 'One row per plate; the same row is switched on again.');
    }

    public function test_an_automatic_long_parking_alert_is_confirmed_by_the_system_and_counts_once(): void
    {
        SystemSetting::updateOrCreate(['key' => 'long_parking_threshold_hours'], ['value' => 12, 'group' => 'parking']);
        $driver = $this->driverWithCar('ABC1234');
        $this->park('ABC1234', 'A-01', minutesAgo: 13 * 60, driver: $driver);
        $long = app(LongParkingService::class);

        $this->assertSame(1, $long->detect());
        $this->assertSame(0, $long->detect(), 'The second run finds the same session and makes nothing new.');

        $incident = Incident::sole();
        $this->assertSame(Incident::CONFIRMED, $incident->confirmation);
        $this->assertNull($incident->confirmed_by);
        $this->assertSame(1, app(BanService::class)->count('ABC1234'));
        $this->assertSame('long:'.ParkingSession::sole()->id, $incident->event_key);
    }

    public function test_changing_the_long_parking_threshold_rechecks_parked_vehicles_at_once(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        SystemSetting::updateOrCreate(['key' => 'long_parking_threshold_hours'], ['value' => 12, 'group' => 'parking']);
        $this->park('ABC1234', 'A-01', minutesAgo: 5 * 60);
        $this->assertSame(0, app(LongParkingService::class)->detect());

        $this->actingAs($manager)->put(route('manager.settings.update'), ['settings' => $this->settings(['long_parking_threshold_hours' => 4])])->assertSessionHasNoErrors();

        $this->assertSame(1, Incident::where('category', 'Long-parked vehicle')->count(), 'The new limit caught the vehicle immediately.');

        // Raising the limit again never deletes what was already recorded.
        $this->put(route('manager.settings.update'), ['settings' => $this->settings(['long_parking_threshold_hours' => 24])])->assertSessionHasNoErrors();
        $this->assertSame(1, Incident::where('category', 'Long-parked vehicle')->count());
    }

    /** @return array<string, mixed> */
    private function settings(array $override): array
    {
        return $override + [
            'idle_timeout_minutes' => 20, 'idle_warning_seconds' => 45, 'failed_sign_ins_before_lock' => 6, 'lock_duration_minutes' => 25,
            'long_parking_threshold_hours' => 12, 'reservation_grace_minutes' => 10, 'early_check_in_minutes' => 25, 'maximum_advance_booking_days' => 2,
            'maximum_slots_per_zone' => 16, 'maximum_active_zones' => 26, 'ban_incident_threshold' => 3, 'exit_window_minutes' => 15, 'reservation_reminder_minutes' => 20,
            'notify_reservation_confirmed' => '1', 'lot_name' => 'Bayline Test Lot', 'lot_address' => '1 Test Road', 'receipt_footer' => 'Pay at the booth.',
        ];
    }

    public function test_only_the_manager_can_confirm_dismiss_or_lift(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        $guard = $this->user(User::ROLE_GUARD, 'Ben Aquino');
        $incident = Incident::create(['code' => 'INC-0001', 'category' => 'Violation', 'source' => 'manual', 'status' => 'open', 'plate_number' => 'ABC1234',
            'attributed_plate' => 'ABC1234', 'description' => 'Blocked the lane.', 'reported_at' => now(), 'confirmation' => Incident::PENDING]);

        $this->actingAs($guard);
        foreach ([
            fn () => $this->post(route('manager.incidents.confirm', $incident)),
            fn () => $this->post(route('manager.incidents.dismiss', $incident), ['note' => 'No.']),
            fn () => $this->get(route('manager.bans.index')),
        ] as $attempt) {
            $this->assertContains($attempt()->getStatusCode(), [302, 403]);
        }
        $this->assertSame(Incident::PENDING, $incident->fresh()->confirmation);

        $this->actingAs($manager)->post(route('manager.incidents.confirm', $incident))->assertSessionHasNoErrors();
        $this->assertSame(Incident::CONFIRMED, $incident->fresh()->confirmation);
        $this->get(route('manager.incidents.show', $incident))->assertOk()->assertSee('1 of 3');
        $this->get(route('manager.bans.index'))->assertOk()->assertSee('No vehicle is banned');
    }

    public function test_the_manager_can_review_the_ban_list_and_lift_from_it(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        foreach ([1, 2, 3] as $n) {
            $this->violation('ABC1234', 'A-0'.$n, $manager);
        }
        $ban = BannedPlate::sole();

        $this->actingAs($manager)->get(route('manager.bans.index'))->assertOk()->assertSee('ABC 1234')->assertSee('INC-0001');
        $this->post(route('manager.bans.lift', $ban), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('manager.bans.lift', $ban), ['note' => 'Paid the fine.'])->assertSessionHasNoErrors();
        $this->assertFalse(app(BanService::class)->isBanned('ABC1234'));
        $this->get(route('manager.bans.index', ['state' => 'lifted']))->assertOk()->assertSee('Paid the fine.');
    }
}
