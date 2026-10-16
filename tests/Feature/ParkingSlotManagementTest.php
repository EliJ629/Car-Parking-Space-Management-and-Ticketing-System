<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ParkingSlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingSlotManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_dashboard_uses_live_database_slot_counts(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);
        ParkingSlot::create([
            'code' => 'A-02',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_PWD,
            'status' => ParkingSlot::STATUS_OUT_OF_SERVICE,
            'out_of_service_reason' => 'Maintenance',
        ]);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk()
            ->assertSee('Live lot map')
            ->assertSee('A-01')
            ->assertSee('A-02')
            ->assertSee('Maintenance')
            ->assertSee('Parking slots');
    }

    public function test_manager_can_create_edit_and_audit_a_slot(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.slots.store'), [
                'code' => 'a-09',
                'zone' => 'Zone A',
                'type' => ParkingSlot::TYPE_STANDARD,
                'notes' => 'Near the booth',
            ])
            ->assertRedirect(route('manager.slots.index'));

        $slot = ParkingSlot::where('code', 'A-09')->firstOrFail();
        $this->assertSame(ParkingSlot::STATUS_AVAILABLE, $slot->status);
        $this->assertSame('Near the booth', $slot->notes);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'slot_created',
            'subject' => 'A-09',
        ]);

        $this->patch(route('manager.slots.update', $slot), [
            'zone' => 'Zone B',
            'type' => ParkingSlot::TYPE_PWD,
            'notes' => 'Closest to the exit',
        ])->assertRedirect(route('manager.slots.index'));

        $slot->refresh();
        $this->assertSame('A-09', $slot->code);
        $this->assertSame('Zone B', $slot->zone);
        $this->assertSame(ParkingSlot::TYPE_PWD, $slot->type);

        $updateLog = ActivityLog::where('action', 'slot_updated')->firstOrFail();
        $this->assertSame('Zone A', $updateLog->details['before']['zone']);
        $this->assertSame('Zone B', $updateLog->details['after']['zone']);
    }

    public function test_manager_can_take_an_available_slot_out_of_service_and_restore_it(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $slot = ParkingSlot::create([
            'code' => 'B-04',
            'zone' => 'Zone B',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->actingAs($manager)
            ->patch(route('manager.slots.service.update', $slot), [
                'reason' => 'Maintenance',
                'notes' => 'Broken bollard',
            ])
            ->assertRedirect(route('manager.slots.index'));

        $slot->refresh();
        $this->assertSame(ParkingSlot::STATUS_OUT_OF_SERVICE, $slot->status);
        $this->assertSame('Maintenance', $slot->out_of_service_reason);
        $this->assertSame('Broken bollard', $slot->notes);

        $this->patch(route('manager.slots.service.update', $slot))
            ->assertRedirect(route('manager.slots.index'));

        $slot->refresh();
        $this->assertSame(ParkingSlot::STATUS_AVAILABLE, $slot->status);
        $this->assertNull($slot->out_of_service_reason);
        $this->assertDatabaseHas('activity_logs', ['action' => 'slot_returned_to_service']);
    }

    public function test_slots_in_use_cannot_be_removed_or_manually_taken_out_of_service(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $slot = ParkingSlot::create([
            'code' => 'A-07',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_PENDING_EXIT,
        ]);

        $this->actingAs($manager)
            ->delete(route('manager.slots.destroy', $slot))
            ->assertSessionHasErrors('slot');

        $this->patch(route('manager.slots.service.update', $slot), ['reason' => 'Maintenance'])
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseHas('parking_slots', [
            'id' => $slot->id,
            'status' => ParkingSlot::STATUS_PENDING_EXIT,
        ]);
    }

    public function test_non_managers_cannot_access_manager_slot_tools(): void
    {
        $attendant = User::factory()->create([
            'role' => User::ROLE_ATTENDANT,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($attendant)
            ->get(route('manager.slots.index'))
            ->assertForbidden();
    }

    public function test_manager_cannot_create_a_duplicate_slot_code(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->actingAs($manager)
            ->from(route('manager.slots.create'))
            ->post(route('manager.slots.store'), [
                'code' => 'A-01',
                'zone' => 'Zone A',
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.create'))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('parking_slots', 1);
    }

    public function test_manager_slot_views_render_and_filters_keep_their_query_values(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $available = ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);
        ParkingSlot::create([
            'code' => 'A-02',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_RESERVED,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.slots.create'))
            ->assertOk()
            ->assertSee('Add parking slot');

        $this->get(route('manager.slots.index', ['status' => ParkingSlot::STATUS_AVAILABLE, 'zone' => 'Zone A']))
            ->assertOk()
            ->assertSee('A-01')
            ->assertDontSee('A-02')
            ->assertSee('Filter');

        $this->get(route('manager.slots.edit', $available))
            ->assertOk()
            ->assertSee('Edit slot A-01')
            ->assertSee('readonly', false);

        $this->get(route('manager.slots.service.edit', $available))
            ->assertOk()
            ->assertSee('Take A-01 out of service');
    }

    public function test_parking_slot_seeder_creates_ten_slots_per_zone_without_overwriting_existing_slots(): void
    {
        $this->seed(ParkingSlotSeeder::class);

        $this->assertDatabaseCount('parking_slots', 40);

        foreach (range('A', 'D') as $zoneLetter) {
            $this->assertSame(
                10,
                ParkingSlot::where('zone', "Zone {$zoneLetter}")->count(),
            );
            $this->assertDatabaseHas('parking_slots', [
                'code' => "{$zoneLetter}-01",
                'zone' => "Zone {$zoneLetter}",
                'type' => ParkingSlot::TYPE_PWD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
            $this->assertDatabaseHas('parking_slots', [
                'code' => "{$zoneLetter}-10",
                'zone' => "Zone {$zoneLetter}",
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
        }

        ParkingSlot::where('code', 'A-01')->update([
            'status' => ParkingSlot::STATUS_OUT_OF_SERVICE,
            'out_of_service_reason' => 'Maintenance',
        ]);
        $this->seed(ParkingSlotSeeder::class);

        $this->assertDatabaseCount('parking_slots', 40);
        $this->assertDatabaseHas('parking_slots', [
            'code' => 'A-01',
            'status' => ParkingSlot::STATUS_OUT_OF_SERVICE,
            'out_of_service_reason' => 'Maintenance',
        ]);
    }

    public function test_database_seeder_includes_parking_slots(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('parking_slots', 40);

        foreach (range('A', 'D') as $zoneLetter) {
            $this->assertSame(10, ParkingSlot::where('zone', "Zone {$zoneLetter}")->count());
        }
    }
}
