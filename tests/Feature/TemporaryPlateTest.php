<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ParkingReservation;
use App\Models\ParkingSlot;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePreviousPlate;
use App\Support\Lot;
use App\Support\PlateNumber;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\ConfirmsSignUp;
use Tests\TestCase;

/**
 * "Still in registration": the plate box stays open for a temporary plate, every new vehicle and every plate that is set is
 * confirmed on an "all details are correct" screen, and a permanent plate can never be changed.
 */
class TemporaryPlateTest extends TestCase
{
    use ConfirmsSignUp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function driver(): User
    {
        return User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(), 'phone' => '09175550142']);
    }

    /** @return array<string, mixed> */
    private function vehicleForm(array $override = []): array
    {
        return $override + ['vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black', 'contact_number' => '09175550142'];
    }

    private function signUp(array $override = []): array
    {
        return $override + $this->vehicleForm() + [
            'first_name' => 'Pedro', 'last_name' => 'Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '09171234567',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ];
    }

    // ---- the temporary plate format

    /** @return array<string, array{string, string, bool}> */
    public static function temporaryPlates(): array
    {
        return [
            'car L DDDD' => ['Car', 'T 1234', true], 'car LL DDDD' => ['Car', 'AB 1234', true], 'suv L DDDD' => ['SUV', 'T1234', true],
            'van LL DDDD' => ['Van', 'ab-1234', true], 'pickup L DDDD' => ['Pickup', 'T 1234', true],
            'motorcycle temporary' => ['Motorcycle', '1234-12345678901', true], 'tricycle temporary' => ['Tricycle', '1234 12345678901', false],
            'car standard plate' => ['Car', 'ABC 1234', false], 'car pre-1981' => ['Car', '12-3456', false],
            'motorcycle LL DDDD' => ['Motorcycle', 'AB 1234', false], 'motorcycle with a car temporary plate' => ['Motorcycle', 'T 1234', false],
            'car with a motorcycle temporary plate' => ['Car', '1234-12345678901', false],
        ];
    }

    #[DataProvider('temporaryPlates')]
    public function test_which_plates_count_as_temporary(string $type, string $plate, bool $temporary): void
    {
        $this->assertSame($temporary, PlateNumber::isTemporary($plate, $type), "$type / $plate");
        $this->assertSame($temporary, PlateNumber::temporaryProblem($plate, $type) === null);
    }

    public function test_the_temporary_messages_name_the_formats(): void
    {
        $this->assertStringContainsString('T 1234 or AB 1234', (string) PlateNumber::temporaryProblem('ABC 1234', 'Car'));
        $this->assertStringContainsString('1234-12345678901', (string) PlateNumber::temporaryProblem('AB 1234', 'Motorcycle'));
        $this->assertSame(PlateNumber::TEMPORARY_MESSAGE, PlateNumber::temporaryProblem('ABC 1234'));
        $this->assertSame(PlateNumber::MESSAGE, PlateNumber::temporaryProblem('AB 12345'));
    }

    // ---- sign up and Google

    public function test_sign_up_with_the_box_ticked_and_no_plate_creates_a_vehicle_without_one(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->signUp(['no_plate' => '1']))->assertSessionHasNoErrors()->assertRedirect(route('driver.register.sent'));
        $this->assertNull(PendingRegistration::firstOrFail()->plate_number);

        $this->confirmSignUp('pedro.cruz@gmail.com')->assertRedirect(route('driver.login'));
        $vehicle = Vehicle::firstOrFail();
        $this->assertNull($vehicle->plate_number);
        $this->assertFalse($vehicle->hasPlate());
        $this->assertSame('No plate yet', $vehicle->displayPlate());
    }

    public function test_the_plate_box_still_takes_a_temporary_plate_when_the_box_is_ticked(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->signUp(['no_plate' => '1', 'plate_number' => 't 1234']))->assertSessionHasNoErrors();
        $pending = PendingRegistration::firstOrFail();
        $this->assertSame('T1234', $pending->plate_number);
        $this->assertTrue((bool) $pending->plate_is_temporary);

        $this->confirmSignUp('pedro.cruz@gmail.com');
        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('T1234', $vehicle->plate_number);
        $this->assertTrue($vehicle->hasTemporaryPlate());
        $this->assertFalse($vehicle->hasPermanentPlate());
        $this->assertSame('T 1234 (temporary)', $vehicle->plateLabel());
    }

    public function test_a_motorcycle_temporary_plate_is_accepted_and_a_permanent_looking_plate_is_not(): void
    {
        Mail::fake();
        $moto = ['vehicle_type' => 'Motorcycle', 'make_model' => 'Honda Click', 'no_plate' => '1'];

        $this->post(route('driver.register'), $this->signUp($moto + ['plate_number' => 'A 123 BC']))->assertSessionHasErrors('plate_number');
        $this->post(route('driver.register'), $this->signUp($moto + ['plate_number' => '1234-12345678901']))->assertSessionHasNoErrors();
        $this->assertSame('1234-12345678901', PendingRegistration::firstOrFail()->plate_number);
    }

    public function test_a_permanent_plate_typed_with_the_box_ticked_is_refused_with_the_temporary_formats(): void
    {
        $this->post(route('driver.register'), $this->signUp(['no_plate' => '1', 'plate_number' => 'ABC 1234']))
            ->assertSessionHasErrors(['plate_number' => 'A temporary plate for a car looks like T 1234 or AB 1234. Untick "still in registration" to enter a permanent plate.']);
    }

    public function test_sign_up_without_the_box_ticked_still_needs_a_permanent_plate(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->signUp())->assertSessionHasErrors('plate_number');
        $this->post(route('driver.register'), $this->signUp(['plate_number' => 'KLM 4455']))->assertSessionHasNoErrors();
        $this->assertFalse((bool) PendingRegistration::firstOrFail()->plate_is_temporary);
    }

    public function test_sign_up_needs_the_all_details_are_correct_confirmation(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->signUp(['plate_number' => 'KLM 4455', 'details_confirmed' => '']))
            ->assertSessionHasErrors(['details_confirmed' => 'Check your details and confirm that they are all correct.']);
        $this->assertSame(0, PendingRegistration::count());

        $this->post(route('driver.register'), $this->signUp(['plate_number' => 'KLM 4455']))->assertSessionHasNoErrors();
        $this->assertSame(1, PendingRegistration::count());
    }

    public function test_two_sign_ups_with_no_plate_do_not_collide(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->signUp(['no_plate' => '1']))->assertSessionHasNoErrors();
        $this->confirmSignUp('pedro.cruz@gmail.com');
        $this->post(route('driver.register'), $this->signUp(['no_plate' => '1', 'email' => 'ana.reyes@gmail.com', 'first_name' => 'Ana', 'last_name' => 'Reyes']))->assertSessionHasNoErrors();
        $this->confirmSignUp('ana.reyes@gmail.com');
        $this->assertSame(2, Vehicle::whereNull('plate_number')->count());
    }

    public function test_the_google_finish_form_has_the_same_rules(): void
    {
        config(['services.google.client_id' => 'id-123', 'services.google.client_secret' => 'secret-456', 'services.google.redirect' => null]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-1001', 'email' => 'ana.reyes@gmail.com', 'email_verified' => true, 'name' => 'Ana Reyes'], 200),
        ]);
        parse_str((string) parse_url($this->get(route('driver.google.redirect'))->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->get(route('driver.google.callback', ['state' => $q['state'], 'code' => 'abc']))->assertRedirect(route('driver.google.complete'));

        $this->get(route('driver.google.complete'))->assertOk()
            ->assertSee('name="no_plate"', false)->assertSee('data-plate-temp="#no_plate"', false)
            ->assertSee('name="details_confirmed"', false)->assertSee('Are all these details correct?');

        $form = $this->vehicleForm() + ['first_name' => 'Ana', 'last_name' => 'Reyes', 'phone' => '09171234567', 'no_plate' => '1', 'plate_number' => 'T 5678'];
        $this->post(route('driver.google.store'), $form)->assertSessionHasErrors('details_confirmed');
        $this->post(route('driver.google.store'), $form + ['details_confirmed' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('driver.home'));

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('T5678', $vehicle->plate_number);
        $this->assertTrue($vehicle->hasTemporaryPlate());
    }

    public function test_the_sign_up_page_has_the_checkbox_the_hidden_flag_and_the_confirmation_screen(): void
    {
        $this->get(route('driver.register'))->assertOk()
            ->assertSee('name="no_plate"', false)
            ->assertSee('data-plate-temp="#no_plate"', false)
            ->assertSee('data-confirm-details="#details-confirm" data-confirm-always', false)
            ->assertSee('name="details_confirmed"', false)
            ->assertSee('id="details-confirm"', false)
            ->assertSee('js/details-confirm.js', false)
            ->assertSee('still in registration')
            ->assertSee('enter the temporary plate you were given');
    }

    // ---- the vehicle form

    public function test_a_new_vehicle_needs_the_confirmation(): void
    {
        $driver = $this->driver();
        $form = $this->vehicleForm(['plate_number' => 'ABC 1234']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $form)->assertSessionHasErrors(['details_confirmed' => 'Check the details and confirm that they are all correct.']);
        $this->assertSame(0, Vehicle::count());

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $form + ['details_confirmed' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, Vehicle::count());
        $this->assertFalse(Vehicle::firstOrFail()->hasTemporaryPlate());
    }

    public function test_a_vehicle_can_be_added_with_a_temporary_plate_or_none(): void
    {
        $driver = $this->driver();
        $go = fn (array $d) => $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->vehicleForm($d) + ['details_confirmed' => '1']);

        $go(['no_plate' => '1', 'plate_number' => 'AB 1234'])->assertSessionHasNoErrors();
        $go(['no_plate' => '1', 'make_model' => 'Toyota Wigo'])->assertSessionHasNoErrors();
        $go(['no_plate' => '1', 'plate_number' => 'ABC 1234'])->assertSessionHasErrors('plate_number');

        $this->assertTrue(Vehicle::where('plate_number', 'AB1234')->firstOrFail()->hasTemporaryPlate());
        $this->assertSame(1, Vehicle::whereNull('plate_number')->count());
    }

    public function test_a_temporary_plate_is_replaced_by_the_permanent_plate_after_the_confirmation_and_is_kept_on_record(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true]));
        $put = fn (array $d) => $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm($d));

        $edit = $this->actingAs($driver)->get(route('driver.vehicles.edit', $vehicle))->assertOk();
        $edit->assertSee('data-plate-locked="T1234"', false)->assertSee('name="no_plate"', false)->assertSee('This is a temporary plate.');

        // Not confirmed: nothing changes.
        $put(['plate_number' => 'ABC 1234'])->assertSessionHasErrors('details_confirmed');
        $this->assertSame('T1234', $vehicle->fresh()->plate_number);

        $put(['plate_number' => 'ABC 1234', 'details_confirmed' => '1'])->assertSessionHasNoErrors();
        $fresh = $vehicle->fresh();
        $this->assertSame('ABC1234', $fresh->plate_number);
        $this->assertTrue($fresh->hasPermanentPlate());
        $this->assertDatabaseHas('vehicle_previous_plates', ['vehicle_id' => $vehicle->id, 'plate_number' => 'T1234', 'was_temporary' => true]);
        $this->assertSame(1, ActivityLog::where('action', 'vehicle_plate_replaced')->count());
    }

    public function test_the_old_temporary_plate_cannot_be_registered_by_someone_else(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true]));
        VehiclePreviousPlate::create(['vehicle_id' => $vehicle->id, 'user_id' => $driver->id, 'plate_number' => 'T5555', 'replaced_at' => now()]);

        $this->assertTrue(Vehicle::plateTaken('T 5555'));
        $other = $this->driver();
        $this->actingAs($other)->post(route('driver.vehicles.store'), $this->vehicleForm(['plate_number' => 'T 5555', 'no_plate' => '1', 'details_confirmed' => '1']))->assertSessionHas('modal');
        $this->assertSame(0, $other->vehicles()->count());
    }

    public function test_a_temporary_plate_cannot_be_swapped_for_another_temporary_plate_or_kept_as_the_permanent_one(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true]));
        $put = fn (array $d) => $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm($d) + ['details_confirmed' => '1']);

        $put(['no_plate' => '1', 'plate_number' => 'T 9999'])->assertSessionHasErrors(['plate_number' => \App\Support\PlateEntry::TEMPORARY_ONLY_ONCE]);
        $put(['plate_number' => 'T 1234'])->assertSessionHasErrors(['plate_number' => \App\Support\PlateEntry::SAME_AS_TEMPORARY]);
        $put(['plate_number' => ''])->assertSessionHasErrors('plate_number');
        $this->assertSame('T1234', $vehicle->fresh()->plate_number);

        // Ticked and left alone: the vehicle can still be edited (here the color) with no confirmation, the plate stays.
        $put(['no_plate' => '1', 'plate_number' => 'T 1234', 'color' => 'Blue', 'details_confirmed' => ''])->assertSessionHasNoErrors();
        $this->assertSame('Blue', $vehicle->fresh()->color);
        $this->assertTrue($vehicle->fresh()->hasTemporaryPlate());
    }

    public function test_a_vehicle_with_no_plate_can_get_a_temporary_or_a_permanent_plate_with_the_confirmation(): void
    {
        $driver = $this->driver();
        $a = $driver->vehicles()->create($this->vehicleForm(['plate_number' => null]));
        $b = $driver->vehicles()->create($this->vehicleForm(['plate_number' => null, 'make_model' => 'Toyota Wigo']));

        $this->actingAs($driver)->put(route('driver.vehicles.update', $a), $this->vehicleForm(['no_plate' => '1', 'plate_number' => 'T 4321']))->assertSessionHasErrors('details_confirmed');
        $this->actingAs($driver)->put(route('driver.vehicles.update', $a), $this->vehicleForm(['no_plate' => '1', 'plate_number' => 'T 4321', 'details_confirmed' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->hasTemporaryPlate());
        $this->assertSame(0, VehiclePreviousPlate::count(), 'There was no earlier plate to keep.');

        $this->actingAs($driver)->put(route('driver.vehicles.update', $b), $this->vehicleForm(['make_model' => 'Toyota Wigo', 'plate_number' => 'DEF 5678', 'details_confirmed' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue($b->fresh()->hasPermanentPlate());
        $this->assertSame(2, ActivityLog::where('action', 'vehicle_plate_set')->count());
    }

    public function test_a_permanent_plate_cannot_be_changed_or_removed(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'ABC1234']));
        $put = fn (array $d) => $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm($d) + ['details_confirmed' => '1']);

        $put(['plate_number' => 'XYZ 9876'])->assertSessionHasErrors(['plate_number' => \App\Support\PlateEntry::PERMANENT_FINAL]);
        $put(['plate_number' => 'XYZ 9876', 'no_plate' => '1'])->assertSessionHasErrors('plate_number');
        $put(['plate_number' => 'XYZ 9876', 'plate_change_reason' => 'vanity'])->assertSessionHasErrors('plate_number');
        $this->assertSame('ABC1234', $vehicle->fresh()->plate_number);
        $this->assertSame(0, VehiclePreviousPlate::count());
    }

    public function test_the_other_details_of_a_permanent_plate_vehicle_can_still_be_edited_without_a_confirmation(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'ABC1234']));
        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm(['plate_number' => 'ABC 1234', 'color' => 'Blue']))->assertSessionHasNoErrors();
        $this->assertSame('Blue', $vehicle->fresh()->color);
        $this->assertSame('ABC1234', $vehicle->fresh()->plate_number);
    }

    public function test_a_plate_saved_in_an_older_format_stays_editable(): void
    {
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'AB12345']));
        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm(['plate_number' => 'AB12345', 'color' => 'Red']))->assertSessionHasNoErrors();
        $this->assertSame('Red', $vehicle->fresh()->color);

        // The form shows the plate but does not run the plate check on it, so the old plate does not block the save.
        $this->actingAs($driver)->get(route('driver.vehicles.edit', $vehicle))->assertOk()
            ->assertSee('value="AB 12345" readonly', false)
            ->assertSee('A saved plate cannot be changed')
            ->assertDontSee('name="no_plate"', false)
            ->assertDontSee('Why is the plate changing?');
    }

    public function test_the_plate_of_a_vehicle_that_is_reserved_or_parked_cannot_be_replaced(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true]));
        $slot = ParkingSlot::where('code', 'A-03')->firstOrFail();
        ParkingReservation::create(['code' => 'RSV-0001', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'slot_id' => $slot->id, 'slot_code' => 'A-03',
            'plate_number' => 'T1234', 'scheduled_at' => now()->addHour(), 'status' => ParkingReservation::STATUS_BOOKED, 'approval_status' => ParkingReservation::APPROVAL_APPROVED]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), $this->vehicleForm(['plate_number' => 'ABC 1234', 'details_confirmed' => '1']))
            ->assertSessionHasErrors(['plate_number' => 'The plate cannot change while this vehicle has a reservation or a parking session.']);
        $this->assertSame('T1234', $vehicle->fresh()->plate_number);
    }

    public function test_the_vehicle_pages_show_the_right_controls(): void
    {
        $driver = $this->driver();
        $none = $driver->vehicles()->create($this->vehicleForm(['plate_number' => null]));
        $temp = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true, 'make_model' => 'Toyota Wigo']));

        $this->actingAs($driver)->get(route('driver.vehicles.create'))->assertOk()
            ->assertSee('data-confirm-details="#details-confirm"', false)->assertSee('data-confirm-always', false)
            ->assertSee('name="details_confirmed"', false)->assertSee('id="details-confirm"', false)
            ->assertDontSee('plate-confirm', false);

        $this->actingAs($driver)->get(route('driver.vehicles.edit', $none))->assertOk()->assertSee('name="no_plate"', false)->assertSee('data-plate-temp="#no_plate"', false);
        $this->actingAs($driver)->get(route('driver.vehicles'))->assertOk()
            ->assertSee('No plate yet - add plate number')->assertSee('Temporary plate - add permanent plate');
        $this->assertTrue($temp->hasTemporaryPlate());
    }

    // ---- reservations and the guard

    public function test_a_vehicle_with_no_plate_cannot_be_reserved_but_one_with_a_temporary_plate_can(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $driver = $this->driver();
        $none = $driver->vehicles()->create($this->vehicleForm(['plate_number' => null]));
        $temp = $driver->vehicles()->create($this->vehicleForm(['plate_number' => 'T1234', 'plate_is_temporary' => true, 'make_model' => 'Toyota Wigo']));
        $slot = ParkingSlot::where('code', 'A-03')->firstOrFail();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')];

        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2] + $q))->assertOk()->assertSee('Add the plate to reserve');
        $this->actingAs($driver)->post(route('driver.reserve.store'), ['understand' => '1', 'slot' => $slot->id, 'vehicle' => $none->id] + $q)->assertSessionHasErrors('vehicle');
        $this->assertSame(0, ParkingReservation::count());

        $this->actingAs($driver)->post(route('driver.reserve.store'), ['understand' => '1', 'slot' => $slot->id, 'vehicle' => $temp->id] + $q)->assertSessionHasNoErrors();
        $this->assertSame('T1234', ParkingReservation::firstOrFail()->plate_number);
    }

    public function test_guard_and_booth_searches_stop_at_20_characters(): void
    {
        $guard = User::factory()->create(['role' => User::ROLE_GUARD, 'status' => User::STATUS_ACTIVE, 'username' => 'guard'.random_int(100, 999)]);
        $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search']))->assertOk()->assertSee('maxlength="20"', false);
        $this->actingAs($guard)->get(route('guard.records'))->assertOk()->assertSee('maxlength="20"', false);

        $long = str_repeat('A', 30);
        $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => $long]))->assertOk()->assertSee('value="'.str_repeat('A', 20).'"', false)->assertDontSee($long);
        $this->actingAs($guard)->get(route('guard.records', ['q' => $long]))->assertOk()->assertDontSee($long);
    }

    public function test_the_scripts_are_on_the_pages_and_the_contact_number_is_optional(): void
    {
        $driver = $this->driver();
        $this->actingAs($driver)->get(route('driver.vehicles.create'))->assertOk()->assertSee('js/details-confirm.js', false)
            ->assertSee('name="contact_number" type="tel" data-mobile data-optional', false);
        $js = file_get_contents(public_path('js/plate.js'));
        $this->assertStringContainsString('function limit(raw, type, temporary)', $js);
        $this->assertStringContainsString('function temporaryProblem', $js);
        $this->assertStringContainsString('data-plate-temp', $js);
        $this->assertFileDoesNotExist(public_path('js/plate-confirm.js'));
    }
}
