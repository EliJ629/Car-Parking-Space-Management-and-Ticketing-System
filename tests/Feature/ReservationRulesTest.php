<?php

namespace Tests\Feature;

use App\Models\ParkingReservation;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One reservation is one day and one arrival time. The server enforces it, not the screen. */
class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function setUpLot(int $advanceDays = 3): array
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'maximum_advance_booking_days'], ['value' => $advanceDays, 'group' => 'parking']);
        // Noon in the lot, so "today" and "tomorrow" mean the same thing wherever the test runs.
        $this->travelTo(Lot::now()->setTime(12, 0));

        $driver = User::create(['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '09175550142', 'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE]);
        $vehicle = $driver->vehicles()->create(['plate_number' => 'DEF2208', 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios']);

        return [$driver, $vehicle, ParkingSlot::where('code', 'A-03')->first()];
    }

    private function book(User $driver, $vehicle, $slot, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $at = Lot::now()->addHours(3);

        return $this->actingAs($driver)->post(route('driver.reserve.store'), array_merge([
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'slot' => $slot->id, 'vehicle' => $vehicle->id, 'understand' => '1',
        ], $extra));
    }

    public function test_a_normal_one_day_reservation_is_stored_as_one_arrival_time(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();

        $this->book($driver, $vehicle, $slot)->assertSessionHasNoErrors();

        $reservation = ParkingReservation::sole();
        $this->assertSame(Lot::now()->addHours(3)->format('Y-m-d H:i'), Lot::local($reservation->scheduled_at)->format('Y-m-d H:i'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('parking_reservations', 'end_at'), 'There is no end time: a reservation has no range.');
    }

    public function test_any_attempt_to_book_a_range_is_refused_and_nothing_is_saved(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $after = Lot::now()->addDays(2)->format('Y-m-d');

        foreach (['end_date' => $after, 'end_time' => '18:00', 'date_to' => $after, 'to_date' => $after, 'until' => $after, 'check_out' => $after,
            'days' => '3', 'nights' => '2', 'dates' => [$after], 'duration_days' => '2', 'repeat' => 'daily', 'recurring' => '1'] as $field => $value) {
            $this->book($driver, $vehicle, $slot, [$field => $value])->assertSessionHasErrors($field);
        }

        $this->assertSame(0, ParkingReservation::count());
    }

    public function test_impossible_dates_and_times_are_refused(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();

        foreach ([
            ['date' => '2026-02-31'], ['date' => '2026-13-01'], ['date' => 'tomorrow'], ['date' => '2026-1-5'], ['date' => ''],
            ['time' => '25:00'], ['time' => '12:60'], ['time' => '9:30'], ['time' => ''],
        ] as $bad) {
            $this->book($driver, $vehicle, $slot, $bad)->assertSessionHasErrors(array_key_first($bad));
        }

        $this->assertSame(0, ParkingReservation::count());
    }

    public function test_the_past_is_refused_and_now_or_later_today_is_accepted(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $today = Lot::now()->format('Y-m-d');

        $this->book($driver, $vehicle, $slot, ['date' => $today, 'time' => '11:59'])->assertSessionHasErrors('when');
        $this->book($driver, $vehicle, $slot, ['date' => Lot::now()->subDay()->format('Y-m-d'), 'time' => '18:00'])->assertSessionHasErrors('when');
        $this->assertSame(0, ParkingReservation::count());

        $this->book($driver, $vehicle, $slot, ['date' => $today, 'time' => '12:00'])->assertSessionHasNoErrors();
        $this->assertSame(1, ParkingReservation::count());
    }

    public function test_a_reservation_must_fall_inside_the_next_24_hours(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $this->travelTo(Lot::now()->setTime(2, 0));

        // 2:00 AM today to 2:00 AM tomorrow is the window. One minute later is out; the boundary itself is in.
        $past = Lot::now()->addDay()->addMinute();
        $this->book($driver, $vehicle, $slot, ['date' => $past->format('Y-m-d'), 'time' => $past->format('H:i')])->assertSessionHasErrors('when');
        $this->assertSame(0, ParkingReservation::count());

        $edge = Lot::now()->addDay();
        $this->book($driver, $vehicle, $slot, ['date' => $edge->format('Y-m-d'), 'time' => $edge->format('H:i')])->assertSessionHasNoErrors();
        $this->assertSame(1, ParkingReservation::count());
    }

    public function test_the_time_picker_works_out_the_date_and_offers_no_other_date(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $this->travelTo(Lot::now()->setTime(14, 0));

        $page = $this->actingAs($driver)->get(route('driver.reserve'))->assertOk()->getContent();
        $this->assertStringNotContainsString('other-date', $page);
        $this->assertStringNotContainsString('Or pick another date', $page);
        $this->assertStringContainsString('a window of 24 hours', $page);

        // 9:00 AM has passed today, so it means tomorrow at 9:00 AM; 4:00 PM means today.
        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2, 'time' => '09:00']))->assertOk()
            ->assertSee('name="date" value="'.Lot::now()->addDay()->format('Y-m-d').'"', false);
        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2, 'time' => '16:00']))->assertOk()
            ->assertSee('name="date" value="'.Lot::now()->format('Y-m-d').'"', false);
    }

    public function test_a_day_boundary_reservation_stays_on_its_own_day(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $tomorrow = Lot::now()->addDay()->format('Y-m-d');

        $this->book($driver, $vehicle, $slot, ['date' => $tomorrow, 'time' => '00:00'])->assertSessionHasNoErrors();

        $stored = Lot::local(ParkingReservation::sole()->scheduled_at);
        $this->assertSame($tomorrow, $stored->format('Y-m-d'), 'Midnight in the lot is still that day, not the day before in UTC.');
        $this->assertSame('00:00', $stored->format('H:i'));
    }

    public function test_the_same_bay_and_time_cannot_be_booked_twice(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $other = User::create(['name' => 'Juan Cruz', 'email' => 'juan@example.com', 'phone' => '09175550143', 'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE]);
        $otherCar = $other->vehicles()->create(['plate_number' => 'XYZ9876', 'color' => 'Red', 'vehicle_type' => 'Car', 'make_model' => 'Honda City']);

        $this->book($driver, $vehicle, $slot)->assertSessionHasNoErrors();
        $this->book($other, $otherCar, $slot)->assertSessionHas('modal');

        $this->assertSame(1, ParkingReservation::count());
    }

    public function test_the_same_driver_cannot_hold_two_reservations(): void
    {
        [$driver, $vehicle, $slot] = $this->setUpLot();
        $second = ParkingSlot::where('code', 'A-07')->first();

        $this->book($driver, $vehicle, $slot)->assertSessionHasNoErrors();
        $this->book($driver, $vehicle, $second, ['time' => '15:00'])->assertSessionHas('modal');
        $this->assertSame(1, ParkingReservation::count());
    }
}
