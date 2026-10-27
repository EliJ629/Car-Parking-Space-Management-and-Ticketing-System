<?php

namespace Tests\Feature;

use App\Models\BannedPlate;
use App\Models\Incident;
use App\Models\Notice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePreviousPlate;
use App\Services\BanService;
use App\Services\VehicleBanned;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/** A temporary plate is replaced by the permanent plate: the violations on the old plate carry over to the new one. */
class ViolationsFollowTheVehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function user(string $role, string $name): User
    {
        return User::create(['name' => $name, 'username' => strtolower(str_replace(' ', '', $name)).random_int(10, 99), 'email' => strtolower(str_replace(' ', '.', $name)).random_int(10, 99).'@test.com',
            'phone' => '0917'.random_int(1000000, 9999999), 'password' => 'Staff123_', 'role' => $role, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function violation(string $plate, User $manager): Incident
    {
        $incident = Incident::create(['code' => 'INC-'.str_pad((string) (Incident::count() + 1), 4, '0', STR_PAD_LEFT), 'category' => 'Unauthorized parking', 'source' => 'manual',
            'status' => 'open', 'plate_number' => $plate, 'attributed_plate' => $plate, 'location' => 'A-01', 'description' => 'Parked across two bays.',
            'reported_by' => $manager->id, 'reported_at' => now(), 'confirmation' => Incident::PENDING]);
        app(BanService::class)->confirm($incident, $manager);

        return $incident->fresh();
    }

    /** A driver whose car is on a temporary plate, then moves to the permanent plate through the vehicle form. */
    private function driverWhoGetsThePermanentPlate(string $temp = 'T1234', string $permanent = 'ABC1234'): array
    {
        $driver = $this->user(User::ROLE_DRIVER, 'Pedro Cruz');
        $vehicle = $driver->vehicles()->create(['plate_number' => $temp, 'plate_is_temporary' => true, 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'contact_number' => '09175550142']);

        return [$driver, $vehicle, fn () => $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'White', 'contact_number' => '09175550142', 'plate_number' => $permanent, 'details_confirmed' => '1',
        ])->assertSessionHasNoErrors()];
    }

    public function test_two_violations_on_the_temporary_plate_and_one_on_the_permanent_plate_ban_the_new_plate(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [$driver, $vehicle, $swap] = $this->driverWhoGetsThePermanentPlate();
        $bans = app(BanService::class);

        $this->violation('T1234', $manager);
        $this->violation('T1234', $manager);
        $this->assertSame(2, $bans->count('T1234'));
        $this->assertFalse($bans->isBanned('T1234'));

        $swap();
        $this->assertSame('ABC1234', $vehicle->fresh()->plate_number);

        // The two old violations are already on the new plate.
        $this->assertSame(2, $bans->count('ABC1234'));
        $this->assertFalse($bans->isBanned('ABC1234'));

        $result = $this->violation('ABC1234', $manager);
        $this->assertSame(3, $bans->count('ABC1234'));
        $this->assertTrue($bans->isBanned('ABC1234'));
        $this->assertTrue($bans->isBanned('T1234'), 'The old plate belongs to the same vehicle.');
        $this->assertSame('ABC1234', BannedPlate::sole()->plate_number, 'The ban sits on the new plate.');
        $this->assertSame($driver->id, BannedPlate::sole()->user_id);
        $this->assertNotNull($result);
    }

    public function test_the_third_violation_reported_under_the_old_plate_after_the_change_bans_the_new_plate(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [, , $swap] = $this->driverWhoGetsThePermanentPlate();
        $bans = app(BanService::class);

        $this->violation('T1234', $manager);
        $this->violation('T1234', $manager);
        $swap();
        $this->violation('T1234', $manager);

        $this->assertTrue($bans->isBanned('ABC1234'));
        $this->assertSame('ABC1234', BannedPlate::sole()->plate_number);
    }

    public function test_violations_before_the_change_can_reach_three_on_their_own(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [, $vehicle, $swap] = $this->driverWhoGetsThePermanentPlate();
        $bans = app(BanService::class);

        $this->violation('T1234', $manager);
        $this->violation('T1234', $manager);
        $this->violation('T1234', $manager);
        $this->assertSame('T1234', BannedPlate::sole()->plate_number);

        // The ban was on the temporary plate. Getting the permanent plate does not escape it.
        $this->assertTrue($vehicle->fresh()->hasTemporaryPlate());
        $vehicle->update(['plate_number' => 'ABC1234', 'plate_is_temporary' => false]);
        VehiclePreviousPlate::create(['vehicle_id' => $vehicle->id, 'user_id' => $vehicle->user_id, 'plate_number' => 'T1234', 'was_temporary' => true, 'replaced_at' => now()]);
        $this->assertTrue($bans->isBanned('ABC1234'));
        $this->assertNotNull($bans->activeBan('ABC1234'));
        $this->expectException(VehicleBanned::class);
        $bans->assertAllowed('ABC1234');
    }

    public function test_a_banned_vehicle_cannot_be_reserved_under_its_new_plate(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [$driver, $vehicle] = $this->driverWhoGetsThePermanentPlate();
        foreach (range(1, 3) as $_) {
            $this->violation('T1234', $manager);
        }
        $vehicle->update(['plate_number' => 'ABC1234', 'plate_is_temporary' => false]);
        VehiclePreviousPlate::create(['vehicle_id' => $vehicle->id, 'user_id' => $driver->id, 'plate_number' => 'T1234', 'was_temporary' => true, 'replaced_at' => now()]);

        $at = \App\Support\Lot::now()->addHours(3);
        $slot = \App\Models\ParkingSlot::where('code', 'A-03')->firstOrFail();
        $this->actingAs($driver)->post(route('driver.reserve.store'), ['understand' => '1', 'slot' => $slot->id, 'vehicle' => $vehicle->id, 'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')])
            ->assertRedirect(route('driver.login'));
        $this->assertSame(0, \App\Models\ParkingReservation::count());
    }

    public function test_the_old_plate_is_not_free_for_someone_else_and_the_driver_still_gets_the_notices(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [$driver, , $swap] = $this->driverWhoGetsThePermanentPlate();
        $this->violation('T1234', $manager);
        $swap();

        $this->assertTrue(Vehicle::plateTaken('T1234'));
        $this->assertSame($driver->id, app(\App\Services\NoticeService::class)->driverForPlate('T1234')?->id);

        $this->violation('T1234', $manager);
        $this->assertGreaterThanOrEqual(2, Notice::where('user_id', $driver->id)->where('kind', 'incident_warning')->count());
    }

    public function test_a_lifted_ban_restarts_the_count_for_the_whole_vehicle(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [, , $swap] = $this->driverWhoGetsThePermanentPlate();
        $bans = app(BanService::class);

        $this->violation('T1234', $manager);
        $this->violation('T1234', $manager);
        $swap();
        $this->violation('ABC1234', $manager);
        $this->assertTrue($bans->isBanned('ABC1234'));

        $bans->lift(BannedPlate::sole(), $manager, 'Settled with the manager.');
        $this->assertFalse($bans->isBanned('ABC1234'));
        $this->assertSame(0, $bans->count('ABC1234'));
        $this->assertSame(0, $bans->count('T1234'));
    }

    public function test_a_different_vehicle_of_the_same_driver_is_not_affected(): void
    {
        $manager = $this->user(User::ROLE_MANAGER, 'Elena Cruz');
        [$driver, , $swap] = $this->driverWhoGetsThePermanentPlate();
        $driver->vehicles()->create(['plate_number' => 'XYZ9876', 'color' => 'Red', 'vehicle_type' => 'Car', 'make_model' => 'Honda City']);
        foreach (range(1, 2) as $_) {
            $this->violation('T1234', $manager);
        }
        $swap();
        $this->violation('ABC1234', $manager);

        $bans = app(BanService::class);
        $this->assertTrue($bans->isBanned('ABC1234'));
        $this->assertFalse($bans->isBanned('XYZ9876'));
        $this->assertSame(0, $bans->count('XYZ9876'));
    }
}
