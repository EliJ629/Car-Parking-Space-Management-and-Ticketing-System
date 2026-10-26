<?php

namespace Tests\Feature;

use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\Lot;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A slot only shows Reserved when an approved booking is about to arrive; every board draws the Manager's layout; one receipt format. */
class SlotBoardsAndHoldsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role, string $email = null): User
    {
        $email ??= $role.'@example.com';

        return User::create(['name' => ucfirst($role).' Person', 'username' => strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0])), 'email' => $email, 'phone' => '0917'.random_int(1000000, 9999999),
            'password' => 'Pass123_x!', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function driver(): User
    {
        $d = $this->user(User::ROLE_DRIVER, 'maria.santos@gmail.com');
        $d->vehicles()->create(['plate_number' => 'DEF2208', 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Vios']);

        return $d;
    }

    private function reserve(User $driver, string $slotCode, \Carbon\CarbonInterface $at, bool $photo = false)
    {
        return $this->actingAs($driver)->post(route('driver.reserve.store'), [
            'date' => $at->format('Y-m-d'), 'time' => $at->format('H:i'), 'understand' => '1',
            'slot' => ParkingSlot::where('code', $slotCode)->value('id'), 'vehicle' => $driver->vehicles()->first()->id,
        ] + ($photo ? ['id_photo' => UploadedFile::fake()->createWithContent('id.png', base64_decode(self::PNG))] : []));
    }

    public function test_a_booking_for_later_does_not_touch_the_live_slot_until_its_hold_window_starts(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->travelTo(Lot::now()->setTime(10, 0));

        $this->reserve($driver, 'A-05', Lot::now()->addHours(6))->assertRedirect();
        $slot = ParkingSlot::where('code', 'A-05')->first();

        $this->assertSame('available', $slot->status, 'Six hours ahead, the slot is not shown as taken.');
        $this->assertSame(0, app(ReservationService::class)->holdTodaysSlots());

        $this->travelTo(Lot::now()->setTime(15, 50));
        $this->assertSame(1, app(ReservationService::class)->holdTodaysSlots(), 'Inside the hold window the slot shows Reserved.');
        $this->assertSame('reserved', $slot->fresh()->status);
    }

    public function test_a_booking_about_to_arrive_holds_the_slot_at_once(): void
    {
        $this->lot();
        $driver = $this->driver();

        $this->reserve($driver, 'A-05', Lot::now()->addMinutes(10))->assertRedirect();

        $this->assertSame('reserved', ParkingSlot::where('code', 'A-05')->value('status'));
    }

    public function test_a_pwd_request_is_not_reserved_until_the_attendant_approves_it(): void
    {
        $this->lot();
        $driver = $this->driver();
        $att = $this->user(User::ROLE_ATTENDANT);

        // Arrives in ten minutes: an ordinary booking would hold the slot immediately.
        $this->reserve($driver, 'A-01', Lot::now()->addMinutes(10), photo: true)->assertRedirect();
        $reservation = ParkingReservation::sole();

        $this->assertSame('pending', $reservation->approval_status);
        $this->assertSame('available', ParkingSlot::where('code', 'A-01')->value('status'), 'A pending PWD request does not mark the slot Reserved.');

        // Others see it as awaiting approval, not Reserved, and cannot pick it.
        $board = app(ReservationService::class)->boardAt(now()->addMinutes(10));
        $this->assertSame('awaiting_approval', $board->firstWhere('code', 'A-01')->state);

        // A walk-in cannot be put in the slot while the request is waiting.
        $this->assertTrue(ParkingReservation::slotHeldNow(ParkingSlot::where('code', 'A-01')->value('id'), true));
        $this->assertFalse(ParkingReservation::slotHeldNow(ParkingSlot::where('code', 'A-01')->value('id')));

        $this->actingAs($att)->post(route('attendant.reservations.approve', $reservation))->assertRedirect();

        $this->assertSame('approved', $reservation->fresh()->approval_status);
        $this->assertSame('reserved', ParkingSlot::where('code', 'A-01')->value('status'), 'Approved and about to arrive: now it shows Reserved.');
    }

    public function test_a_declined_pwd_request_never_marks_the_slot(): void
    {
        $this->lot();
        $driver = $this->driver();
        $att = $this->user(User::ROLE_ATTENDANT);

        $this->reserve($driver, 'A-01', Lot::now()->addMinutes(10), photo: true)->assertRedirect();
        $reservation = ParkingReservation::sole();
        $this->actingAs($att)->post(route('attendant.reservations.reject', $reservation), ['reason' => 'The photo is not readable.'])->assertRedirect();

        $this->assertSame('available', ParkingSlot::where('code', 'A-01')->value('status'));
    }

    public function test_every_slot_board_draws_the_managers_layout(): void
    {
        $this->lot();
        $driver = $this->driver();
        $guard = $this->user(User::ROLE_GUARD);
        $slot = ParkingSlot::where('code', 'A-03')->first();
        $slot->update(['layout_x' => 0.5, 'layout_y' => 0.25]);

        $session = ParkingSession::create(['ticket_number' => 'PK-0001', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-03',
            'attendant_id' => $this->user(User::ROLE_ATTENDANT)->id, 'plate_number' => 'DEF2208', 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subHour(), 'status' => ParkingSession::STATUS_ACTIVE]);
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        $free = $this->user(User::ROLE_DRIVER, 'free.driver@gmail.com');
        $free->vehicles()->create(['plate_number' => 'XYZ9876', 'color' => 'Red', 'vehicle_type' => 'Car', 'make_model' => 'City']);

        $pages = [
            'find' => $this->actingAs($driver)->get(route('driver.find'))->getContent(),
            'reserve' => $this->actingAs($free)->get(route('driver.reserve', ['step' => 2, 'time' => Lot::now()->addHour()->format('H:i')]))->getContent(),
            'locate' => $this->actingAs($driver)->get(route('driver.session.locate'))->getContent(),
            'guard' => $this->actingAs($guard)->get(route('guard.slots'))->getContent(),
        ];

        foreach ($pages as $name => $html) {
            $this->assertStringContainsString('data-layout-canvas', $html, "$name draws the shared layout canvas");
            $this->assertStringContainsString('data-slot-id="'.$slot->id.'"', $html, "$name has the slot");
            $this->assertMatchesRegularExpression('/data-slot-id="'.$slot->id.'"[^>]*data-x="0\.5" data-y="0\.25"/', $html, "$name uses the Manager's coordinates");
            $this->assertStringNotContainsString('class="g"', $html, "$name no longer uses the old plain grid");
        }

        $this->assertStringContainsString('slot-layout-slot occ', $pages['guard']);
    }

    public function test_the_reserve_board_labels_pwd_slots_and_cannot_pick_waiting_ones(): void
    {
        $this->lot();
        $driver = $this->driver();
        $this->reserve($driver, 'A-01', Lot::now()->addHours(2), photo: true)->assertRedirect();
        $other = $this->user(User::ROLE_DRIVER, 'juan.cruz@gmail.com');
        $other->vehicles()->create(['plate_number' => 'XYZ9876', 'color' => 'Red', 'vehicle_type' => 'Car', 'make_model' => 'City']);

        $html = $this->actingAs($other)->get(route('driver.reserve', ['step' => 2, 'time' => Lot::now()->addHours(2)->format('H:i')]))->assertOk()->getContent();

        $this->assertStringContainsString('Awaiting approval', $html);
        $this->assertMatchesRegularExpression('/value="'.ParkingSlot::where('code', 'A-01')->value('id').'" disabled/', $html);
        $this->assertStringContainsString('pwdtag', $html);
    }

    public function test_the_driver_receipt_is_the_printed_receipt(): void
    {
        $this->lot();
        $driver = $this->driver();
        $att = $this->user(User::ROLE_ATTENDANT);
        $slot = ParkingSlot::where('code', 'A-04')->first();

        $session = ParkingSession::create(['ticket_number' => 'PK-0007', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-04',
            'attendant_id' => $att->id, 'plate_number' => 'DEF2208', 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(3), 'exited_at' => now(), 'status' => ParkingSession::STATUS_COMPLETED]);
        $payment = ParkingPayment::create(['receipt_number' => 'RC-0007', 'session_id' => $session->id, 'received_by' => $att->id,
            'base_fee' => 30, 'additional_fee' => 40, 'penalty' => 0, 'discount' => 10, 'total' => 60, 'method' => 'cash', 'paid_at' => now()->subMinutes(2),
            'receipt_snapshot' => ['cash_received' => 100, 'change_given' => 40, 'discount_name' => 'Senior citizen']]);

        $driverHtml = $this->actingAs($driver)->get(route('driver.receipt', $payment))->assertOk()->getContent();
        $printed = view('partials.receipt-slip', ['payment' => $payment->fresh(), 'session' => $session])->render();

        // Every line of the printed slip appears, in the same order, on the driver's screen.
        preg_match_all('/<(?:span|b|h4|p)[^>]*>([^<]+)<\/(?:span|b|h4|p)>/', $printed, $lines);
        $position = 0;

        foreach ($lines[1] as $text) {
            $found = strpos($driverHtml, $text, $position);
            $this->assertNotFalse($found, "Missing or out of order: {$text}");
            $position = $found;
        }

        foreach (['Official receipt', 'Base fee (first hour)', 'Discount (Senior citizen)', 'Total paid', 'Cash received', 'Change', 'RC-0007', 'PK-0007'] as $label) {
            $this->assertStringContainsString($label, $driverHtml);
        }

        // The booth's own receipt page uses the same slip.
        $this->actingAs($att)->get(route('attendant.exit.receipt', $payment))->assertOk()->assertSee('Base fee (first hour)')->assertSee('Discount (Senior citizen)');
    }

    public function test_the_gate_search_has_no_report_incident_button(): void
    {
        $this->lot();
        $guard = $this->user(User::ROLE_GUARD);

        $none = $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => 'ELI 8888']))->assertOk()->getContent();
        $this->assertStringContainsString('No session found', $none);
        $this->assertStringNotContainsString('Report incident', $none);
        $this->assertStringContainsString('Search again', $none);
    }
}
