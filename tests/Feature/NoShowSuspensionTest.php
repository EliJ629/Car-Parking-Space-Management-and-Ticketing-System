<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSlot;
use App\Models\ReservationSuspension;
use App\Models\User;
use App\Services\NoShowService;
use App\Services\ReservationException;
use App\Services\ReservationService;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/** Three missed reservations stop the driver from reserving for 1 week; every further three make the pause longer. */
class NoShowSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function driver(): User
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(), 'phone' => '09175550142']);
        $driver->vehicles()->create(['plate_number' => 'ABC1234', 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'contact_number' => '09175550142']);

        return $driver;
    }

    /** A booked reservation whose time and grace have passed. */
    private function late(User $driver, string $approval = ParkingReservation::APPROVAL_APPROVED, string $status = ParkingReservation::STATUS_BOOKED): ParkingReservation
    {
        $this->n++;
        $slot = ParkingSlot::where('code', 'A-0'.(($this->n % 8) + 1))->firstOrFail();

        return ParkingReservation::create(['code' => 'RSV-'.str_pad((string) $this->n, 4, '0', STR_PAD_LEFT), 'driver_id' => $driver->id, 'vehicle_id' => $driver->vehicles()->first()->id,
            'slot_id' => $slot->id, 'slot_code' => $slot->code, 'plate_number' => 'ABC1234', 'scheduled_at' => now()->subHours(3), 'status' => $status, 'approval_status' => $approval]);
    }

    private function expire(): int
    {
        return app(ReservationService::class)->expireDue();
    }

    public function test_the_first_two_misses_only_warn(): void
    {
        $driver = $this->driver();
        $this->late($driver);
        $this->expire();
        $this->assertSame(1, app(NoShowService::class)->noShows($driver));
        $this->assertSame(0, ReservationSuspension::count());
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'no_show_warning')->count());

        $this->late($driver);
        $this->expire();
        $this->assertSame(0, ReservationSuspension::count());
        $this->assertSame(2, Notice::where('user_id', $driver->id)->where('kind', 'no_show_warning')->count());
    }

    public function test_the_third_miss_blocks_reserving_for_one_week(): void
    {
        $driver = $this->driver();
        foreach (range(1, 3) as $_) {
            $this->late($driver);
        }
        $this->expire();

        $block = ReservationSuspension::sole();
        $this->assertSame(3, $block->no_show_count);
        $this->assertSame(1, $block->tier);
        $this->assertEqualsWithDelta(7 * 86400, $block->starts_at->diffInSeconds($block->ends_at), 5);
        $this->assertSame(1, Notice::where('user_id', $driver->id)->where('kind', 'reservation_suspended')->count());
        $this->assertNotNull(app(NoShowService::class)->activeSuspension($driver));
    }

    public function test_a_used_reservation_breaks_the_run_and_a_block_starts_the_count_again(): void
    {
        $driver = $this->driver();
        $this->late($driver);
        $this->late($driver);
        $this->expire();
        $this->assertSame(2, app(NoShowService::class)->noShows($driver));

        // The driver then uses a reservation: two misses are not "three in a row" any more.
        $this->late($driver, ParkingReservation::APPROVAL_APPROVED, ParkingReservation::STATUS_COMPLETED);
        $this->late($driver);
        $this->expire();
        $this->assertSame(1, app(NoShowService::class)->noShows($driver));
        $this->assertSame(0, ReservationSuspension::count());

        $this->late($driver);
        $this->late($driver);
        $this->expire();
        $this->assertSame(1, ReservationSuspension::count());
        $this->assertSame(0, app(NoShowService::class)->noShows($driver), 'after a block the count starts again');
        $this->assertSame(7, app(NoShowService::class)->daysForTier(2));
    }

    public function test_cancelled_and_unanswered_reservations_do_not_count(): void
    {
        $driver = $this->driver();
        $this->late($driver, ParkingReservation::APPROVAL_PENDING);
        $this->late($driver, ParkingReservation::APPROVAL_APPROVED, ParkingReservation::STATUS_CANCELED);
        $this->late($driver, ParkingReservation::APPROVAL_REJECTED, ParkingReservation::STATUS_REJECTED);
        $this->expire();

        $this->assertSame(0, app(NoShowService::class)->noShows($driver));
        $this->assertSame(0, ReservationSuspension::count());
    }

    public function test_a_blocked_driver_cannot_reserve_and_sees_why(): void
    {
        $driver = $this->driver();
        foreach (range(1, 3) as $_) {
            $this->late($driver);
        }
        $this->expire();

        $slot = ParkingSlot::where('code', 'A-03')->firstOrFail();
        $at = Lot::now()->addHours(3);
        $vehicle = $driver->vehicles()->first();

        $this->actingAs($driver)->post(route('driver.reserve.store'), ['understand' => '1', 'slot' => $slot->id, 'vehicle' => $vehicle->id, 'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')])
            ->assertSessionHas('modal', fn ($m) => $m['title'] === 'Reservations are paused' && str_contains($m['body'], 'You missed 3 reservations in a row'));
        $this->assertSame(3, ParkingReservation::count());

        $this->actingAs($driver)->get(route('driver.reserve'))->assertOk()->assertSee('Reservations are paused')->assertSee('You can still park without a reservation');
    }

    public function test_the_service_throws_a_suspended_error(): void
    {
        $driver = $this->driver();
        foreach (range(1, 3) as $_) {
            $this->late($driver);
        }
        $this->expire();
        $slot = ParkingSlot::where('code', 'A-03')->firstOrFail();

        try {
            app(ReservationService::class)->create($driver, $driver->vehicles()->first(), $slot, Lot::now()->addHours(3));
            $this->fail('A blocked driver was allowed to reserve.');
        } catch (ReservationException $e) {
            $this->assertSame('suspended', $e->reason);
        }
    }

    public function test_the_block_ends_and_reserving_works_again(): void
    {
        $driver = $this->driver();
        foreach (range(1, 3) as $_) {
            $this->late($driver);
        }
        $this->expire();
        $this->assertNotNull(app(NoShowService::class)->activeSuspension($driver));

        $this->travel(8)->days();
        $this->assertNull(app(NoShowService::class)->activeSuspension($driver));

        $slot = ParkingSlot::where('code', 'A-03')->firstOrFail();
        $at = Lot::now()->addHours(3);
        $this->actingAs($driver)->post(route('driver.reserve.store'), ['understand' => '1', 'slot' => $slot->id, 'vehicle' => $driver->vehicles()->first()->id, 'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ParkingReservation::where('status', ParkingReservation::STATUS_BOOKED)->count());
    }

    public function test_running_the_expiry_twice_does_not_stack_blocks(): void
    {
        $driver = $this->driver();
        foreach (range(1, 3) as $_) {
            $this->late($driver);
        }
        $this->expire();
        $this->expire();
        $this->assertSame(1, ReservationSuspension::count());
    }

    public function test_another_driver_is_not_affected(): void
    {
        $a = $this->driver();
        $b = User::factory()->create(['role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        foreach (range(1, 3) as $_) {
            $this->late($a);
        }
        $this->expire();
        $this->assertNull(app(NoShowService::class)->activeSuspension($b));
    }
}
