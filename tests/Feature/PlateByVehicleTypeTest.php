<?php

namespace Tests\Feature;

use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\PlateNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The plate depends on the vehicle type, so the type is asked first and the plate is checked against it. */
class PlateByVehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, bool}> type, plate, accepted */
    public static function matrix(): array
    {
        return [
            'car with LLL DDDD' => ['Car', 'ABC 1234', true], 'SUV with LLL DDD' => ['SUV', 'ABC 123', true], 'van with a vanity plate' => ['Van', 'ZEN 88', true],
            'pickup with a temporary car plate' => ['Pickup', 'T 1234', true], 'truck with LLL DDDD' => ['Truck', 'ABC 1234', true],
            'car with a pre-1981 plate' => ['Car', '12-3456', true], 'car with a diplomatic plate' => ['Car', '123 4567', true], 'car with a government plate' => ['Car', '17', true],
            'car with a vintage plate' => ['Car', 'A 1234 BC', true], 'car with LL DDDD' => ['Car', 'AB 1234', true], 'car with DDD LLL' => ['Car', '123 ABC', true],
            'car with a motorcycle plate L DDD LL' => ['Car', 'A 123 BC', false], 'car with DDDD LL' => ['Car', '1234 AB', false],
            'car with a temporary motorcycle plate' => ['Car', '1234-12345678901', false],
            'motorcycle with L DDD LL' => ['Motorcycle', 'A 123 BC', true], 'motorcycle with DDD LLL' => ['Motorcycle', '123 ABC', true],
            'motorcycle with LL DDDD' => ['Motorcycle', 'AB 1234', true], 'motorcycle with DDDD LL' => ['Motorcycle', '1234 AB', true],
            'motorcycle with a temporary plate' => ['Motorcycle', '1234-12345678901', true], 'motorcycle with a pre-1981 plate' => ['Motorcycle', 'AB 123', true],
            'motorcycle with a government plate' => ['Motorcycle', '5', true],
            'tricycle with DDDD LL' => ['Tricycle', '1234 AB', true], 'tricycle with LL DDDD' => ['Tricycle', 'AB 1234', true],
            'motorcycle with a car plate LLL DDDD' => ['Motorcycle', 'ABC 1234', false], 'tricycle with LLL DDD' => ['Tricycle', 'ABC 123', false],
            'motorcycle with a vanity plate' => ['Motorcycle', 'ZEN 88', false], 'tricycle with a temporary car plate' => ['Tricycle', 'T 1234', false],
            'car with a plate that is on no list' => ['Car', 'AB 12345', false], 'motorcycle with a plate that is on no list' => ['Motorcycle', 'PANGULO', false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_a_plate_must_suit_the_vehicle_type(string $type, string $plate, bool $accepted): void
    {
        $this->assertSame($accepted, PlateNumber::isValid($plate, $type), "$type / $plate");
        $this->assertSame($accepted, PlateNumber::problem($plate, $type) === null);
        // With no type chosen yet, any listed plate passes.
        if (PlateNumber::isValid($plate)) {
            $this->assertTrue(PlateNumber::isValid($plate, null));
        }
    }

    public function test_the_message_says_which_kind_of_vehicle_the_plate_belongs_to(): void
    {
        $this->assertStringContainsString('belongs to a motorcycle or tricycle', (string) PlateNumber::problem('A 123 BC', 'Car'));
        $this->assertStringContainsString('belongs to a car, SUV, van or pickup', (string) PlateNumber::problem('ABC 1234', 'Motorcycle'));
        $this->assertStringContainsString('not a supported plate for a tricycle', (string) PlateNumber::problem('AB 12345', 'Tricycle'));
        $this->assertSame(PlateNumber::MESSAGE, PlateNumber::problem('AB 12345'));
        $this->assertSame('four', PlateNumber::wheels('SUV'));
        $this->assertSame('two', PlateNumber::wheels('tricycle'));
        $this->assertNull(PlateNumber::wheels(''));
        $this->assertNull(PlateNumber::wheels('Spaceship'));
    }

    private function signUp(array $override = []): array
    {
        return $override + [
            'first_name' => 'Pedro', 'last_name' => 'Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '09171234567', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ];
    }

    public function test_sign_up_checks_the_plate_against_the_vehicle_type(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->post(route('driver.register'), $this->signUp(['vehicle_type' => 'Motorcycle', 'make_model' => 'Honda Click', 'plate_number' => 'KLM 4455']))
            ->assertSessionHasErrors(['plate_number' => 'This plate format belongs to a car, SUV, van or pickup. A motorcycle plate looks like A 123 BC, 123 ABC, AB 1234 or 1234 AB.']);

        $this->post(route('driver.register'), $this->signUp(['vehicle_type' => 'Car', 'plate_number' => 'K 123 LM']))
            ->assertSessionHasErrors('plate_number');

        $this->post(route('driver.register'), $this->signUp(['vehicle_type' => 'Motorcycle', 'make_model' => 'Honda Click', 'plate_number' => 'K 123 LM']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_driver_vehicle_form_checks_the_plate_against_the_vehicle_type(): void
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        $model = \App\Support\VehicleCatalog::forForm()['Tricycle'][0];
        $add = fn (array $d) => $this->actingAs($driver)->post(route('driver.vehicles.store'), $d + ['details_confirmed' => '1', 'color' => 'Blue', 'make_model' => $model, 'contact_number' => '09175550142']);

        $add(['plate_number' => 'ABC 1234', 'vehicle_type' => 'Tricycle'])->assertSessionHasErrors('plate_number');
        $add(['plate_number' => '1234 AB', 'vehicle_type' => 'Tricycle'])->assertSessionHasNoErrors();
        $this->assertSame(1, Vehicle::where('plate_number', '1234AB')->where('vehicle_type', 'Tricycle')->count());
    }

    public function test_the_booth_refuses_a_plate_that_does_not_suit_the_vehicle_type(): void
    {
        $attendant = User::factory()->create(['role' => User::ROLE_ATTENDANT, 'status' => User::STATUS_ACTIVE, 'username' => 'att'.random_int(100, 999)]);
        ParkingSlot::create(['code' => 'A-01', 'zone' => 'Zone A', 'type' => 'Standard', 'vehicle_class' => 'car', 'status' => ParkingSlot::STATUS_AVAILABLE]);

        $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), ['plate' => 'A123BC', 'type' => 'Car', 'slot_code' => 'A-01', 'phone' => '', 'color' => 'Black'])
            ->assertSessionHasErrors('type');
    }

    public function test_the_forms_ask_for_the_vehicle_before_the_plate(): void
    {
        $this->get(route('driver.register'))->assertOk()
            ->assertSeeInOrder(['id="vehicle_type"', 'id="make_model"', 'id="color"', 'id="plate_number"'], false)
            ->assertSee('data-plate-type="#vehicle_type"', false)
            ->assertSee('Choose the vehicle type first', false);

        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        $this->actingAs($driver)->get(route('driver.vehicles.create'))->assertOk()
            ->assertSeeInOrder(['id="vehicle_type"', 'id="color"', 'id="plate_number"'], false)
            ->assertSee('data-plate-type="#vehicle_type"', false);
    }

    public function test_a_form_sent_in_the_background_gets_the_errors_back_as_json_and_a_redirect_as_json(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', 'X-Bayline-Ajax' => '1'];

        $this->post(route('driver.register'), $this->signUp(['vehicle_type' => 'Motorcycle', 'make_model' => 'Honda Click', 'plate_number' => 'KLM 4455']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors('plate_number');

        $this->post(route('driver.register'), $this->signUp(), $headers)
            ->assertOk()->assertJsonPath('redirect', route('driver.register.sent'));
    }

    public function test_a_sign_up_with_a_badly_formed_answer_does_not_wait_for_database_checks(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

        // A Gmail name that is too short and a plate that is not on the list: nothing to look up in the database.
        $this->post(route('driver.register'), $this->signUp(['email' => 'elikw@gmail.com', 'plate_number' => 'AB 12345']))
            ->assertSessionHasErrors(['email', 'plate_number']);

        $joined = implode("\n", $queries);
        $this->assertStringNotContainsString('banned_plates', $joined);
        $this->assertStringNotContainsString('from "vehicles"', $joined);
        $this->assertStringNotContainsString('"email_key"', $joined);
        $this->assertStringNotContainsString('delete from "pending_registrations"', $joined);
        $this->assertLessThanOrEqual(2, count($queries), $joined);
    }

    public function test_a_stale_unconfirmed_account_still_frees_its_email_and_plate(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $old = User::factory()->create([
            'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email' => 'pedro.cruz@gmail.com', 'email_verified_at' => null, 'created_at' => now()->subDays(3),
        ]);
        $old->vehicles()->create(['plate_number' => 'KLM4455', 'vehicle_type' => 'Car', 'color' => 'Black', 'make_model' => 'Test', 'contact_number' => '09170000000']);

        $this->post(route('driver.register'), $this->signUp())->assertSessionHasNoErrors();
        $this->assertNull(User::find($old->id));
    }

    public function test_the_email_message_says_what_is_wrong_with_a_short_gmail_name(): void
    {
        $this->assertStringContainsString('6 to 30', \App\Support\Rules::GMAIL_MESSAGE);
        $js = (string) file_get_contents(public_path('js/bayline.js'));
        $this->assertStringContainsString('at least 6 characters before @gmail.com', $js);
    }
}
