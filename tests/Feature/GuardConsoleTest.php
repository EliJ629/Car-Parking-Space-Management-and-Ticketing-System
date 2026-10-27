<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LongParkingService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $name, string $email): User
    {
        return User::create(['name' => $name, 'username' => strtolower(explode('@', $email)[0]), 'email' => $email, 'phone' => '0917000'.random_int(1000, 9999),
            'password' => 'Pass123_x', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function guard(): User
    {
        return $this->user(User::ROLE_GUARD, 'Ben Aquino', 'guard@example.com');
    }

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
    }

    private function parked(string $ticket, string $plate, string $slotCode, string $status, int $minutesAgo = 60, ?User $driver = null): ParkingSession
    {
        $attendant = User::where('role', User::ROLE_ATTENDANT)->first() ?? $this->user(User::ROLE_ATTENDANT, 'Booth', 'att@example.com');
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => $status === 'paid' ? ParkingSlot::STATUS_PENDING_EXIT : ParkingSlot::STATUS_OCCUPIED]);

        $session = ParkingSession::create([
            'ticket_number' => $ticket, 'driver_id' => $driver?->id, 'slot_id' => $slot->id, 'slot_code' => $slotCode, 'attendant_id' => $attendant->id,
            'plate_number' => $plate, 'vehicle_color' => 'White', 'vehicle_type' => 'Van', 'entered_at' => now()->subMinutes($minutesAgo), 'status' => $status,
        ]);

        if ($status === 'paid') {
            ParkingPayment::create([
                'receipt_number' => 'RC-'.substr($ticket, 3), 'session_id' => $session->id, 'received_by' => $attendant->id,
                'base_fee' => 30, 'additional_fee' => 0, 'penalty' => 0, 'discount' => 0, 'total' => 30, 'method' => 'cash', 'paid_at' => now()->subMinutes(3),
            ]);
        }

        return $session->fresh();
    }

    public function test_every_guard_screen_renders(): void
    {
        $this->lot();
        $guard = $this->guard();
        $paid = $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');
        $this->parked('PK-0016', 'HJK4471', 'A-02', 'active');
        $incident = Incident::create(['code' => 'INC-0001', 'category' => 'Vehicle damage', 'source' => 'manual', 'status' => 'open', 'description' => 'Scratch on a door.', 'reported_by' => $guard->id, 'reported_at' => now()]);

        foreach ([
            route('guard.gate'), route('guard.gate', ['tab' => 'search']), route('guard.gate', ['tab' => 'search', 'q' => 'BNM 6628']),
            route('guard.gate', ['tab' => 'search', 'q' => 'HJK4471']), route('guard.gate', ['tab' => 'search', 'q' => 'ZZZ9999']),
            route('guard.gate.show', $paid), route('guard.incidents'), route('guard.incidents.create'),
            route('guard.incidents.show', $incident), route('guard.records'), route('guard.records', ['tab' => 'incidents']),
            route('guard.records', ['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31']), route('guard.slots'),
        ] as $url) {
            $this->actingAs($guard)->get($url)->assertOk();
        }

        $this->actingAs($guard)->get(route('guard.gate'))->assertSee('PK-0018')->assertSee('RC-0018')->assertSee('Review');
        $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => 'HJK4471']))->assertSee('Outstanding balance')->assertSee('Unpaid');
        $this->actingAs($guard)->get(route('guard.gate', ['tab' => 'search', 'q' => 'ZZZ9999']))->assertSee('No session found');
        $this->actingAs($guard)->getJson(route('guard.gate.feed'))->assertOk()->assertJsonPath('waiting', 1);
        $this->actingAs($guard)->getJson(route('guard.alerts'))->assertOk()->assertJsonStructure(['count', 'items']);
    }

    public function test_only_guards_can_use_the_console(): void
    {
        $this->lot();
        $this->get(route('guard.gate'))->assertRedirect();
        $this->actingAs($this->user(User::ROLE_ATTENDANT, 'Booth', 'a@example.com'))->get(route('guard.gate'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_MANAGER, 'Boss', 'm@example.com'))->get(route('guard.slots'))->assertForbidden();
    }

    public function test_good_to_go_needs_all_checks_then_releases_the_slot(): void
    {
        $this->lot();
        $guard = $this->guard();
        $driver = $this->user(User::ROLE_DRIVER, 'Maria', 'maria@example.com');
        $session = $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid', 60, $driver);

        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1]])
            ->assertRedirect(route('guard.gate.show', $session))->assertSessionHasErrors('checks');
        $this->assertSame('paid', $session->fresh()->status);
        $this->actingAs($guard)->get(route('guard.gate.show', $session))->assertOk();

        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]])
            ->assertRedirect(route('guard.gate.show', [$session, 'done' => 1]));

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame($guard->id, $session->exit_approved_by);
        $this->assertSame('available', ParkingSlot::where('code', 'A-07')->value('status'));
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-09')->exists());
        $this->assertTrue(ActivityLog::where('action', 'exit_approved')->exists());
        $this->actingAs($guard)->get(route('guard.gate.show', [$session, 'done' => 1]))->assertOk()->assertSee('Exit approved');
    }

    public function test_the_slot_goes_back_to_reserved_when_a_booking_for_today_holds_it(): void
    {
        $this->lot();
        $guard = $this->guard();
        $session = $this->parked('PK-0019', 'BNM6629', 'A-08', 'paid', 60);
        $slot = ParkingSlot::where('code', 'A-08')->first();
        ParkingReservation::create([
            'code' => 'RSV-9100', 'slot_id' => $slot->id, 'slot_code' => 'A-08', 'plate_number' => 'QRS3317',
            'scheduled_at' => now()->addMinutes(10), 'status' => 'booked', 'approval_status' => 'approved',
        ]);

        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]])
            ->assertRedirect(route('guard.gate.show', [$session, 'done' => 1]));

        $this->assertSame('reserved', $slot->fresh()->status);
    }

    public function test_an_unpaid_session_cannot_be_approved(): void
    {
        $this->lot();
        $guard = $this->guard();
        $session = $this->parked('PK-0016', 'HJK4471', 'A-02', 'active', 125);

        $this->actingAs($guard)->get(route('guard.gate.show', $session))->assertOk()->assertSee('Exit approval blocked');
        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]])->assertSessionHasErrors('checks');
        $this->assertSame('active', $session->fresh()->status);
        $this->assertSame('occupied', ParkingSlot::where('code', 'A-02')->value('status'));
    }

    public function test_an_ended_exit_window_blocks_approval_and_reopens_the_session(): void
    {
        $this->lot();
        $guard = $this->guard();
        $session = $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');
        $session->payment->update(['paid_at' => now()->subMinutes(20)]);

        $this->actingAs($guard)->post(route('guard.gate.approve', $session), ['checks' => ['plate' => 1, 'details' => 1, 'ticket' => 1]])
            ->assertRedirect(route('guard.gate'))->assertSessionHas('modal');
        $session->refresh();
        $this->assertSame('active', $session->status);
        $this->assertNotNull($session->exit_window_expired_at);
        $this->assertSame('occupied', ParkingSlot::where('code', 'A-07')->value('status'));
    }

    public function test_mismatch_logs_and_opens_a_prefilled_incident_form(): void
    {
        $this->lot();
        $guard = $this->guard();
        $session = $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');

        $response = $this->actingAs($guard)->post(route('guard.gate.mismatch', $session), ['reasons' => ['plate', 'ticket']]);
        $response->assertRedirect();
        $this->assertTrue(ActivityLog::where('action', 'exit_mismatch')->exists());
        $this->assertSame('paid', $session->fresh()->status);

        $this->followRedirects = false;
        $this->actingAs($guard)->get($response->headers->get('Location'))->assertOk()->assertSee('Mismatched plate or details')->assertSee('BNM6628')->assertSee('plate number and ticket number');
        $this->actingAs($guard)->post(route('guard.gate.mismatch', $session), [])->assertSessionHas('modal');
    }

    public function test_manual_incident_with_photos_and_driver_notice(): void
    {
        Storage::fake('local');
        $this->lot();
        $guard = $this->guard();
        $driver = $this->user(User::ROLE_DRIVER, 'Maria', 'maria@example.com');
        $this->parked('PK-0016', 'HJK4471', 'A-02', 'active', 125, $driver);

        $this->actingAs($guard)->post(route('guard.incidents.store'), ['category' => '', 'description' => 'short'])->assertSessionHasErrors(['category', 'description']);

        $response = $this->actingAs($guard)->post(route('guard.incidents.store'), [
            'category' => 'Vehicle damage', 'description' => 'Scratch along the left door.', 'plate_number' => 'hjk 4471', 'location' => 'a-02',
            'responsible_unknown' => 1,
            'photos' => [UploadedFile::fake()->create('a.jpg', 20, 'image/jpeg'), UploadedFile::fake()->create('b.png', 20, 'image/png')],
        ]);

        $incident = Incident::where('category', 'Vehicle damage')->first();
        $this->assertNotNull($incident);
        $response->assertRedirect(route('guard.incidents.show', $incident))->assertSessionHas('modal');
        $this->assertSame('HJK4471', $incident->plate_number);
        $this->assertSame('A-02', $incident->location);
        $this->assertSame(2, $incident->photos()->count());
        $this->assertTrue($incident->driver_notified);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-12')->exists());
        $this->assertTrue(Notice::whereNull('user_id')->where('audience_role', 'manager')->where('code', 'S-04')->exists());

        $photo = $incident->photos()->first();
        $this->actingAs($guard)->get(route('staff.incident-photos.show', $photo))->assertOk();
        $this->actingAs($driver)->get(route('staff.incident-photos.show', $photo))->assertRedirect();

        $this->actingAs($guard)->post(route('guard.incidents.store'), [
            'category' => 'Other', 'description' => 'Too many photos here.',
            'photos' => array_fill(0, 4, UploadedFile::fake()->create('c.jpg', 10, 'image/jpeg')),
        ])->assertSessionHasErrors('photos');
    }

    public function test_incident_plate_and_slot_follow_the_category_and_the_vehicle_must_be_parked_now(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0020', 'HJK4471', 'A-02', 'active');
        $this->parked('PK-0021', 'GON1111', 'A-03', 'completed');
        $url = route('guard.incidents.store');
        $base = ['category' => 'Vehicle damage', 'description' => 'Scratch along the left door.', 'responsible_unknown' => 1];

        $this->actingAs($guard)->post($url, $base)->assertSessionHasErrors(['plate_number', 'location']);
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'HJK 4471'])->assertSessionHasErrors('location');
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'HJK 4471', 'location' => 'ZZ-99'])->assertSessionHasErrors('location');
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'HJK 4471', 'location' => 'A-05'])->assertSessionHasErrors('location');

        // A vehicle that left, and one that was never here, cannot be reported.
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'GON 1111', 'location' => 'A-03'])->assertSessionHasErrors('plate_number');
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'NEW 9999', 'location' => 'A-03'])->assertSessionHasErrors('plate_number');
        $this->assertSame(0, Incident::count());

        // "a2" is read as A-02.
        $this->actingAs($guard)->post($url, $base + ['plate_number' => 'HJK 4471', 'location' => 'a2'])->assertSessionHasNoErrors();
        $this->assertSame('A-02', Incident::first()->location);

        // The slot is filled in from the parked vehicle for an unpaid exit attempt.
        $this->actingAs($guard)->post($url, ['category' => 'Unpaid vehicle attempting exit', 'description' => 'Tried to drive out without paying.', 'plate_number' => 'HJK4471'])->assertSessionHasNoErrors();
        $this->assertSame('A-02', Incident::where('category', 'Unpaid vehicle attempting exit')->value('location'));
        $this->actingAs($guard)->post($url, ['category' => 'Unpaid vehicle attempting exit', 'description' => 'Tried to drive out without paying.'])->assertSessionHasErrors('plate_number');

        // Suspicious vehicle: a place is required, the plate is optional, but a plate that is given must be parked now.
        $this->actingAs($guard)->post($url, ['category' => 'Suspicious vehicle', 'description' => 'Someone is looking into cars.'])->assertSessionHasErrors('location');
        $this->actingAs($guard)->post($url, ['category' => 'Suspicious vehicle', 'description' => 'Someone is looking into cars.', 'location' => 'Zone A lane', 'plate_number' => 'NEW 9999'])->assertSessionHasErrors('plate_number');
        $this->actingAs($guard)->post($url, ['category' => 'Other', 'other_label' => 'Stuck gate arm', 'description' => 'Gate arm is stuck halfway.'])->assertSessionHasNoErrors();
    }

    public function test_the_plate_check_says_whether_a_vehicle_is_parked_now(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0020', 'HJK4471', 'A-02', 'active');
        $this->parked('PK-0021', 'GON1111', 'A-03', 'completed');

        $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => 'hjk 4471']))->assertOk()->assertJson(['state' => 'parked', 'slot' => 'A-02']);
        $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => 'GON1111']))->assertOk()->assertJson(['state' => 'gone']);
        $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => 'NEW9999']))->assertOk()->assertJson(['state' => 'gone']);
        $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => '']))->assertOk()->assertJson(['state' => 'empty']);
        $this->actingAs($guard)->getJson(route('guard.incidents.check-plate', ['plate' => '!!']))->assertOk()->assertJson(['state' => 'invalid']);
    }

    public function test_incident_without_a_plate_has_no_driver_to_notify(): void
    {
        $this->lot();
        $guard = $this->guard();

        $this->actingAs($guard)->post(route('guard.incidents.store'), ['category' => 'Suspicious vehicle', 'description' => 'Someone is looking into cars.', 'location' => 'Zone A lane'])->assertSessionHasNoErrors();
        $incident = Incident::first();
        $this->assertFalse($incident->driver_notified);
        $this->assertStringContainsString('No plate', $incident->driver_notice_reason);
    }

    public function test_status_only_moves_forward_and_resolving_needs_a_note(): void
    {
        $this->lot();
        $guard = $this->guard();
        $incident = Incident::create(['code' => 'INC-0001', 'category' => 'Other', 'source' => 'manual', 'status' => 'open', 'description' => 'Something odd.', 'reported_by' => $guard->id, 'reported_at' => now()]);

        $this->actingAs($guard)->post(route('guard.incidents.status', $incident), ['status' => 'under_review', 'note' => 'Checking the lot records'])->assertSessionHas('toast');
        $this->assertSame('under_review', $incident->fresh()->status);

        $this->actingAs($guard)->post(route('guard.incidents.status', $incident), ['status' => 'open'])->assertSessionHas('modal');
        $this->assertSame('under_review', $incident->fresh()->status);

        $this->actingAs($guard)->post(route('guard.incidents.status', $incident), ['status' => 'resolved', 'note' => ''])->assertSessionHas('modal');
        $this->assertSame('under_review', $incident->fresh()->status);

        $this->actingAs($guard)->post(route('guard.incidents.status', $incident), ['status' => 'resolved', 'note' => 'Owner moved the car.'])->assertSessionHas('toast');
        $this->assertSame('resolved', $incident->fresh()->status);

        $this->actingAs($guard)->post(route('guard.incidents.status', $incident), ['status' => 'open'])->assertSessionHas('modal');
        $this->assertSame('resolved', $incident->fresh()->status);

        $this->actingAs($guard)->post(route('guard.incidents.notes', $incident), ['body' => 'Car is gone now.'])->assertSessionHas('toast');
        $this->actingAs($guard)->get(route('guard.incidents.show', $incident))->assertOk()->assertSee('Owner moved the car')->assertSee('Car is gone now');
    }

    public function test_long_parking_is_automatic_only_a_guard_cannot_report_it_by_hand(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0012', 'QRS3317', 'B-01', 'active', 60 * 14);

        // The manual report page and its button are gone.
        $this->assertFalse(Route::has('guard.incidents.long-parked'));
        $this->actingAs($guard)->get(route('guard.incidents'))->assertOk()->assertDontSee('Report long-parked vehicle');
        $this->actingAs($guard)->get('/staff/guard/incidents/long-parked')->assertNotFound();

        // The category is not in the new-incident form, and the server refuses it if someone posts it.
        $this->actingAs($guard)->get(route('guard.incidents.create'))->assertOk()->assertDontSee('value="Long-parked vehicle"', false);
        $this->actingAs($guard)->post(route('guard.incidents.store'), ['category' => 'Long-parked vehicle', 'description' => 'Car in B-01 has not moved all day.', 'plate_number' => 'QRS 3317', 'location' => 'B-01'])->assertSessionHasErrors('category');
        $this->assertSame(0, Incident::where('source', 'manual')->count());
    }

    public function test_automatic_detection_makes_one_incident_per_session_and_it_shows_in_alerts(): void
    {
        $this->lot();
        $guard = $this->guard();
        $driver = $this->user(User::ROLE_DRIVER, 'Maria', 'maria@example.com');
        $session = $this->parked('PK-0012', 'QRS3317', 'B-01', 'active', 60 * 24 + 30, $driver);
        $this->parked('PK-0020', 'NEW1234', 'B-02', 'active', 90);

        $service = app(LongParkingService::class);
        $this->assertSame(1, $service->detect());
        $this->assertSame(0, $service->detect());
        $incident = Incident::where('session_id', $session->id)->first();
        $this->assertSame(1, Incident::count());
        $this->assertSame(2, $incident->detection_count);
        $this->assertSame('auto', $incident->source);
        $this->assertTrue($incident->driver_notified);
        $this->assertTrue(Notice::where('user_id', $driver->id)->where('code', 'N-10')->exists());

        $this->actingAs($guard)->get(route('guard.gate'))->assertSee('Long-parked vehicle: B-01')->assertSee($incident->code);
        $this->actingAs($guard)->getJson(route('guard.alerts'))->assertJsonPath('count', 1);
        $this->actingAs($guard)->get(route('guard.incidents.show', $incident))->assertOk()->assertSee('Detected by Bayline');
    }

    public function test_guard_alert_read_state_survives_navigation_and_new_alerts_are_unread(): void
    {
        $this->lot();
        $guard = $this->guard();
        $notice = Notice::create([
            'audience_role' => User::ROLE_GUARD,
            'code' => 'S-05',
            'kind' => 'test_alert',
            'title' => 'Test alert',
            'body' => 'Test alert body.',
        ]);

        $this->actingAs($guard)->getJson(route('guard.alerts'))->assertJsonPath('count', 1);
        $this->actingAs($guard)->postJson(route('guard.alerts.read'))->assertOk();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->actingAs($guard)->get(route('guard.records'))->assertOk();
        $this->actingAs($guard)->getJson(route('guard.alerts'))->assertJsonPath('count', 0);

        Notice::create([
            'audience_role' => User::ROLE_GUARD,
            'code' => 'S-06',
            'kind' => 'test_alert',
            'title' => 'New test alert',
            'body' => 'New test alert body.',
        ]);

        $this->actingAs($guard)->getJson(route('guard.alerts'))->assertJsonPath('count', 1);
    }

    public function test_guard_record_search_strips_unnecessary_special_characters_but_keeps_hyphens(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');
        $incident = Incident::create([
            'code' => 'INC-0001',
            'category' => 'Other',
            'source' => 'manual',
            'status' => 'open',
            'description' => 'Something odd happened.',
            'reported_by' => $guard->id,
            'reported_at' => now(),
        ]);

        $this->actingAs($guard)->get(route('guard.records', ['q' => 'bnm@-6628']))
            ->assertOk()->assertSee('PK-0018');
        $this->actingAs($guard)->get(route('guard.records', ['tab' => 'incidents', 'q' => 'inc@-0001']))
            ->assertOk()->assertSee($incident->code);
    }

    public function test_records_filter_and_detail(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');
        $this->parked('PK-0016', 'HJK4471', 'A-02', 'active', 125);
        $old = $this->parked('PK-0001', 'OLD1111', 'A-03', 'active', 60 * 24 * 20);
        $old->update(['status' => 'completed', 'exited_at' => now()->subDays(20)]);

        $this->actingAs($guard)->get(route('guard.records'))->assertSee('PK-0018')->assertSee('PK-0016')->assertDontSee('PK-0001');
        $this->actingAs($guard)->get(route('guard.records', ['range' => '30']))->assertSee('PK-0001');
        $this->actingAs($guard)->get(route('guard.records', ['payment' => 'unpaid']))->assertSee('PK-0016')->assertDontSee('PK-0018');
        $this->actingAs($guard)->get(route('guard.records', ['q' => 'bnm 6628']))->assertSee('PK-0018')->assertDontSee('PK-0016');
        $this->actingAs($guard)->get(route('guard.records', ['q' => 'nothing-here']))->assertSee('No results');
        $this->actingAs($guard)->get(route('guard.records', ['view' => ParkingSession::where('ticket_number', 'PK-0018')->value('id')]))->assertSee('Parking record PK-0018');
    }

    public function test_slots_view_shows_vehicle_and_booking_details(): void
    {
        $this->lot();
        $guard = $this->guard();
        $this->parked('PK-0018', 'BNM6628', 'A-07', 'paid');
        $driver = $this->user(User::ROLE_DRIVER, 'Maria', 'maria@example.com');
        $slot = ParkingSlot::where('code', 'A-04')->first();
        $slot->update(['status' => 'reserved']);
        ParkingReservation::create(['code' => 'RSV-AAAAAA', 'driver_id' => $driver->id, 'slot_id' => $slot->id, 'slot_code' => 'A-04', 'plate_number' => 'DEF2208', 'scheduled_at' => now()->addHour(), 'status' => 'booked']);

        $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->assertSee('A-07')->assertSee('BNM 6628, ticket PK-0018', false)->assertSee('RSV-AAAAAA', false);
    }

    public function test_idle_polling_does_not_keep_a_staff_session_alive(): void
    {
        $this->lot();
        SystemSetting::query()->updateOrCreate(['key' => 'idle_timeout_minutes'], ['value' => json_encode(15)]);
        $guard = $this->guard();

        $this->actingAs($guard)->withSession(['staff_last_activity' => now()->subMinutes(5)->timestamp])
            ->getJson(route('guard.alerts'), ['X-Bayline-Poll' => '1'])->assertOk();
        $this->assertLessThanOrEqual(now()->subMinutes(5)->timestamp + 5, session('staff_last_activity'));

        $this->actingAs($guard)->withSession(['staff_last_activity' => now()->subMinutes(30)->timestamp])
            ->getJson(route('guard.alerts'), ['X-Bayline-Poll' => '1'])->assertRedirect(route('staff.login'));
    }

    public function test_demo_command_creates_and_clears_its_own_rows_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $real = $this->parked('PK-0100', 'REAL123', 'C-01', 'active', 30);

        $this->artisan('bayline:demo')->assertSuccessful();
        $this->assertSame(1 + 4, ParkingSession::count());
        $this->assertSame(1, ParkingPayment::count());
        $this->assertSame('pending_exit', ParkingSlot::where('code', 'A-07')->value('status'));
        $this->assertSame(1, Incident::where('category', 'Long-parked vehicle')->count());

        $guard = User::where('email', 'guard@test.com')->first();
        $this->actingAs($guard)->get(route('guard.gate'))->assertOk()->assertSee('PK-9018')->assertSee('Long-parked vehicle: B-01');

        $this->artisan('bayline:demo', ['--driver' => 'reservation'])->assertSuccessful();
        $this->assertSame(1, ParkingReservation::where('code', 'RSV-DEMO01')->count());
        $this->assertSame(4, ParkingSession::count());

        $this->artisan('bayline:demo', ['--clear' => true])->assertSuccessful();
        $this->assertSame(1, ParkingSession::count());
        $this->assertSame($real->id, ParkingSession::first()->id);
        $this->assertSame(0, ParkingPayment::count());
        $this->assertSame(0, Incident::count());
        $this->assertSame('available', ParkingSlot::where('code', 'A-07')->value('status'));
        $this->assertSame('occupied', ParkingSlot::where('code', 'C-01')->value('status'));
    }
}
