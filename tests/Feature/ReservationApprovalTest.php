<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A reservation waits for the booth attendant. The QR code and check in work only after approval. PWD slots need an ID photo. */
class ReservationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        Storage::fake('local');
    }

    private function driver(string $email = 'maria.santos@gmail.com', string $plate = 'DEF2208'): User
    {
        $driver = User::create([
            'name' => 'Maria Santos '.substr(md5($email), 0, 2), 'email' => $email, 'phone' => '09175550142',
            'password' => 'Driver123_!', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE,
        ]);
        $driver->vehicles()->create(['plate_number' => $plate, 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios']);

        return $driver;
    }

    private function attendant(): User
    {
        return User::create([
            'name' => 'Booth Attendant', 'username' => 'attendant1', 'email' => 'att@example.com', 'phone' => '09170000001',
            'password' => 'Staff123_', 'role' => User::ROLE_ATTENDANT, 'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function idPhoto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pwd-id.png', base64_decode(self::PNG));
    }

    /** @return array<string, mixed> */
    private function form(User $driver, string $slotCode, ?\Illuminate\Support\Carbon $at = null): array
    {
        $at ??= Lot::now()->addHours(3);

        return [
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'understand' => '1',
            'slot' => ParkingSlot::where('code', $slotCode)->value('id'), 'vehicle' => $driver->vehicles()->first()->id,
        ];
    }

    public function test_a_new_reservation_waits_for_the_attendant_and_shows_no_qr_code(): void
    {
        $this->lot();
        $driver = $this->driver();

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05'))->assertRedirect();
        $reservation = ParkingReservation::firstOrFail();

        $this->assertSame('booked', $reservation->status);
        $this->assertSame('pending', $reservation->approval_status);
        $this->assertFalse($reservation->requires_id);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-14')->exists());
        $this->assertFalse(Notice::where('user_id', $driver->id)->where('code', 'N-01')->exists());
        $this->assertTrue(Notice::where('audience_role', 'attendant')->where('code', 'S-06')->exists());

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))
            ->assertOk()->assertSee('Awaiting approval')->assertSee('QR code locked')->assertDontSee('data-qr=', false);
        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee('Awaiting approval');
    }

    public function test_the_qr_code_check_in_is_refused_until_the_attendant_approves(): void
    {
        $this->lot();
        $driver = $this->driver();
        $attendant = $this->attendant();
        $at = Lot::now()->addMinutes(10);

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', $at))->assertRedirect();
        $reservation = ParkingReservation::firstOrFail();

        $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), ['code' => $reservation->code])
            ->assertOk()->assertJson(['ok' => false, 'status' => 'awaiting_approval']);
        $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), ['reservation_id' => $reservation->id, 'entered_plate' => 'DEF 2208'])
            ->assertSessionHas('modal');
        $this->assertSame('booked', $reservation->fresh()->status);

        $this->actingAs($attendant)->post(route('attendant.reservations.approve', $reservation))->assertRedirect(route('attendant.reservations.index'));
        $reservation->refresh();
        $this->assertSame('approved', $reservation->approval_status);
        $this->assertSame($attendant->id, $reservation->approved_by);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-01')->exists());

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))->assertOk()->assertSee('data-qr=', false)->assertSee('Cancel reservation');
        $this->actingAs($attendant)->postJson(route('attendant.entry.reserved.lookup'), ['code' => $reservation->code])
            ->assertOk()->assertJson(['ok' => true, 'status' => 'confirmed']);
        $this->actingAs($attendant)->post(route('attendant.entry.reserved.check-in'), ['reservation_id' => $reservation->id, 'entered_plate' => 'DEF 2208'])
            ->assertRedirect();
        $this->assertSame('checked_in', $reservation->fresh()->status);
    }

    public function test_a_pwd_slot_needs_an_id_photo_that_is_really_a_photo(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->actingAs($driver);

        $this->post(route('driver.reserve.store'), $this->form($driver, 'A-01'))->assertSessionHasErrors('id_photo');
        $this->post(route('driver.reserve.store'), $this->form($driver, 'A-01') + ['id_photo' => UploadedFile::fake()->create('id.pdf', 30, 'application/pdf')])
            ->assertSessionHasErrors('id_photo');
        $this->assertSame(0, ParkingReservation::count());

        $this->post(route('driver.reserve.store'), $this->form($driver, 'A-01') + ['id_photo' => $this->idPhoto()])->assertRedirect();
        $reservation = ParkingReservation::firstOrFail();

        $this->assertTrue($reservation->requires_id);
        $this->assertNotNull($reservation->id_photo_path);
        Storage::disk('local')->assertExists($reservation->id_photo_path);
    }

    public function test_only_booth_staff_can_open_the_id_photo(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-01') + ['id_photo' => $this->idPhoto()]);
        $reservation = ParkingReservation::firstOrFail();

        $attendant = $this->attendant();
        $this->actingAs($attendant)->get(route('attendant.reservations.id-photo', $reservation))->assertOk();
        $this->actingAs($attendant)->get(route('attendant.reservations.index'))->assertOk()->assertSee($reservation->code)->assertSee('PWD slot, ID needed');

        auth()->logout();
        $this->actingAs($driver)->get(route('attendant.reservations.id-photo', $reservation))->assertRedirect();
    }

    public function test_rejecting_tells_the_driver_frees_the_slot_and_lets_them_choose_again(): void
    {
        $this->lot();
        $driver = $this->driver();
        $attendant = $this->attendant();
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-01') + ['id_photo' => $this->idPhoto()]);
        $reservation = ParkingReservation::firstOrFail();
        $path = $reservation->id_photo_path;

        // A reason is required.
        $this->actingAs($attendant)->post(route('attendant.reservations.reject', $reservation), [])->assertSessionHasErrors('reason');
        $this->actingAs($attendant)->post(route('attendant.reservations.reject', $reservation), ['reason' => 'other', 'reason_other' => ''])->assertSessionHas('modal');
        $this->assertSame('booked', $reservation->fresh()->status);

        $this->actingAs($attendant)->post(route('attendant.reservations.reject', $reservation), ['reason' => 'other', 'reason_other' => 'The ID has expired.'])
            ->assertRedirect(route('attendant.reservations.index'))->assertSessionHas('modal');

        $reservation->refresh();
        $this->assertSame('rejected', $reservation->status);
        $this->assertSame('rejected', $reservation->approval_status);
        $this->assertSame('The ID has expired.', $reservation->rejection_reason);
        $this->assertNull($reservation->id_photo_path);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('available', ParkingSlot::where('code', 'A-01')->value('status'));

        $notice = Notice::where('user_id', $driver->id)->where('code', 'N-15')->firstOrFail();
        $this->assertStringContainsString('choose a new slot', $notice->body);
        $this->assertStringContainsString('The ID has expired', $notice->body);

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))->assertOk()->assertSee('Choose a new slot')->assertSee('NOT APPROVED');
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05'))->assertRedirect();
        $this->assertSame(2, ParkingReservation::count());

        // Deciding twice changes nothing.
        $this->actingAs($attendant)->post(route('attendant.reservations.approve', $reservation))->assertSessionHas('modal');
        $this->assertSame('rejected', $reservation->fresh()->status);
    }

    public function test_a_paid_checked_in_booking_shows_pending_exit(): void
    {
        $this->lot();
        $driver = $this->driver();
        $slot = ParkingSlot::where('code', 'A-05')->first();
        $session = ParkingSession::create([
            'ticket_number' => 'PK-9100', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-05', 'plate_number' => 'DEF2208',
            'vehicle_color' => 'White', 'vehicle_type' => 'Car', 'entered_at' => now()->subHour(), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);
        $reservation = ParkingReservation::create([
            'code' => 'RSV-PEND01', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-05', 'plate_number' => 'DEF2208',
            'scheduled_at' => now()->subHour(), 'status' => ParkingReservation::STATUS_CHECKED_IN, 'session_id' => $session->id,
        ]);

        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee('Checked in')->assertDontSee('Pending exit');

        $session->update(['status' => ParkingSession::STATUS_PAID]);
        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee('Pending exit')->assertDontSee('>Checked in<', false);
        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))->assertOk()->assertSee('Pending exit');
    }

    public function test_advance_booking_is_one_or_two_days_as_the_manager_sets_it(): void
    {
        $this->lot();
        $driver = $this->driver();

        SystemSetting::query()->where('key', 'maximum_advance_booking_days')->update(['value' => json_encode(9)]);
        $this->assertSame(2, Lot::advanceDays(), 'A saved value above 2 is held at 2.');

        SystemSetting::query()->where('key', 'maximum_advance_booking_days')->update(['value' => json_encode(1)]);
        $this->assertSame(1, Lot::advanceDays());

        $tomorrow = Lot::now()->addDay()->setTime(10, 0);
        $dayAfter = Lot::now()->addDays(2)->setTime(10, 0);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', $dayAfter))->assertSessionHas('modal');
        $this->assertSame(0, ParkingReservation::count());
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', $tomorrow))->assertRedirect();
        $this->assertSame(1, ParkingReservation::count());
    }

    public function test_a_time_that_has_already_passed_today_cannot_be_chosen(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->travelTo(Lot::now()->setTime(14, 0));

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', Lot::now()->setTime(9, 0)))->assertSessionHasErrors('when');
        $this->assertSame(0, ParkingReservation::count());
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', Lot::now()->setTime(16, 0)))->assertRedirect();
    }
}
