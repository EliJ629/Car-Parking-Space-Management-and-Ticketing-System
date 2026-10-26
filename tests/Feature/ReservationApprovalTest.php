<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\Lot;
use App\Support\PwdPhoto;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
    private function form(User $driver, string $slotCode, ?Carbon $at = null): array
    {
        $at ??= Lot::now()->addHours(3);

        return [
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'understand' => '1',
            'slot' => ParkingSlot::where('code', $slotCode)->value('id'), 'vehicle' => $driver->vehicles()->first()->id,
        ];
    }

    public function test_a_standard_slot_is_confirmed_at_once_and_the_attendant_has_nothing_to_approve(): void
    {
        $this->lot();
        $driver = $this->driver();
        $attendant = $this->attendant();

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05'))->assertRedirect();
        $reservation = ParkingReservation::firstOrFail();

        $this->assertSame('booked', $reservation->status);
        $this->assertSame('approved', $reservation->approval_status);
        $this->assertNull($reservation->approved_by);
        $this->assertFalse($reservation->requires_id);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-01')->exists());
        $this->assertFalse(Notice::where('user_id', $driver->id)->where('code', 'N-14')->exists());
        $this->assertFalse(Notice::where('audience_role', 'attendant')->where('code', 'S-06')->exists());

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))
            ->assertOk()->assertSee('data-qr=', false)->assertDontSee('QR code locked')->assertDontSee('Awaiting approval');

        // It never shows on the attendant's approval screen, and approving it is refused.
        $this->actingAs($attendant)->get(route('attendant.reservations.index'))->assertOk()->assertDontSee($reservation->code);
        $this->actingAs($attendant)->post(route('attendant.reservations.approve', $reservation))
            ->assertRedirect(route('attendant.reservations.index'))->assertSessionHas('modal.title', 'No approval needed');
        $this->assertNull($reservation->fresh()->approved_by);
    }

    public function test_an_old_waiting_standard_reservation_cannot_be_approved_by_hand(): void
    {
        $this->lot();
        $driver = $this->driver();
        $attendant = $this->attendant();
        $slot = ParkingSlot::where('code', 'A-05')->first();
        $reservation = ParkingReservation::create([
            'code' => 'RSV-OLD001', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-05', 'plate_number' => 'DEF2208',
            'scheduled_at' => now()->addHour(), 'status' => ParkingReservation::STATUS_BOOKED,
            'approval_status' => ParkingReservation::APPROVAL_PENDING, 'requires_id' => false,
        ]);

        $this->actingAs($attendant)->get(route('attendant.reservations.index'))->assertOk()->assertDontSee('RSV-OLD001');
        $this->actingAs($attendant)->post(route('attendant.reservations.approve', $reservation))->assertSessionHas('modal.title', 'No approval needed');
        $this->assertSame('pending', $reservation->fresh()->approval_status);

        // Even a direct call to the service refuses it.
        $this->assertFalse(app(ReservationService::class)->approve($reservation, $attendant));
    }

    public function test_the_migration_confirms_standard_reservations_that_were_waiting(): void
    {
        $this->lot();
        $driver = $this->driver();
        $slots = ParkingSlot::whereIn('code', ['A-05', 'A-01'])->get()->keyBy('code');
        $make = fn (string $code, string $slot, bool $pwd) => ParkingReservation::create([
            'code' => $code, 'driver_id' => $driver->id, 'slot_id' => $slots[$slot]->id, 'slot_code' => $slot, 'plate_number' => 'DEF2208',
            'scheduled_at' => now()->addHour(), 'status' => ParkingReservation::STATUS_BOOKED,
            'approval_status' => ParkingReservation::APPROVAL_PENDING, 'requires_id' => $pwd,
        ]);
        $standard = $make('RSV-MIG001', 'A-05', false);
        $pwd = $make('RSV-MIG002', 'A-01', true);

        (require base_path('database/migrations/2026_10_26_000004_confirm_standard_reservations.php'))->up();

        $this->assertSame('approved', $standard->fresh()->approval_status);
        $this->assertNotNull($standard->fresh()->approved_at);
        $this->assertSame('pending', $pwd->fresh()->approval_status);
    }

    public function test_a_pwd_reservation_waits_for_the_attendant_and_shows_no_qr_code(): void
    {
        $this->lot();
        $driver = $this->driver();

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-01') + ['id_photo' => $this->idPhoto()])->assertRedirect();
        $reservation = ParkingReservation::firstOrFail();

        $this->assertSame('booked', $reservation->status);
        $this->assertSame('pending', $reservation->approval_status);
        $this->assertTrue($reservation->requires_id);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-14')->exists());
        $this->assertFalse(Notice::where('user_id', $driver->id)->where('code', 'N-01')->exists());
        $this->assertTrue(Notice::where('audience_role', 'attendant')->where('code', 'S-06')->exists());

        $this->actingAs($driver)->get(route('driver.bookings.show', $reservation))
            ->assertOk()->assertSee('Awaiting approval')->assertSee('QR code locked')->assertDontSee('data-qr=', false);
        $this->actingAs($driver)->get(route('driver.bookings'))->assertOk()->assertSee('Awaiting approval');
    }

    public function test_the_qr_code_check_in_is_refused_until_the_attendant_approves_a_pwd_reservation(): void
    {
        $this->lot();
        $driver = $this->driver();
        $attendant = $this->attendant();
        $at = Lot::now()->addMinutes(10);

        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-01', $at) + ['id_photo' => $this->idPhoto()])->assertRedirect();
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

    public function test_a_photo_over_the_size_limit_is_refused_with_the_reason_inside_the_pop_up(): void
    {
        $this->lot();
        $driver = $this->driver();
        $big = UploadedFile::fake()->create('pwd-id.jpg', (int) (PwdPhoto::limitKb() + 400), 'image/jpeg');
        $form = $this->form($driver, 'A-01');
        $step3 = ['step' => 3, 'date' => $form['date'], 'time' => $form['time'], 'slot' => $form['slot'], 'vehicle' => $form['vehicle']];

        // The driver is sent back to the same step and the pop-up opens with the reason.
        $html = $this->actingAs($driver)->from(route('driver.reserve', $step3))->followingRedirects()
            ->post(route('driver.reserve.store'), $form + ['id_photo' => $big])
            ->assertOk()->getContent();
        $this->assertSame(0, ParkingReservation::count());

        $this->assertMatchesRegularExpression('/<dialog[^>]*id="confirm-res"[^>]*data-autoopen/', $html);
        $this->assertMatchesRegularExpression('/<dialog[^>]*id="confirm-res".*?id="id-photo-warning"[^>]*role="alert"[^>]*>(?:(?!hidden).)*?too large.*?<\/dialog>/s', $html);
        $this->assertStringContainsString('Photo not accepted', $html);
        $this->assertStringContainsString('data-max-bytes="'.PwdPhoto::limitBytes().'"', $html);
        // The old warning above the pop-up is gone, so the reason is shown once and in the pop-up.
        $this->assertSame(1, substr_count($html, PwdPhoto::tooLargeMessage()));
    }

    public function test_the_warning_box_is_hidden_until_there_is_something_to_say(): void
    {
        $this->lot();
        $driver = $this->driver();
        $form = $this->form($driver, 'A-01');
        $step3 = ['step' => 3, 'date' => $form['date'], 'time' => $form['time'], 'slot' => $form['slot'], 'vehicle' => $form['vehicle']];

        $html = $this->actingAs($driver)->get(route('driver.reserve', $step3))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="id-photo-warning"[^>]*hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/<dialog[^>]*id="confirm-res"[^>]*data-autoopen/', $html);

        // A standard slot has no photo field and no warning box.
        $std = $this->form($driver, 'A-05');
        $this->actingAs($driver)->get(route('driver.reserve', ['step' => 3, 'slot' => $std['slot']] + $step3))->assertOk()
            ->assertDontSee('id-photo-warning', false)->assertSee('Reserve slot');
    }

    public function test_a_request_too_big_for_php_returns_to_the_pop_up_with_the_warning(): void
    {
        $this->lot();
        $driver = $this->driver();
        $form = $this->form($driver, 'A-01');
        $step3 = route('driver.reserve', ['step' => 3, 'date' => $form['date'], 'time' => $form['time'], 'slot' => $form['slot'], 'vehicle' => $form['vehicle']]);

        // PHP drops the whole form when it is over post_max_size, so the handler turns that into a redirect.
        $request = Request::create(route('driver.reserve.store'), 'POST', [], [], [], ['HTTP_REFERER' => $step3]);
        $response = app(ExceptionHandler::class)->render($request, new PostTooLargeException);

        $this->assertTrue($response->isRedirection());
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('/driver/reserve?step=3', $target);
        $this->assertStringEndsWith('&photo=too_large', $target);

        // Another page's oversize upload is left to the normal error page.
        $other = Request::create('http://localhost/driver/vehicles', 'POST');
        $this->assertSame(413, app(ExceptionHandler::class)->render($other, new PostTooLargeException)->getStatusCode());

        $this->actingAs($driver)->get($target)->assertOk()
            ->assertSee(PwdPhoto::tooLargeMessage(), false)->assertSee('data-autoopen', false);
    }

    public function test_the_size_limit_follows_php_when_php_is_stricter(): void
    {
        $this->assertSame(2 * 1024 * 1024, PwdPhoto::iniBytes('2M'));
        $this->assertSame(512 * 1024, PwdPhoto::iniBytes('512K'));
        $this->assertSame(1024 ** 3, PwdPhoto::iniBytes('1G'));
        $this->assertSame(0, PwdPhoto::iniBytes('-1'));
        $this->assertLessThanOrEqual(5 * 1024 * 1024, PwdPhoto::limitBytes());
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? MB$/', PwdPhoto::limitLabel());
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

    public function test_booking_is_limited_to_24_hours_whatever_the_old_advance_days_setting_says(): void
    {
        $this->lot();
        $driver = $this->driver();

        SystemSetting::query()->where('key', 'maximum_advance_booking_days')->update(['value' => json_encode(9)]);

        $tooLate = Lot::now()->addHours(26);
        $fine = Lot::now()->addHours(20);
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', $tooLate))->assertSessionHas('modal');
        $this->assertSame(0, ParkingReservation::count());
        $this->actingAs($driver)->post(route('driver.reserve.store'), $this->form($driver, 'A-05', $fine))->assertRedirect();
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
