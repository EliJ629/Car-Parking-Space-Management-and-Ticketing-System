<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ParkingPayment;
use App\Models\ParkingRateSet;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\LandingConfig;
use App\Support\PlateNumber;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Manager-configured landing page, the shared lookup layout, and the real "Find my car" lookup. */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $name = 'Elena Cruz'): User
    {
        return User::create(['name' => $name, 'username' => strtolower(str_replace(' ', '', $name)).$role, 'email' => $role.'@test.com',
            'phone' => '0917'.random_int(1000000, 9999999), 'password' => 'Staff123_', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    /** Sign out: the public pages are for visitors, and a signed in user is sent to their own home instead. */
    private function visitor(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        $this->flushSession();
    }

    private function valid(array $override = []): array
    {
        return $override + [
            'hero_eyebrow' => 'For motorists', 'hero_title' => 'Park *smart* today', 'hero_lede' => 'Reserve a bay and follow it from your phone.',
            'lookup_on_login' => '1', 'lookup_layout' => 'inline', 'lookup_heading' => 'Where is my car?', 'lookup_intro' => 'Type the ticket number on your slip.',
            'sections' => [
                ['key' => 'fees', 'position' => 1, 'enabled' => '1'],
                ['key' => 'reserve', 'position' => 2, 'enabled' => '1'],
                ['key' => 'find', 'position' => 3, 'enabled' => '1'],
                ['key' => 'grace', 'position' => 4],
            ],
        ];
    }

    public function test_defaults_show_when_nothing_is_saved(): void
    {
        $this->get(route('driver.landing'))->assertOk()
            ->assertSee('For drivers')->assertSee('waiting', false)
            ->assertSee('Reserve in a minute')->assertSee('Reservation grace period')->assertSee('Know the amount before you reach the booth');
        $this->get(route('driver.login'))->assertOk()->assertSee('Find my car')->assertSee('findbox-stacked', false);
    }

    public function test_only_the_manager_can_open_or_save_the_landing_settings(): void
    {
        $this->get(route('manager.landing.edit'))->assertRedirect();
        foreach ([User::ROLE_ATTENDANT, User::ROLE_GUARD, User::ROLE_DRIVER] as $role) {
            // Another role is turned away (sent home or refused); either way it never reaches the form or the save.
            $this->actingAs($this->user($role, ucfirst($role).' Person'));
            $this->assertContains($this->get(route('manager.landing.edit'))->getStatusCode(), [302, 403]);
            $this->assertContains($this->put(route('manager.landing.update'), $this->valid())->getStatusCode(), [302, 403]);
        }
        $this->assertNull(SystemSetting::value(LandingConfig::KEY));
    }

    public function test_the_manager_saves_content_order_and_layout_and_every_page_reflects_it(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAGER))->get(route('manager.landing.edit'))->assertOk()->assertSee('Landing page');
        $this->put(route('manager.landing.update'), $this->valid())->assertRedirect(route('manager.landing.edit'))->assertSessionHasNoErrors();
        $this->visitor();

        $stored = SystemSetting::value(LandingConfig::KEY);
        $this->assertSame(['fees', 'reserve', 'find', 'grace'], array_column($stored['sections'], 'key'));
        $this->assertSame([true, true, true, false], array_column($stored['sections'], 'enabled'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'landing_page_updated', 'area' => 'manager']);

        $page = $this->get(route('driver.landing'))->assertOk()->assertSee('For motorists')->assertSee('Park <em>smart</em> today', false)
            ->assertSee('Where is my car?')->assertSee('findbox-inline', false)->assertDontSee('Reservation grace period');
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'id="reserve"'), strpos($html, 'id="fees"'), 'Fees come before Reserve now.');

        // The sign in page uses the same block, so it has the same layout and wording.
        $this->get(route('driver.login'))->assertOk()->assertSee('findbox-inline', false)->assertSee('Where is my car?')->assertSee('Type the ticket number on your slip.');
    }

    public function test_the_lookup_can_be_hidden_from_the_sign_in_page(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAGER));
        $payload = $this->valid();
        unset($payload['lookup_on_login']);
        $this->put(route('manager.landing.update'), $payload)->assertSessionHasNoErrors();
        $this->visitor();

        $this->get(route('driver.login'))->assertOk()->assertDontSee('findbox', false);
        $this->get(route('driver.landing'))->assertOk()->assertSee('findbox', false);
    }

    public function test_the_compact_layout_has_no_intro_text(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAGER));
        $this->put(route('manager.landing.update'), $this->valid(['lookup_layout' => 'compact']))->assertSessionHasNoErrors();
        $this->visitor();
        $this->get(route('driver.login'))->assertSee('findbox-compact', false)->assertDontSee('Type the ticket number on your slip.');
    }

    public function test_invalid_landing_settings_are_refused_and_nothing_is_saved(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAGER));
        $bad = [
            'empty title' => ['hero_title' => ''],
            'too long title' => ['hero_title' => str_repeat('a', 91)],
            'html in title' => ['hero_title' => '<script>alert(1)</script>'],
            'two emphasis marks' => ['hero_title' => 'A *b* and *c*'],
            'short lede' => ['hero_lede' => 'short'],
            'unknown layout' => ['lookup_layout' => 'floating'],
            'empty heading' => ['lookup_heading' => ''],
            'html in intro' => ['lookup_intro' => 'Click <b>here</b> now'],
            'duplicate places' => ['sections' => [['key' => 'fees', 'position' => 1], ['key' => 'reserve', 'position' => 1], ['key' => 'find', 'position' => 3], ['key' => 'grace', 'position' => 4]]],
            'unknown section' => ['sections' => [['key' => 'fees', 'position' => 1], ['key' => 'reserve', 'position' => 2], ['key' => 'find', 'position' => 3], ['key' => 'hack', 'position' => 4]]],
            'missing section' => ['sections' => [['key' => 'fees', 'position' => 1], ['key' => 'reserve', 'position' => 2], ['key' => 'find', 'position' => 3]]],
            'repeated section' => ['sections' => [['key' => 'fees', 'position' => 1], ['key' => 'fees', 'position' => 2], ['key' => 'find', 'position' => 3], ['key' => 'grace', 'position' => 4]]],
        ];

        foreach ($bad as $name => $override) {
            $this->put(route('manager.landing.update'), $this->valid($override))->assertSessionHasErrors(null, null, "Accepted: {$name}");
        }

        $this->assertNull(SystemSetting::value(LandingConfig::KEY));
    }

    public function test_typed_markup_is_never_output_as_html(): void
    {
        // Even if a bad value got into the setting some other way, the page escapes it and cleans it on read.
        SystemSetting::create(['key' => LandingConfig::KEY, 'group' => 'landing', 'value' => [
            'hero_title' => '<img src=x onerror=alert(1)> *hi*', 'hero_eyebrow' => '<b>x</b>y', 'sections' => 'nonsense', 'lookup' => ['layout' => 'evil'],
        ]]);

        $this->get(route('driver.landing'))->assertOk()->assertDontSee('<img src=x', false)->assertSee('<em>hi</em>', false)->assertSee('findbox-stacked', false);
    }

    public function test_reset_restores_the_defaults(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAGER));
        $this->put(route('manager.landing.update'), $this->valid());
        $this->put(route('manager.landing.update'), ['action' => 'reset'])->assertRedirect(route('manager.landing.edit'));
        $this->visitor();

        $this->assertSame(LandingConfig::defaults(), SystemSetting::value(LandingConfig::KEY));
        $this->assertDatabaseHas('activity_logs', ['action' => 'landing_page_reset']);
        $this->get(route('driver.landing'))->assertSee('For drivers');
    }

    public function test_the_page_uses_the_live_rates_grace_and_booking_window(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'reservation_grace_minutes'], ['value' => 25, 'group' => 'parking']);
        SystemSetting::updateOrCreate(['key' => 'maximum_advance_booking_days'], ['value' => 3, 'group' => 'parking']);
        ParkingRateSet::query()->update(['first_hour_fee' => 45, 'additional_hour_fee' => 35, 'daily_maximum' => 410]);

        $this->get(route('driver.landing'))->assertOk()
            ->assertSee('25 minutes of grace')->assertSee('8:25 AM')
            ->assertSee('Book any time in the next 24 hours')
            ->assertSee('₱45')->assertSee('₱35')->assertSee('₱410')
            ->assertDontSee('Ten minutes of grace')->assertDontSee('7 days')->assertDontSee('₱300 a day');
    }

    public function test_find_my_car_answers_from_real_sessions_and_hides_private_data(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
        $slot = ParkingSlot::where('code', 'A-05')->first();
        $session = ParkingSession::create([
            'ticket_number' => 'PK-0017', 'slot_id' => $slot->id, 'slot_code' => 'A-05', 'plate_number' => 'LMN1234', 'driver_phone' => '09171234567',
            'vehicle_color' => 'Black', 'vehicle_type' => 'SUV', 'entered_at' => now()->subMinutes(50), 'status' => ParkingSession::STATUS_ACTIVE,
        ]);

        $body = $this->getJson(route('find-my-car', ['ticket' => 'pk17']))->assertOk()
            ->assertJsonPath('state', 'parked')->assertJsonPath('slot', 'A-05')->assertJsonPath('ticket', 'PK-0017')->assertJsonPath('plate', 'LMN ••34');
        $raw = $body->getContent();
        $this->assertStringNotContainsString('LMN1234', $raw);
        $this->assertStringNotContainsString('LMN 1234', $raw);
        $this->assertStringNotContainsString('09171234567', $raw);

        $this->getJson(route('find-my-car', ['ticket' => 'PK-0099']))->assertOk()->assertJsonPath('state', 'unknown');
        $this->getJson(route('find-my-car', ['ticket' => 'hello']))->assertStatus(422)->assertJsonPath('state', 'invalid');
        $this->getJson(route('find-my-car', ['ticket' => 'PK-00000']))->assertStatus(422);
        $this->getJson(route('find-my-car'))->assertStatus(422);

        $session->update(['status' => ParkingSession::STATUS_PAID]);
        ParkingPayment::create(['receipt_number' => 'RC-0001', 'session_id' => $session->id, 'received_by' => $this->user(User::ROLE_ATTENDANT, 'Marco V')->id,
            'base_fee' => 30, 'additional_fee' => 0, 'penalty' => 0, 'discount' => 0, 'total' => 30, 'method' => 'cash', 'paid_at' => now()]);
        $this->getJson(route('find-my-car', ['ticket' => 'PK-0017']))->assertJsonPath('state', 'paid')->assertJsonPath('fee', 'Paid ₱30.00, receipt RC-0001');

        $session->update(['status' => ParkingSession::STATUS_COMPLETED, 'exited_at' => now()]);
        $left = $this->getJson(route('find-my-car', ['ticket' => 'PK-0017']))->assertJsonPath('state', 'left');
        $this->assertArrayNotHasKey('plate', $left->json());
        $this->assertArrayNotHasKey('slot', $left->json());
    }

    public function test_find_my_car_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson(route('find-my-car', ['ticket' => 'PK-0001']))->assertOk();
        }

        $this->getJson(route('find-my-car', ['ticket' => 'PK-0001']))->assertStatus(429);
    }

    public function test_the_plate_mask_hides_the_middle(): void
    {
        $this->assertSame('LMN ••34', PlateNumber::mask('lmn 1234'));
        $this->assertSame('AB1 ••45', PlateNumber::mask('AB12345'));
        $this->assertStringNotContainsString('5', PlateNumber::mask('5'));
        $this->assertSame('', PlateNumber::mask(null));
    }
}
