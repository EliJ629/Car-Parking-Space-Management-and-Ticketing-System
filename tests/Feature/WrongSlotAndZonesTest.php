<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BanService;
use App\Services\ReservationService;
use App\Support\Lot;
use App\Support\SlotLayout;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class WrongSlotAndZonesTest extends TestCase
{
    use RefreshDatabase;

    private function driver(string $type = 'Car', string $plate = 'ABC1234'): array
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        $vehicle = $driver->vehicles()->create(['plate_number' => $plate, 'vehicle_type' => $type, 'color' => 'Black', 'make_model' => 'Test', 'contact_number' => '09170000000']);

        return [$driver, $vehicle];
    }

    private function guard(): User
    {
        return User::factory()->create(['role' => User::ROLE_GUARD, 'status' => User::STATUS_ACTIVE, 'username' => 'guard'.random_int(100, 999)]);
    }

    private function slot(string $code, string $zone, string $class = 'car', string $type = 'Standard'): ParkingSlot
    {
        return ParkingSlot::create(['code' => $code, 'zone' => $zone, 'type' => $type, 'vehicle_class' => $class, 'status' => ParkingSlot::STATUS_AVAILABLE]);
    }

    public function test_tricycle_is_a_vehicle_type_with_models_and_parks_in_car_zones(): void
    {
        $this->assertContains('Tricycle', Vehicle::DRIVER_TYPES);
        $this->assertContains('Tricycle', array_keys(\App\Support\VehicleCatalog::forForm()));
        $this->assertSame('car', Vehicle::zoneClassFor('Tricycle'));
        $this->assertSame('car', Vehicle::zoneClassFor('SUV'));
        $this->assertSame('car', Vehicle::zoneClassFor('Van'));
        $this->assertSame('motorcycle', Vehicle::zoneClassFor('Motorcycle'));

        $this->get(route('driver.register'))->assertOk()->assertSee('Tricycle');
    }

    public function test_a_new_lot_has_a_motorcycle_zone(): void
    {
        $this->seed(ParkingSlotSeeder::class);

        $this->assertSame('motorcycle', ParkingSlot::where('code', 'D-03')->value('vehicle_class'));
        $this->assertSame('car', ParkingSlot::where('code', 'A-03')->value('vehicle_class'));
    }

    public function test_the_reserve_page_asks_for_the_vehicle_before_the_slot_and_marks_each_slot_with_its_zone(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->slot('A-01', 'Zone A', 'car');
        $this->slot('B-01', 'Zone B', 'motorcycle');
        [$driver, $vehicle] = $this->driver('Tricycle');
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')];

        $html = $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2] + $q))->assertOk()
            ->assertSeeInOrder(['1. Pick the vehicle you will use', '2. Pick a slot'])
            ->getContent();

        $this->assertStringContainsString('data-class="car"', $html);
        $this->assertStringContainsString('data-class="motorcycle"', $html);
        $this->assertStringContainsString('Motorcycles', $html);
        // No vehicle is chosen for the driver.
        $this->assertDoesNotMatchRegularExpression('/name="vehicle"[^>]*checked/', $html);

        $this->get(route('driver.reserve', ['step' => 3, 'slot' => ParkingSlot::where('code', 'A-01')->value('id')] + $q))
            ->assertRedirect()->assertSessionHasErrors('vehicle');
    }

    public function test_a_motorcycle_cannot_reserve_a_car_slot_and_a_tricycle_cannot_reserve_a_motorcycle_slot(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $car = $this->slot('A-01', 'Zone A', 'car');
        $moto = $this->slot('B-01', 'Zone B', 'motorcycle');
        [$rider, $bike] = $this->driver('Motorcycle', 'A123BC');
        [$tri, $trike] = $this->driver('Tricycle', 'CD23456');
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'understand' => '1'];

        // Review step.
        $this->actingAs($rider)->get(route('driver.reserve', ['step' => 3, 'slot' => $car->id, 'vehicle' => $bike->id] + $q))
            ->assertRedirect()->assertSessionHasErrors('slot');
        $this->actingAs($tri)->get(route('driver.reserve', ['step' => 3, 'slot' => $moto->id, 'vehicle' => $trike->id] + $q))
            ->assertRedirect()->assertSessionHasErrors('slot');

        // The booking itself, even when the screens are skipped.
        $this->actingAs($rider)->post(route('driver.reserve.store'), $q + ['slot' => $car->id, 'vehicle' => $bike->id])->assertSessionHasErrors('slot');
        $this->actingAs($tri)->post(route('driver.reserve.store'), $q + ['slot' => $moto->id, 'vehicle' => $trike->id])->assertSessionHasErrors('slot');
        $this->assertSame(0, ParkingReservation::count());

        // The right zone works for both.
        $this->actingAs($rider)->post(route('driver.reserve.store'), $q + ['slot' => $moto->id, 'vehicle' => $bike->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($tri)->post(route('driver.reserve.store'), $q + ['slot' => $car->id, 'vehicle' => $trike->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, ParkingReservation::count());
    }

    public function test_the_service_refuses_the_wrong_zone(): void
    {
        $car = $this->slot('A-01', 'Zone A', 'car');
        [$rider, $bike] = $this->driver('Motorcycle', 'A123BC');

        $this->expectExceptionMessage('car zone');
        app(ReservationService::class)->create($rider, $bike, $car, now()->addHours(2));
    }

    public function test_the_manager_marks_a_zone_for_motorcycles_and_the_whole_zone_follows(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'status' => User::STATUS_ACTIVE]);
        $a = $this->slot('A-01', 'Zone A');
        $this->slot('A-02', 'Zone A');

        $this->actingAs($manager)->patch(route('manager.slots.update', $a), ['zone' => 'Zone A', 'type' => 'Standard', 'vehicle_type' => 'Motorcycle'])
            ->assertRedirect(route('manager.slots.index'));
        $this->assertSame(['motorcycle', 'motorcycle'], ParkingSlot::where('zone', 'Zone A')->pluck('vehicle_class')->all());

        // A new slot cannot be the odd one out.
        $this->post(route('manager.slots.store'), ['code' => 'A-03', 'zone' => 'Zone A', 'type' => 'Standard', 'vehicle_type' => 'Car'])->assertSessionHasErrors('vehicle_type');
        $this->post(route('manager.slots.store'), ['code' => 'A-03', 'zone' => 'Zone A', 'type' => 'Standard', 'vehicle_type' => 'Motorcycle'])->assertSessionHasNoErrors();
        // Left out, it follows the zone.
        $this->post(route('manager.slots.store'), ['code' => 'A-04', 'zone' => 'Zone A', 'type' => 'Standard'])->assertSessionHasNoErrors();
        $this->assertSame('motorcycle', ParkingSlot::where('code', 'A-04')->value('vehicle_class'));
    }

    public function test_a_zone_cannot_change_its_vehicle_type_while_one_of_its_slots_is_in_use(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'status' => User::STATUS_ACTIVE]);
        $a = $this->slot('A-01', 'Zone A');
        $b = $this->slot('A-02', 'Zone A');
        $b->update(['status' => ParkingSlot::STATUS_OCCUPIED]);
        \App\Models\ParkingLayoutZone::create(['zone' => 'Zone A', 'vehicle_type' => 'Car']);

        $this->actingAs($manager)->patch(route('manager.slots.update', $a), ['zone' => 'Zone A', 'type' => 'Standard', 'vehicle_type' => 'Motorcycle'])
            ->assertSessionHasErrors('vehicle_type');
        $this->assertSame('car', $a->fresh()->vehicle_class);
    }

    public function test_the_booth_refuses_a_motorcycle_in_a_car_zone_and_a_car_in_a_motorcycle_zone(): void
    {
        $attendant = User::factory()->create(['role' => User::ROLE_ATTENDANT, 'status' => User::STATUS_ACTIVE, 'username' => 'att'.random_int(100, 999)]);
        $this->slot('A-01', 'Zone A', 'car');
        $this->slot('B-01', 'Zone B', 'motorcycle');
        $post = fn (array $d) => $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), $d + ['phone' => '', 'color' => 'Black']);

        $post(['plate' => 'A123BC', 'type' => 'Motorcycle', 'slot_code' => 'A-01'])->assertSessionHasErrors('slot_code');
        $post(['plate' => '1234AB', 'type' => 'Tricycle', 'slot_code' => 'B-01'])->assertSessionHasErrors('slot_code');
        $this->assertSame(0, ParkingSession::count());

        $post(['plate' => 'A123BC', 'type' => 'Motorcycle', 'slot_code' => 'B-01'])->assertSessionHasNoErrors();
        $post(['plate' => '1234AB', 'type' => 'Tricycle', 'slot_code' => 'A-01'])->assertSessionHasNoErrors();
        $this->assertSame(2, ParkingSession::count());
    }

    private function parkedIn(ParkingSlot $slot, string $plate, string $type = 'Car', int $minutes = 135): ParkingSession
    {
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        return ParkingSession::create([
            'ticket_number' => 'PK-'.str_pad((string) (ParkingSession::count() + 1), 4, '0', STR_PAD_LEFT), 'slot_id' => $slot->id, 'slot_code' => $slot->code,
            'plate_number' => $plate, 'vehicle_color' => 'Black', 'vehicle_type' => $type, 'entered_at' => now()->subMinutes($minutes), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);
    }

    public function test_a_wrong_slot_report_notifies_the_driver_at_once_and_counts_as_one_warning_when_confirmed(): void
    {
        $guard = $this->guard();
        [$driver, $vehicle] = $this->driver('Motorcycle', 'A123BC');
        $car = $this->slot('A-01', 'Zone A', 'car');
        $this->slot('B-01', 'Zone B', 'motorcycle');
        $this->parkedIn($car, 'A123BC', 'Motorcycle');

        $this->assertContains('Wrong slot', Incident::MANUAL_CATEGORIES);
        $this->assertSame('Parked in the wrong slot', Incident::guardLabel('Wrong slot'));

        $this->actingAs($guard)->post(route('guard.incidents.store'), [
            'category' => 'Wrong slot', 'plate_number' => 'A 123 BC', 'location' => 'A-01', 'description' => 'Motorcycle left in a car bay.', 'notify_driver' => '0',
        ])->assertRedirect();

        $incident = Incident::firstOrFail();
        $this->assertSame('Wrong slot', $incident->category);
        $this->assertSame('A123BC', $incident->attributed_plate);
        $this->assertTrue($incident->driver_notified);

        $notice = Notice::where('user_id', $driver->id)->where('kind', 'incident_wrong_slot')->firstOrFail();
        $this->assertSame('Your vehicle is parked in the wrong slot', $notice->title);
        $this->assertStringContainsString('A-01', $notice->body);
        $this->assertStringContainsString('motorcycle zone', $notice->body);
        $this->assertStringContainsString('one warning', $notice->body);

        // It is not a warning until the Manager confirms it; then it is exactly one.
        $bans = app(BanService::class);
        $this->assertSame(0, $bans->count('A123BC'));
        $bans->confirm($incident, User::factory()->create(['role' => User::ROLE_MANAGER, 'status' => User::STATUS_ACTIVE]));
        $this->assertSame(1, $bans->count('A123BC'));
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'incident_warning')->count());
    }

    public function test_a_wrong_slot_notice_names_the_reserved_slot(): void
    {
        $guard = $this->guard();
        [$driver, $vehicle] = $this->driver('Car', 'ABC1234');
        $mine = $this->slot('A-05', 'Zone A');
        $other = $this->slot('A-06', 'Zone A');
        $session = $this->parkedIn($other, 'ABC1234');
        ParkingReservation::create([
            'code' => 'RSV-ZZZ111', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'slot_id' => $mine->id, 'slot_code' => 'A-05', 'plate_number' => 'ABC1234',
            'scheduled_at' => now()->subMinutes(30), 'status' => ParkingReservation::STATUS_CHECKED_IN, 'session_id' => $session->id, 'approval_status' => ParkingReservation::APPROVAL_APPROVED,
        ]);

        $this->actingAs($guard)->post(route('guard.incidents.store'), [
            'category' => 'Wrong slot', 'plate_number' => 'ABC 1234', 'location' => 'A-06', 'description' => 'Parked in A-06, not the booked bay.',
        ])->assertRedirect();

        $this->assertStringContainsString('your reserved slot A-05', Notice::where('user_id', $driver->id)->where('kind', 'incident_wrong_slot')->value('body'));
    }

    public function test_the_guard_board_offers_a_wrong_slot_report_for_a_parked_slot(): void
    {
        $guard = $this->guard();
        $slot = $this->slot('A-01', 'Zone A');
        $this->parkedIn($slot, 'ABC1234');

        $html = $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->assertSee('Report wrong slot')->getContent();
        $this->assertStringContainsString('data-report="'.e(route('guard.incidents.create', ['category' => 'Wrong slot', 'plate' => 'ABC1234', 'location' => 'A-01', 'session' => ParkingSession::value('id')])).'"', $html);
    }

    public function test_an_occupied_square_shows_how_long_the_car_has_been_parked_and_a_reserved_one_shows_the_booking_time(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $guard = $this->guard();
        $parked = $this->slot('A-01', 'Zone A');
        $held = $this->slot('A-02', 'Zone A');
        $this->parkedIn($parked, 'ABC1234', 'Car', 135);
        [$driver, $vehicle] = $this->driver('Car', 'XYZ7788');
        $at = now()->addMinutes(20);
        ParkingReservation::create([
            'code' => 'RSV-AAA111', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'slot_id' => $held->id, 'slot_code' => 'A-02', 'plate_number' => 'XYZ7788',
            'scheduled_at' => $at, 'status' => ParkingReservation::STATUS_BOOKED, 'approval_status' => ParkingReservation::APPROVAL_APPROVED,
        ]);
        $held->update(['status' => ParkingSlot::STATUS_RESERVED]);
        $time = SlotLayout::tileTime($at);

        // The driver's Find screen.
        $this->actingAs($driver)->get(route('driver.find'))->assertOk()->assertSee('2 h 15 min')->assertSee($time);
        // The guard board.
        $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->assertSee('2 h 15 min')->assertSee($time)->assertSee('XYZ 7788');
    }

    public function test_tile_times_are_short(): void
    {
        $today = Lot::now()->startOfDay()->addHours(23)->addMinutes(30);
        $this->assertSame('11:30 PM', SlotLayout::tileTime($today));
        $this->assertStringStartsWith('Tmrw ', SlotLayout::tileTime(Lot::now()->addDay()->startOfDay()->addHours(10)));
        $this->assertSame('', SlotLayout::tileTime(null));
    }
}
