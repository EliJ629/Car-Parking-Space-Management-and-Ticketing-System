<?php

namespace Tests\Feature;

use App\Models\BannedPlate;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\BanService;
use App\Services\IncidentService;
use App\Services\NoticeService;
use App\Support\SearchTerm;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The banned list for staff, the ban on sign in and sign up, mobile-number linking, and the texts sent to drivers. */
class BannedDriverAndMessagesTest extends TestCase
{
    use RefreshDatabase;

    private int $ticket = 700;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['textbee.enabled' => true, 'textbee.api_key' => 'key-123456', 'textbee.device_id' => 'device-1', 'textbee.base_url' => 'https://api.textbee.dev/api/v1']);
        Http::swap(new Factory);
        Http::fake(['api.textbee.dev/*' => Http::response(['data' => ['success' => true, 'smsBatchId' => 'b-1']], 200)]);
    }

    private function staff(string $role, string $name): User
    {
        return User::create(['name' => $name, 'username' => strtolower(str_replace(' ', '', $name)), 'email' => strtolower(str_replace(' ', '.', $name)).'@test.com',
            'phone' => '0917'.random_int(1000000, 9999999), 'password' => 'Staff123_', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function driver(string $plate = 'ABC1234', string $phone = '09175550142', string $name = 'Pedro Cruz'): User
    {
        $driver = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@gmail.com', 'phone' => $phone, 'password' => 'Driver123x!',
            'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
        $driver->vehicles()->create(['plate_number' => $plate, 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios']);

        return $driver;
    }

    private function park(string $plate, string $slotCode, ?string $phone = null, ?User $driver = null, int $minutesAgo = 135): ParkingSession
    {
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

        return ParkingSession::create(['ticket_number' => 'PK-'.str_pad((string) $this->ticket++, 4, '0', STR_PAD_LEFT), 'driver_id' => $driver?->id, 'driver_phone' => $phone,
            'slot_id' => $slot->id, 'slot_code' => $slotCode, 'plate_number' => $plate, 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subMinutes($minutesAgo), 'status' => ParkingSession::STATUS_ACTIVE]);
    }

    private function violation(string $plate, string $slot, User $manager, ?ParkingSession $session = null): Incident
    {
        $incident = Incident::create(['code' => 'INC-'.str_pad((string) (Incident::count() + 1), 4, '0', STR_PAD_LEFT), 'category' => 'Unauthorized parking', 'source' => 'manual',
            'status' => 'open', 'plate_number' => $plate, 'attributed_plate' => $plate, 'location' => $slot, 'description' => 'Parked across two bays.', 'session_id' => $session?->id,
            'reported_by' => $manager->id, 'reported_at' => now(), 'confirmation' => Incident::PENDING]);
        app(BanService::class)->confirm($incident, $manager);

        return $incident->fresh();
    }

    private function ban(User $driver, string $plate = 'ABC1234'): BannedPlate
    {
        $manager = $this->staff(User::ROLE_MANAGER, 'Elena Cruz');
        $this->park($plate, 'A-01', $driver->phone, $driver);

        foreach (['A-01', 'A-01', 'A-01'] as $slot) {
            $this->violation($plate, $slot, $manager);
        }

        return BannedPlate::where('plate_number', $plate)->firstOrFail();
    }

    public function test_the_ban_keeps_the_drivers_name_and_mobile_number(): void
    {
        $ban = $this->ban($this->driver());

        $this->assertSame('Pedro Cruz', $ban->driver_name);
        $this->assertSame('09175550142', $ban->driver_phone);
    }

    public function test_a_banned_driver_cannot_sign_in_but_a_lifted_ban_lets_them_back(): void
    {
        $driver = $this->driver();
        $ban = $this->ban($driver);

        $this->post(route('driver.login'), ['identity' => $driver->email, 'password' => 'Driver123x!'])->assertSessionHasErrors(['identity' => BanService::LOGIN_MESSAGE]);
        $this->assertGuest();

        $ban->update(['lifted_at' => now()]);
        $this->post(route('driver.login'), ['identity' => $driver->email, 'password' => 'Driver123x!'])->assertSessionHasNoErrors();
    }

    public function test_a_driver_who_is_banned_while_signed_in_is_signed_out(): void
    {
        $driver = $this->driver();
        $this->actingAs($driver)->get(route('driver.home'))->assertOk();
        $this->ban($driver);

        $this->actingAs($driver)->get(route('driver.home'))->assertRedirect(route('driver.login'));
    }

    public function test_sign_up_is_refused_for_a_banned_plate_name_or_mobile_number(): void
    {
        Mail::fake();
        $this->ban($this->driver());

        $form = fn (array $o) => $o + [
            'first_name' => 'Juan', 'last_name' => 'Reyes', 'email' => 'juan.reyes@gmail.com', 'phone' => '09171110001', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black', 'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ];

        $this->post(route('driver.register'), $form(['plate_number' => 'ABC 1234']))->assertSessionHasErrors(['plate_number' => \App\Services\VehicleBanned::MESSAGE]);
        $this->assertStringContainsString('numerous incident reports', \App\Services\VehicleBanned::MESSAGE);
        $this->post(route('driver.register'), $form(['first_name' => 'Pedro', 'last_name' => 'Cruz']))->assertSessionHasErrors(['name' => BanService::NAME_MESSAGE]);
        $this->post(route('driver.register'), $form(['phone' => '09175550142']))->assertSessionHasErrors('phone');
        $this->assertSame(0, PendingRegistration::count());

        $this->post(route('driver.register'), $form([]))->assertRedirect(route('driver.register.sent'));
    }

    public function test_the_sign_up_page_sends_the_device_to_sign_in_once_the_link_was_used_elsewhere(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), [
            'first_name' => 'Juan', 'last_name' => 'Reyes', 'email' => 'juan.reyes@gmail.com', 'phone' => '09171110001', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black', 'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ])->assertRedirect(route('driver.register.sent'));

        $this->getJson(route('driver.register.status'))->assertOk()->assertJson(['done' => false]);

        // The link is opened on a phone: another session with no cookie from the first device.
        $token = 'z'.str_repeat('a', 63);
        PendingRegistration::firstOrFail()->forceFill(['token_hash' => PendingRegistration::hashToken($token)])->save();
        $session = $this->app['session.store']->all();
        $this->flushSession();
        $this->get(route('driver.register.confirm', ['token' => $token]))->assertRedirect(route('driver.login'));
        $this->flushSession();
        $this->withSession($session);

        $this->getJson(route('driver.register.status'))->assertOk()->assertJson(['done' => true, 'url' => route('driver.login')]);
    }

    public function test_guard_and_attendant_see_the_banned_list_with_the_reasons(): void
    {
        $this->ban($this->driver());

        foreach ([[User::ROLE_GUARD, 'guard.bans'], [User::ROLE_ATTENDANT, 'attendant.bans']] as [$role, $route]) {
            $this->actingAs($this->staff($role, 'Staff '.$role))->get(route($route))
                ->assertOk()->assertSee('Banned list')->assertSee('ABC 1234')->assertSee('Pedro Cruz')->assertSee('09175550142')->assertSee('Parked across two bays.');
        }

        $this->actingAs($this->staff(User::ROLE_GUARD, 'Other Guard'))->get(route('guard.bans', ['q' => 'ZZZ']))->assertOk()->assertSee('No banned driver matches');
        $this->actingAs($this->driver('QRS7788', '09170000099', 'Ana Lim'))->get(route('guard.bans'))->assertRedirect();
    }

    public function test_a_walk_in_with_a_banned_mobile_number_cannot_park(): void
    {
        $this->ban($this->driver());
        $attendant = $this->staff(User::ROLE_ATTENDANT, 'Booth One');

        $this->actingAs($attendant)->post(route('attendant.entry.walk-in.store'), [
            'plate' => 'XYZ9876', 'phone' => '09175550142', 'color' => 'Red', 'type' => 'Car', 'slot_code' => 'A-03',
        ])->assertSessionHasErrors('phone');
        $this->assertSame(0, ParkingSession::where('plate_number', 'XYZ9876')->count());
    }

    public function test_violations_follow_the_drivers_mobile_number_to_a_new_plate(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER, 'Elena Cruz');
        // A walk-in on a temporary plate, then the same person registers with a permanent plate and the same number.
        $walkIn = $this->park('TMP1111', 'A-02', '09175550142');
        $this->violation('TMP1111', 'A-02', $manager, $walkIn);
        $this->violation('TMP1111', 'A-02', $manager, $walkIn);

        $driver = $this->driver('NEW5555', '09175550142');
        $this->assertSame(2, app(BanService::class)->count('NEW5555'));

        $this->violation('NEW5555', 'A-02', $manager);
        $this->assertTrue(app(BanService::class)->isBanned('NEW5555'));
        $this->assertTrue(app(BanService::class)->isBanned('TMP1111'));

        // A number typed at the booth for two unrelated walk-ins does not join them.
        $this->park('AAA1111', 'A-04', '09179990000');
        $this->park('BBB2222', 'A-05', '09179990000');
        $this->violation('AAA1111', 'A-04', $manager);
        $this->assertSame(0, app(BanService::class)->count('BBB2222'));
    }

    public function test_a_report_texts_the_registered_driver_with_entry_time_duration_and_slot(): void
    {
        $driver = $this->driver();
        $session = $this->park('ABC1234', 'A-06', '09175550142', $driver, 135);
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');

        app(IncidentService::class)->createManual($guard, ['category' => 'Unauthorized parking', 'plate_number' => 'ABC1234', 'location' => 'A-06', 'description' => 'Blocking the lane.', 'session_id' => $session->id]);

        Http::assertSent(function (Request $r) use ($session) {
            $text = (string) $r['message'];

            return str_contains($r->url(), 'api.textbee.dev')
                && in_array('+639175550142', (array) $r['recipients'], true)
                && str_contains($text, 'Entry '.\App\Support\Lot::stamp($session->entered_at))
                && str_contains($text, 'Parked 2 h 15 min')
                && str_contains($text, 'Slot A-06');
        });
    }

    public function test_a_report_texts_a_walk_in_and_every_driver_notice_is_texted(): void
    {
        $session = $this->park('WLK1234', 'A-07', '09171112233');
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');

        app(IncidentService::class)->createManual($guard, ['category' => 'Unauthorized parking', 'plate_number' => 'WLK1234', 'location' => 'A-07', 'description' => 'Blocking.', 'session_id' => $session->id, 'notify_driver' => false]);
        Http::assertSent(fn (Request $r) => in_array('+639171112233', (array) $r['recipients'], true) && str_contains((string) $r['message'], 'Slot A-07'));

        // Parking started for a walk-in is a text too.
        Http::swap(new Factory);
        Http::fake(['api.textbee.dev/*' => Http::response(['data' => ['smsBatchId' => 'b-2']], 200)]);
        app(NoticeService::class)->parkingStarted($session);
        Http::assertSent(fn (Request $r) => in_array('+639171112233', (array) $r['recipients'], true) && str_contains((string) $r['message'], 'Parking started'));
    }

    public function test_search_boxes_accept_only_letters_numbers_spaces_and_hyphens(): void
    {
        $this->assertSame('AB-12 3script', SearchTerm::normalize("AB-12 3<script>%_'\";é"));
        $this->assertSame('PK-0018', SearchTerm::normalize('PK-0018!!'));
    }

    public function test_the_banned_list_puts_every_report_in_its_own_box_with_its_details(): void
    {
        $this->ban($this->driver());

        $this->actingAs($this->staff(User::ROLE_GUARD, 'Gate Guard'))->get(route('guard.bans'))
            ->assertOk()->assertSee('Banned because of numerous incident reports')->assertSee('INC-0001')->assertSee('INC-0002')->assertSee('INC-0003')
            ->assertSee('Unauthorized parking')->assertSee('Reported by')->assertSee('Confirmed')->assertSee('Driver told')->assertSee('What happened:');
    }

    public function test_an_incident_unresolved_for_a_day_alerts_guard_and_manager_and_repeats_every_day(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER, 'Elena Cruz');
        $incident = Incident::create(['code' => 'INC-0100', 'category' => 'Suspicious vehicle', 'source' => 'manual', 'status' => 'open', 'plate_number' => 'ABC1234', 'location' => 'A-02',
            'description' => 'Someone is looking into cars.', 'reported_by' => $manager->id, 'reported_at' => now()->subHours(25), 'confirmation' => Incident::PENDING]);
        $reminders = app(\App\Services\IncidentReminders::class);

        $reminders->send();
        $reminders->send();
        $this->assertSame(1, \App\Models\Notice::where('kind', 'incident_unresolved')->where('audience_role', 'guard')->count());
        $this->assertSame(1, \App\Models\Notice::where('kind', 'incident_unresolved')->where('audience_role', 'manager')->count());

        $this->travel(24)->hours();
        $reminders->send();
        $this->assertSame(2, \App\Models\Notice::where('kind', 'incident_unresolved')->where('audience_role', 'guard')->count());

        $incident->update(['status' => 'resolved']);
        $this->travel(24)->hours();
        $reminders->send();
        $this->assertSame(2, \App\Models\Notice::where('kind', 'incident_unresolved')->where('audience_role', 'guard')->count(), 'a resolved incident stops the reminders');
    }

    public function test_a_young_incident_is_not_reminded_yet(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER, 'Elena Cruz');
        Incident::create(['code' => 'INC-0101', 'category' => 'Violation', 'source' => 'manual', 'status' => 'open', 'description' => 'Something happened here.', 'reported_by' => $manager->id, 'reported_at' => now()->subHours(23), 'confirmation' => Incident::PENDING]);

        app(\App\Services\IncidentReminders::class)->send();
        $this->assertSame(0, \App\Models\Notice::where('kind', 'incident_unresolved')->count());
    }

    public function test_the_same_vehicle_cannot_be_reported_twice_under_one_category_but_can_under_another(): void
    {
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');
        $this->park('ABC1234', 'A-06');
        $payload = ['category' => 'Suspicious vehicle', 'plate_number' => 'ABC 1234', 'location' => 'A-06', 'description' => 'Circling the lot for an hour.'];

        $this->actingAs($guard)->post(route('guard.incidents.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('guard.incidents.store'), $payload)->assertSessionHasErrors('plate_number');
        $this->assertSame(1, Incident::count());

        $this->post(route('guard.incidents.store'), ['category' => 'Violation'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, Incident::count());

        $this->get(route('guard.incidents.create'))->assertOk()->assertSee('data-taken', false);
    }

    public function test_a_new_report_offers_to_resolve_it_at_once(): void
    {
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');
        $this->park('ABC1234', 'A-06');

        $this->actingAs($guard)->post(route('guard.incidents.store'), ['category' => 'Suspicious vehicle', 'plate_number' => 'ABC1234', 'location' => 'A-06', 'description' => 'Circling the lot for an hour.'])
            ->assertSessionHas('offer_resolution');
        $incident = Incident::sole();

        $this->get(route('guard.incidents.show', $incident))->assertOk()->assertSee('Already dealt with?')->assertSee('Mark as resolved');

        $this->post(route('guard.incidents.status', $incident), ['status' => 'resolved', 'note' => 'Driver was spoken to and left.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertSame('Driver was spoken to and left.', $incident->fresh()->resolution_note);
    }

    public function test_a_long_parking_incident_has_no_update_status_and_refuses_a_hand_set_status(): void
    {
        $guard = $this->staff(User::ROLE_GUARD, 'Ben Aquino');
        $session = $this->park('ABC1234', 'A-06', null, null, 60 * 30);
        $incident = Incident::create(['code' => 'INC-0200', 'category' => 'Long-parked vehicle', 'source' => 'auto', 'status' => 'open', 'session_id' => $session->id, 'plate_number' => 'ABC1234',
            'location' => 'A-06', 'description' => 'Parked too long.', 'reported_at' => now(), 'confirmation' => Incident::CONFIRMED]);

        $this->actingAs($guard)->get(route('guard.incidents.show', $incident))->assertOk()->assertSee('Notify driver')->assertDontSee('Update status');
        $this->post(route('guard.incidents.status', $incident), ['status' => 'under_review'])->assertSessionHas('modal');
        $this->assertSame('open', $incident->fresh()->status);
    }
}
