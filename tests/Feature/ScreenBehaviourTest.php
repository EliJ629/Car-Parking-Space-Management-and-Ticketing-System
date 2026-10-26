<?php

namespace Tests\Feature;

use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use Database\Seeders\ManagerDefaultsSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Markup-level checks for the driver and guard screens: one theme control, confirmed sign-out, live filters, long-parked slots. */
class ScreenBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role, string $email): User
    {
        return User::create(['name' => ucfirst($role).' User', 'username' => $role.'x', 'email' => $email, 'phone' => '0917'.random_int(1000000, 9999999),
            'password' => 'Pass123_x', 'role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function lot(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);
        $this->seed(ParkingSlotSeeder::class);
    }

    private function park(string $slotCode, string $plate, int $hoursAgo, ?User $driver = null): ParkingSession
    {
        $slot = ParkingSlot::where('code', $slotCode)->first();
        $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);
        $attendant = User::where('role', User::ROLE_ATTENDANT)->first() ?? $this->make(User::ROLE_ATTENDANT, 'att@example.com');

        return ParkingSession::create(['ticket_number' => 'PK-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'driver_id' => $driver?->id, 'slot_id' => $slot->id,
            'slot_code' => $slotCode, 'attendant_id' => $attendant->id, 'plate_number' => $plate, 'vehicle_color' => 'White', 'vehicle_type' => 'Car',
            'entered_at' => now()->subHours($hoursAgo), 'status' => ParkingSession::STATUS_ACTIVE]);
    }

    public function test_driver_pages_have_exactly_one_theme_control_and_a_confirmed_sign_out(): void
    {
        $this->lot();
        $driver = $this->make(User::ROLE_DRIVER, 'd@example.com');
        $driver->vehicles()->create(['plate_number' => 'DEF2208', 'color' => 'White', 'vehicle_type' => 'Car', 'make_model' => 'Vios']);

        $html = $this->actingAs($driver)->get(route('driver.account'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="theme"'), 'Only the Appearance row may use the theme id.');
        $this->assertStringContainsString('Appearance', $html);
        $this->assertStringContainsString('id="signout-dlg"', $html);
        $this->assertStringContainsString('data-open="#signout-dlg"', $html);
        // The only direct logout form lives inside the confirmation dialog.
        $this->assertSame(1, substr_count($html, route('driver.logout')));

        $home = $this->actingAs($driver)->get(route('driver.home'))->assertOk()->getContent();
        $this->assertSame(0, substr_count($home, 'id="theme"'));
    }

    public function test_driver_history_filters_apply_live_and_can_be_cleared(): void
    {
        $this->lot();
        $driver = $this->make(User::ROLE_DRIVER, 'd@example.com');

        $plain = $this->actingAs($driver)->get(route('driver.history'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Clear filters', $plain);
        $this->assertStringContainsString('data-autosubmit', $plain);

        $filtered = $this->actingAs($driver)->get(route('driver.history', ['range' => '7']))->assertOk()->getContent();
        $this->assertStringContainsString('Clear filters', $filtered);
        // The Apply button exists only for browsers without JavaScript.
        $this->assertMatchesRegularExpression('/<noscript>\s*<button[^>]*>Apply<\/button>\s*<\/noscript>/', $filtered);
    }

    public function test_guard_records_filter_live_with_clear_and_no_visible_apply(): void
    {
        $this->lot();
        $guard = $this->make(User::ROLE_GUARD, 'g@example.com');

        $html = $this->actingAs($guard)->get(route('guard.records'))->assertOk()->getContent();

        $this->assertStringContainsString('data-auto-filter', $html);
        $this->assertStringContainsString('data-range-start', $html);
        $this->assertStringContainsString('data-range-end', $html);
        $this->assertStringContainsString('Clear filters', $html);
        $outsideNoscript = preg_replace('/<noscript>.*?<\/noscript>/s', '', $html);
        $this->assertStringNotContainsString('>Apply<', $outsideNoscript);
    }

    public function test_guard_console_has_a_theme_control_and_confirms_logout(): void
    {
        $this->lot();
        $guard = $this->make(User::ROLE_GUARD, 'g@example.com');

        $html = $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="theme"'));
        $this->assertStringContainsString('id="logout-dlg"', $html);
    }

    public function test_slot_boards_mark_long_parked_vehicles_with_plate_and_duration(): void
    {
        $this->lot();
        $guard = $this->make(User::ROLE_GUARD, 'g@example.com');
        $limit = \App\Support\Lot::longParkingHours();

        $this->park('A-01', 'LNG1234', $limit + 2);
        $this->park('A-02', 'NEW5678', 1);

        $html = $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->getContent();

        $this->assertStringContainsString('Long parked', $html);
        $this->assertStringContainsString('LNG 1234', $html);
        $this->assertSame(1, preg_match_all('/class="sl slot-layout-slot occ long"/', $html), 'Only the slot past the limit is red.');
        $this->assertStringContainsString('1 long-parked vehicle', $html);

        $manager = $this->make(User::ROLE_MANAGER, 'm@example.com');
        $this->actingAs($manager)->get(route('manager.slots.index'))->assertOk()->assertSee('Long parked');

        $att = User::where('role', User::ROLE_ATTENDANT)->first();
        $this->actingAs($att)->get(route('attendant.slots.index'))->assertOk()->assertSee('Long parked');
    }

    public function test_a_raised_threshold_clears_the_long_parked_marking(): void
    {
        $this->lot();
        $guard = $this->make(User::ROLE_GUARD, 'g@example.com');
        $this->park('A-01', 'LNG1234', 14);

        \App\Models\SystemSetting::query()->where('key', 'long_parking_threshold_hours')->update(['value' => json_encode(24)]);
        \Illuminate\Support\Facades\Cache::flush();

        $html = $this->actingAs($guard)->get(route('guard.slots'))->assertOk()->getContent();
        $this->assertSame(0, preg_match_all('/class="sl slot-layout-slot occ long"/', $html));
        $this->assertStringNotContainsString('long-parked vehicle', $html);
    }
}
