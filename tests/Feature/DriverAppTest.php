<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAppTest extends TestCase
{
    use RefreshDatabase;

    private function driver(string $email = 'maria@example.com', ?string $plate = 'DEF2208'): User
    {
        $driver = User::create([
            'name' => 'Maria Santos', 'email' => $email, 'phone' => '09175550142',
            'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE,
        ]);

        if ($plate) {
            $driver->vehicles()->create(['plate_number' => $plate, 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios']);
        }

        return $driver;
    }

    /** The tick box on the review screen: the driver agrees to arrive on time. */
    private function ack(): array
    {
        return ['understand' => '1'];
    }

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
    }

    private function attendant(): User
    {
        return User::create([
            'name' => 'Booth Attendant', 'username' => 'attendant1', 'email' => 'att@example.com', 'phone' => '09170000001',
            'password' => 'Staff123_', 'role' => User::ROLE_ATTENDANT, 'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function parkedSession(User $driver, string $slotCode = 'A-04', string $plate = 'DEF2208', int $minutesAgo = 135): ParkingSession
    {
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        return ParkingSession::create([
            'ticket_number' => 'PK-9001', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => $slotCode,
            'attendant_id' => $this->attendant()->id, 'plate_number' => $plate, 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subMinutes($minutesAgo), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);
    }

    public function test_every_driver_screen_renders_for_a_driver_with_a_vehicle(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();

        foreach ([
            route('driver.home'), route('driver.find'), route('driver.reserve'), route('driver.bookings'), route('driver.bookings', ['tab' => 'past']),
            route('driver.session'), route('driver.history'), route('driver.notices'), route('driver.vehicles'), route('driver.vehicles.create'),
            route('driver.vehicles.edit', $vehicle), route('driver.account'), route('driver.account.profile'), route('driver.account.password'),
        ] as $url) {
            $this->actingAs($driver)->get($url)->assertOk();
        }

        $this->actingAs($driver)->get(route('driver.home'))->assertSee('Good')->assertSee('Reserve a slot');
        $this->actingAs($driver)->get(route('driver.find'))->assertSee('Zone A')->assertSee('A-01');
    }

    public function test_a_driver_with_no_vehicle_is_asked_to_add_one(): void
    {
        $this->lot();
        $driver = $this->driver('new@example.com', null);

        $this->actingAs($driver)->get(route('driver.home'))->assertOk()->assertSee('Add your vehicle');
        $this->actingAs($driver)->get(route('driver.vehicles'))->assertOk()->assertSee('Add your first vehicle');
    }

    public function test_guests_and_other_roles_cannot_open_the_driver_app(): void
    {
        $this->lot();
        $this->get(route('driver.find'))->assertRedirect();
        $this->actingAs($this->attendant())->get(route('driver.find'))->assertRedirect();
    }

    public function test_reservation_flow_creates_shows_and_cancels(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();
        $slot = ParkingSlot::where('code', 'A-03')->first();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')];

        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2] + $q))->assertOk()->assertSee('A-03')->assertSee('DEF 2208');
        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 3, 'slot' => $slot->id, 'vehicle' => $vehicle->id] + $q))->assertOk()->assertSee('Confirm reservation')->assertSee('Free');

        $response = $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => $slot->id, 'vehicle' => $vehicle->id]);
        $reservation = ParkingReservation::first();
        $this->assertNotNull($reservation);
        $response->assertRedirect(route('driver.bookings.show', [$reservation, 'new' => 1]));
        $this->assertSame('booked', $reservation->status);

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))->assertOk()->assertSee($reservation->code)->assertSee('Cancel reservation');
        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee($reservation->code);
        $this->actingAs($driver)->get(route('driver.home'))->assertOk()->assertSee('Your next reservation');
        // A standard slot needs no approval, so the driver gets the confirmation straight away.
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-01')->exists());
        $this->assertFalse(Notice::where('user_id', $driver->id)->where('code', 'N-14')->exists());

        $this->actingAs($driver)->post(route('driver.bookings.cancel', $reservation))->assertRedirect(route('driver.bookings.show', $reservation));
        $this->assertSame('canceled', $reservation->fresh()->status);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-04')->exists());
        $this->actingAs($driver)->get(route('driver.bookings', ['tab' => 'past']))->assertSee($reservation->code);
    }

    public function test_a_slot_taken_by_someone_else_shows_the_pop_up(): void
    {
        $this->lot();
        $first = $this->driver();
        $second = $this->driver('juan@example.com', 'XYZ9876');
        $slot = ParkingSlot::where('code', 'A-05')->first();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'slot' => $slot->id];

        $this->actingAs($first)->post(route('driver.reserve.store'), $this->ack() + $q + ['vehicle' => $first->vehicles()->first()->id])->assertRedirect();
        $response = $this->actingAs($second)->post(route('driver.reserve.store'), $this->ack() + $q + ['vehicle' => $second->vehicles()->first()->id]);
        $response->assertRedirect()->assertSessionHas('modal');
        $this->assertStringContainsString('just taken', session('modal')['title']);
        $this->assertSame(1, ParkingReservation::count());
    }

    public function test_a_driver_with_a_booking_cannot_reserve_again(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'vehicle' => $vehicle->id];

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => ParkingSlot::where('code', 'A-07')->value('id')])->assertRedirect();
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => ParkingSlot::where('code', 'A-08')->value('id')])->assertSessionHas('modal');
        $this->assertStringContainsString('already have a reservation', session('modal')['title']);

        // A different day does not get around it, and a second vehicle does not either.
        $later = Lot::now()->addDays(2);
        $second = $driver->vehicles()->create(['plate_number' => 'GHI3300', 'color' => 'Red', 'vehicle_type' => 'Car', 'make_model' => 'Honda City']);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + ['date' => $later->format('Y-m-d'), 'time' => '10:00', 'vehicle' => $second->id, 'slot' => ParkingSlot::where('code', 'A-03')->value('id')])
            ->assertSessionHas('modal');
        $this->assertSame(1, ParkingReservation::count());
        $this->assertSame($driver->id, ParkingReservation::first()->driver_id);

        // The reserve screen explains it instead of showing the form.
        $this->actingAs($driver)->get(route('driver.reserve'))->assertOk()->assertSee('You already have a reservation')->assertDontSee('Choose a slot');
    }

    public function test_another_driver_is_not_blocked_by_someone_elses_booking(): void
    {
        $this->lot();
        $first = $this->driver();
        $second = $this->driver('juan@example.com', 'XYZ9876');
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')];

        $this->actingAs($first)->post(route('driver.reserve.store'), $this->ack() + $q + ['vehicle' => $first->vehicles()->first()->id, 'slot' => ParkingSlot::where('code', 'A-07')->value('id')])->assertRedirect();
        $this->actingAs($second)->post(route('driver.reserve.store'), $this->ack() + $q + ['vehicle' => $second->vehicles()->first()->id, 'slot' => ParkingSlot::where('code', 'A-08')->value('id')])->assertRedirect();
        $this->assertSame(2, ParkingReservation::count());
        $this->assertSame(1, ParkingReservation::where('driver_id', $second->id)->count());
    }

    public function test_a_driver_can_reserve_again_after_canceling(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'vehicle' => $vehicle->id];

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => ParkingSlot::where('code', 'A-07')->value('id')]);
        $this->actingAs($driver)->post(route('driver.bookings.cancel', ParkingReservation::first()));
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => ParkingSlot::where('code', 'A-08')->value('id')])->assertRedirect();
        $this->assertSame(2, ParkingReservation::count());
    }

    public function test_a_driver_who_is_parked_or_checked_in_cannot_reserve_until_they_exit(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();
        $session = $this->parkedSession($driver);
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'vehicle' => $vehicle->id, 'slot' => ParkingSlot::where('code', 'A-05')->value('id')];

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q)->assertSessionHas('modal');
        $this->assertStringContainsString('still parked', session('modal')['title']);
        $this->assertSame(0, ParkingReservation::count());
        $this->actingAs($driver)->get(route('driver.reserve'))->assertOk()->assertSee('Your car is still parked');

        // Paid but not yet through the gate still counts as parked.
        $session->update(['status' => ParkingSession::STATUS_PAID]);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q)->assertSessionHas('modal');

        // Once the guard lets the car out, the driver can book again.
        $session->update(['status' => ParkingSession::STATUS_COMPLETED, 'exited_at' => now()]);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $q)->assertRedirect();
        $this->assertSame(1, ParkingReservation::where('driver_id', $driver->id)->count());

        // A checked-in reservation blocks too, until it is completed.
        $reservation = ParkingReservation::first();
        $reservation->update(['status' => ParkingReservation::STATUS_CHECKED_IN]);
        $again = ['date' => Lot::now()->addDays(1)->format('Y-m-d'), 'time' => '09:00'] + $q;
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $again)->assertSessionHas('modal');
        $this->assertStringContainsString('already checked in', session('modal')['title']);
        $reservation->update(['status' => ParkingReservation::STATUS_COMPLETED]);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + $again)->assertRedirect();
        $this->assertSame(2, ParkingReservation::where('driver_id', $driver->id)->count());
    }

    public function test_the_vehicle_model_is_required_when_a_driver_signs_up(): void
    {
        $base = [
            'name' => 'Pedro Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '09171234567', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'color' => 'Black', 'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!',
        ];

        $this->get(route('driver.register'))->assertOk()->assertSee('Vehicle model')->assertDontSee('Truck');
        $this->post(route('driver.register'), $base)->assertSessionHasErrors('make_model');
        $this->assertSame(0, User::where('email', 'pedro.cruz@gmail.com')->count());

        \Illuminate\Support\Facades\Mail::fake();
        $this->post(route('driver.register'), $base + ['make_model' => 'Toyota Vios'])->assertRedirect(route('driver.register.sent'));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ConfirmRegistrationMail::class, fn ($mail) => $this->get($mail->link)->assertRedirect(route('driver.login')) !== null);
        $this->assertSame('Toyota Vios', Vehicle::where('plate_number', 'KLM4455')->value('make_model'));
    }

    public function test_the_reservation_flow_asks_for_time_slot_vehicle_and_an_on_time_promise(): void
    {
        $this->lot();
        $driver = $this->driver();
        $slot = ParkingSlot::where('code', 'A-06')->first();
        $vehicle = $driver->vehicles()->first();
        $at = Lot::now()->addHours(3);
        $q = ['date' => $at->format('Y-m-d'), 'time' => $at->format('H:i')];
        $grace = Lot::graceMinutes();

        $this->actingAs($driver)->get(route('driver.reserve'))
            ->assertOk()->assertSee('Arrive on time')->assertSee("{$grace} minutes");
        $this->get(route('driver.reserve', ['step' => 2] + $q))
            ->assertOk()->assertSee('1. Pick a slot')->assertSee('2. Pick the vehicle you will use')->assertSee('A-06');
        $this->get(route('driver.reserve', ['step' => 3, 'slot' => $slot->id, 'vehicle' => $vehicle->id] + $q))
            ->assertOk()->assertSee('arrive on time')->assertSee('Last check in')->assertSee('the reservation expires')
            ->assertSee(Lot::time($at->copy()->addMinutes($grace)), false);

        // Without the promise nothing is reserved.
        $this->post(route('driver.reserve.store'), $q + ['slot' => $slot->id, 'vehicle' => $vehicle->id])->assertSessionHasErrors('understand');
        $this->assertSame(0, ParkingReservation::count());

        $this->post(route('driver.reserve.store'), $this->ack() + $q + ['slot' => $slot->id, 'vehicle' => $vehicle->id])->assertRedirect();
        $this->assertSame(1, ParkingReservation::count());
        $this->get(route('driver.bookings.show', [ParkingReservation::first(), 'new' => 1]))->assertOk()->assertSee('Reservation confirmed')->assertSee('data-qr=', false);
    }

    public function test_a_date_too_far_ahead_is_refused(): void
    {
        $this->lot();
        $driver = $this->driver();
        $far = Lot::now()->addDays(9);

        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2, 'date' => $far->format('Y-m-d'), 'time' => '10:00']))
            ->assertRedirect()->assertSessionHas('modal');
    }

    public function test_a_past_time_is_refused(): void
    {
        $this->lot();
        $driver = $this->driver();
        $past = Lot::now()->subHours(2);

        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 2, 'date' => $past->format('Y-m-d'), 'time' => $past->format('H:i')]))
            ->assertRedirect()->assertSessionHasErrors('when');
    }

    public function test_active_session_shows_ticket_estimate_and_locator(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->parkedSession($driver, 'A-04', 'DEF2208', 135);

        $this->actingAs($driver)->get(route('driver.home'))->assertOk()->assertSee('PK-9001')->assertSee('A-04');
        // 135 minutes is 3 hours started: 30 + 2 x 20 = 70
        $this->actingAs($driver)->get(route('driver.session'))->assertOk()->assertSee('70.00')->assertSee('Locate my car');
        $this->actingAs($driver)->get(route('driver.session.locate'))->assertOk()->assertSee('Slot A-04');
    }

    public function test_paid_session_shows_the_pending_exit_state(): void
    {
        $this->lot();
        $driver = $this->driver();
        $session = $this->parkedSession($driver);
        $session->update(['status' => ParkingSession::STATUS_PAID]);
        ParkingPayment::create([
            'receipt_number' => 'RC-1001', 'session_id' => $session->id, 'received_by' => $session->attendant_id,
            'base_fee' => 30, 'additional_fee' => 40, 'penalty' => 0, 'discount' => 0, 'total' => 70, 'method' => 'cash',
            'receipt_snapshot' => ['cash_received' => 100, 'change' => 30], 'paid_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($driver)->get(route('driver.home'))->assertOk()->assertSee('Payment received');
        $this->actingAs($driver)->get(route('driver.session'))->assertOk()->assertSee('Pending exit')->assertSee('RC-1001');
    }

    public function test_history_and_receipt_belong_to_the_driver(): void
    {
        $this->lot();
        $driver = $this->driver();
        $other = $this->driver('other@example.com', 'ZZZ1111');
        $session = $this->parkedSession($driver);
        $session->update(['status' => ParkingSession::STATUS_COMPLETED, 'exited_at' => now()->subMinutes(30)]);
        $payment = ParkingPayment::create([
            'receipt_number' => 'RC-1002', 'session_id' => $session->id, 'received_by' => $session->attendant_id,
            'base_fee' => 30, 'additional_fee' => 40, 'penalty' => 0, 'discount' => 0, 'total' => 70, 'method' => 'cash',
            'receipt_snapshot' => ['cash_received' => 100, 'change' => 30], 'paid_at' => now()->subHour(),
        ]);

        $this->actingAs($driver)->get(route('driver.history'))->assertOk()->assertSee('RC-1002');
        $this->actingAs($driver)->get(route('driver.receipt', $payment))->assertOk()->assertSee('70.00')->assertSee('100.00')->assertSee('30.00');
        $this->actingAs($driver)->get(route('driver.session'))->assertOk()->assertSee('Parking completed');
        $this->actingAs($other)->get(route('driver.receipt', $payment))->assertNotFound();
    }

    public function test_a_driver_cannot_open_someone_elses_reservation(): void
    {
        $this->lot();
        $owner = $this->driver();
        $other = $this->driver('other@example.com', 'ZZZ1111');
        $at = Lot::now()->addHours(3);
        $this->actingAs($owner)->post(route('driver.reserve.store'), $this->ack() + [
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'),
            'slot' => ParkingSlot::where('code', 'A-07')->value('id'), 'vehicle' => $owner->vehicles()->first()->id,
        ]);
        $reservation = ParkingReservation::first();

        $this->actingAs($other)->get(route('driver.bookings.show', $reservation))->assertNotFound();
        $this->actingAs($other)->post(route('driver.bookings.cancel', $reservation))->assertNotFound();
    }

    public function test_vehicle_add_edit_duplicate_and_remove(): void
    {
        $this->lot();
        $driver = $this->driver('maria@example.com', null);
        $other = $this->driver('other@example.com', 'ZZZ1111');

        $this->actingAs($driver)->post(route('driver.vehicles.store'), ['plate_number' => 'abc 1234', 'color' => 'Blue', 'vehicle_type' => 'SUV', 'make_model' => 'Honda CR-V', 'contact_number' => '09175550142'])
            ->assertRedirect(route('driver.vehicles'));
        $vehicle = Vehicle::where('plate_number', 'ABC1234')->first();
        $this->assertNotNull($vehicle);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), ['plate_number' => 'ZZZ1111', 'color' => 'Blue', 'vehicle_type' => 'SUV', 'make_model' => 'Honda CR-V'])
            ->assertSessionHas('modal');
        $this->actingAs($driver)->post(route('driver.vehicles.store'), ['plate_number' => '', 'color' => '', 'vehicle_type' => '', 'make_model' => ''])
            ->assertSessionHasErrors(['plate_number', 'color', 'vehicle_type', 'make_model']);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), ['plate_number' => 'ABC1234', 'color' => 'Red', 'vehicle_type' => 'SUV', 'make_model' => 'Honda CR-V'])
            ->assertRedirect(route('driver.vehicles'));
        $this->assertSame('Red', $vehicle->fresh()->color);

        $this->actingAs($other)->get(route('driver.vehicles.edit', $vehicle))->assertNotFound();

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $vehicle))->assertRedirect(route('driver.vehicles'));
        $this->assertSoftDeleted($vehicle);
    }

    public function test_a_vehicle_with_a_booking_cannot_be_removed(): void
    {
        $this->lot();
        $driver = $this->driver();
        $vehicle = $driver->vehicles()->first();
        $at = Lot::now()->addHours(3);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->ack() + [
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'),
            'slot' => ParkingSlot::where('code', 'A-07')->value('id'), 'vehicle' => $vehicle->id,
        ]);

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $vehicle))->assertSessionHas('modal');
        $this->assertNotSoftDeleted($vehicle);
    }

    public function test_notifications_list_count_and_read(): void
    {
        $this->lot();
        $driver = $this->driver();
        $notice = Notice::create(['user_id' => $driver->id, 'code' => 'N-05', 'kind' => 'parking_started', 'title' => 'Parking started', 'body' => 'Your ticket is PK-9001.']);

        $this->actingAs($driver)->get(route('driver.notices'))->assertOk()->assertSee('Parking started');
        $this->actingAs($driver)->getJson(route('driver.notices.count'))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('latest.id', $notice->id);
        $this->actingAs($driver)->get(route('driver.notices.show', $notice))->assertOk()->assertSee('PK-9001');
        $this->actingAs($driver)->getJson(route('driver.notices.count'))->assertJsonPath('count', 0);
        $this->actingAs($this->driver('other@example.com', 'ZZZ1111'))->get(route('driver.notices.show', $notice))->assertNotFound();
    }

    public function test_profile_and_password_changes(): void
    {
        $this->lot();
        $driver = $this->driver();

        // The name is saved at once. A new phone number waits for the code that is emailed.
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAs($driver)->put(route('driver.account.profile.update'), ['name' => 'Maria S. Santos', 'email' => 'maria@example.com', 'phone' => '09175550199'])
            ->assertRedirect(route('driver.account.confirm'));
        $this->assertSame('Maria S. Santos', $driver->fresh()->name);
        $this->assertSame('09175550142', $driver->fresh()->phone);
        $this->assertSame('09175550199', $driver->fresh()->pending_phone);

        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\VerifyEmailMail::class, function ($mail) use (&$code, $driver) {
            $code = $mail->code;

            return $mail->hasTo($driver->email) && $mail->purpose === 'change';
        });
        $this->actingAs($driver)->post(route('driver.account.confirm.check'), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->actingAs($driver)->post(route('driver.account.confirm.check'), ['code' => $code])->assertRedirect(route('driver.account'))->assertSessionHas('modal');
        $this->assertSame('09175550199', $driver->fresh()->phone);
        $this->assertNull($driver->fresh()->pending_phone);

        $this->actingAs($driver)->put(route('driver.account.password.update'), ['current_password' => 'wrong', 'password' => 'Newpass123!', 'password_confirmation' => 'Newpass123!'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($driver)->put(route('driver.account.password.update'), ['current_password' => 'Driver123_', 'password' => 'Driver123_', 'password_confirmation' => 'Driver123_'])
            ->assertSessionHasErrors('password');
        $this->actingAs($driver)->put(route('driver.account.password.update'), ['current_password' => 'Driver123_', 'password' => 'Newpass123!', 'password_confirmation' => 'Newpass123!'])
            ->assertRedirect(route('driver.account'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Newpass123!', $driver->fresh()->password));
    }

    public function test_a_suspended_driver_only_sees_the_suspended_page(): void
    {
        $this->lot();
        $driver = $this->driver();
        $driver->update(['status' => User::STATUS_SUSPENDED]);

        $this->actingAs($driver)->get(route('driver.home'))->assertRedirect(route('driver.suspended'));
        $this->actingAs($driver)->get(route('driver.find'))->assertRedirect(route('driver.suspended'));
        $this->actingAs($driver)->get(route('driver.suspended'))->assertOk()->assertSee('suspended');
    }
}
