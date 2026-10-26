<?php

namespace Tests\Feature;

use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The plate list applies on the driver screens and the guard screens alike. */
class PlateFormatsAcrossScreensTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): User
    {
        return User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function guard(): User
    {
        return User::factory()->create(['role' => User::ROLE_GUARD, 'status' => User::STATUS_ACTIVE, 'username' => 'guard'.random_int(100, 999)]);
    }

    private function parked(string $plate, string $ticket = 'PK-0001'): ParkingSession
    {
        $slot = ParkingSlot::firstOrCreate(['code' => 'A-01'], ['zone' => 'Zone A', 'type' => ParkingSlot::TYPE_STANDARD, 'status' => ParkingSlot::STATUS_AVAILABLE]);

        return ParkingSession::create([
            'ticket_number' => $ticket, 'slot_id' => $slot->id, 'slot_code' => $slot->code, 'plate_number' => $plate,
            'vehicle_color' => 'White', 'vehicle_type' => 'Car', 'entered_at' => now()->subMinutes(30), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);
    }

    public function test_a_driver_can_save_every_kind_of_plate_including_the_longest_one(): void
    {
        $driver = $this->driver();

        // typed => [stored, vehicle type]
        $cases = ['asd 1234' => ['ASD1234', 'Car'], 'a 123 bc' => ['A123BC', 'Motorcycle'], '12-3456' => ['12-3456', 'Car'], '123 4567' => ['1234567', 'Car'], '1234-12345678901' => ['1234-12345678901', 'Motorcycle'], '17' => ['17', 'Car']];

        foreach ($cases as $typed => [$stored, $type]) {
            $model = \App\Support\VehicleCatalog::forForm()[$type][0];
            $this->actingAs($driver)->post(route('driver.vehicles.store'), ['plate_number' => $typed, 'details_confirmed' => '1', 'color' => 'Blue', 'vehicle_type' => $type, 'make_model' => $model, 'contact_number' => '09175550142'])
                ->assertSessionHasNoErrors();
            $this->assertSame(1, Vehicle::where('plate_number', $stored)->count(), $typed);
        }

        // The temporary motorcycle plate is 16 characters, so the column has to hold it whole.
        $this->assertSame(16, strlen((string) Vehicle::where('plate_number', '1234-12345678901')->value('plate_number')));
    }

    public function test_a_driver_cannot_save_a_plate_that_is_not_on_the_list(): void
    {
        $driver = $this->driver();

        foreach (['AB 12345', 'PANGULO', '18', '1234', 'ABC 1', '123-4567', '1234 12345678901'] as $bad) {
            $this->actingAs($driver)->post(route('driver.vehicles.store'), ['plate_number' => $bad, 'color' => 'Blue', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'contact_number' => '09175550142'])
                ->assertSessionHasErrors('plate_number');
        }

        $this->assertSame(0, Vehicle::count());
    }

    public function test_the_guard_gate_search_finds_a_vehicle_by_any_way_of_typing_its_plate(): void
    {
        $this->parked('12-3456');
        $this->parked('1234567', 'PK-0002');
        $guard = $this->guard();

        foreach (['12-3456', '12 - 3456'] as $q) {
            $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => $q]))->assertOk()->assertSee('PK-0001');
        }

        foreach (['123 4567', '1234567'] as $q) {
            $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => $q]))->assertOk()->assertSee('PK-0002');
        }

        $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => 'AB 12345']))->assertOk()->assertSee('No session found');
    }

    public function test_the_guard_incident_form_checks_a_plate_against_the_same_list(): void
    {
        $this->parked('A123BC');
        $guard = $this->guard();

        $check = fn (string $plate, array $extra = []) => $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => $plate] + $extra));

        $check('a 123 bc')->assertOk()->assertJsonPath('state', 'parked')->assertJsonPath('slot', 'A-01');
        $check('AB 12345')->assertOk()->assertJsonPath('state', 'invalid');
        $check('PANGULO')->assertOk()->assertJsonPath('state', 'invalid');
        $check('18')->assertOk()->assertJsonPath('state', 'invalid');
        $check('12-3456', ['role' => 'responsible'])->assertOk()->assertJsonPath('state', 'unknown');
    }

    public function test_the_guard_can_file_a_report_for_a_hyphenated_plate_and_not_for_a_bad_one(): void
    {
        $this->parked('12-3456');
        $guard = $this->guard();
        $good = ['category' => 'Unauthorized parking', 'plate_number' => '12-3456', 'location' => 'A-01', 'description' => 'A car is parked across two slots.'];

        $this->actingAs($guard)->post(route('guard.incidents.store'), $good)->assertSessionHasNoErrors();
        // The dash is part of this plate; a space in its place is not a listed format.
        $this->actingAs($guard)->post(route('guard.incidents.store'), ['plate_number' => '12 3456'] + $good)->assertSessionHasErrors('plate_number');
        $this->actingAs($guard)->post(route('guard.incidents.store'), ['plate_number' => 'AB 12345'] + $good)->assertSessionHasErrors('plate_number');
    }

    public function test_the_screens_load_the_plate_script_and_show_the_new_hints(): void
    {
        $this->get(route('driver.register'))->assertOk()->assertSee('maxlength="20"', false)->assertSee('Choose the vehicle type first', false)
            ->assertDontSee('SENATE', false);
        $this->assertStringContainsString('Government plates 1 to 17', (string) file_get_contents(public_path('js/plate.js')));
        $this->assertStringContainsString('1[0-7]', (string) file_get_contents(public_path('js/plate.js')));
        $this->assertStringNotContainsString('PANGULO', (string) file_get_contents(public_path('js/plate.js')));
    }
}
