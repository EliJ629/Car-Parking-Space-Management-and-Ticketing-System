<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ParkingSlotSeeder;
use Database\Seeders\ParkingSlotStatusSeeder;
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
            ->assertSee('Live parking map')
            ->assertSee('A-02')
            ->assertSee('Maintenance')
            ->assertSee('Parking slots');
    }

    public function test_manager_dashboard_shows_report_analytics_snapshot(): void
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
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Reports snapshot')
            ->assertSee('Average occupancy')
            ->assertSee('Peak entry hour')
            ->assertSee('Open full report');
    }

    public function test_manager_live_map_shows_one_existing_floor_at_a_time(): void
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
        ParkingSlot::create([
            'code' => 'E-01',
            'zone' => 'Zone E',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.map'))
            ->assertOk()
            ->assertSee('Live parking map')
            ->assertSee('layout-editor-dialog', false)
            ->assertSee('Floor 1')
            ->assertSee('Floor 2')
            ->assertSee('A-01')
            ->assertSee('A-02')
            ->assertDontSee('>E-01<', false)
            ->assertDontSee('Floor 3');

        $this->get(route('manager.map', ['floor' => 2]))
            ->assertOk()
            ->assertSee('E-01')
            ->assertDontSee('>A-01<', false)
            ->assertDontSee('>A-02<', false);
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

    public function test_manager_slot_creation_generates_code_from_zone_and_number(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.slots.store'), [
                'zone' => 'Zone B',
                'slot_number' => 16,
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.index'));

        $this->assertDatabaseHas('parking_slots', [
            'code' => 'B-16',
            'zone' => 'Zone B',
        ]);
    }

    public function test_manager_slot_creation_rejects_numbers_above_sixteen(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.slots.store'), [
                'zone' => 'Zone B',
                'slot_number' => 17,
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertSessionHasErrors('slot_number');
    }

    public function test_manager_classifies_a_zone_and_rejects_a_conflicting_slot_classification(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.slots.store'), [
                'zone' => 'Zone C',
                'slot_number' => 1,
                'vehicle_type' => ParkingLayoutZone::VEHICLE_MOTORCYCLE,
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.index'));

        $this->assertDatabaseHas('parking_layout_zones', [
            'zone' => 'Zone C',
            'vehicle_type' => ParkingLayoutZone::VEHICLE_MOTORCYCLE,
        ]);

        $this->post(route('manager.slots.store'), [
            'zone' => 'Zone C',
            'slot_number' => 2,
            'vehicle_type' => ParkingLayoutZone::VEHICLE_CAR,
            'type' => ParkingSlot::TYPE_STANDARD,
        ])->assertSessionHasErrors('vehicle_type');
    }

    public function test_manager_can_edit_a_zone_category_from_zone_categories_page(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        ParkingSlot::create([
            'code' => 'D-01',
            'zone' => 'Zone D',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.slots.categories'))
            ->assertOk()
            ->assertSee('Zone category')
            ->assertSee('Zone D');

        $this->patch(route('manager.slots.categories.update'), [
            'categories' => ['Zone D' => ParkingLayoutZone::VEHICLE_MOTORCYCLE],
        ])->assertRedirect(route('manager.slots.index'));

        $this->assertDatabaseHas('parking_layout_zones', [
            'zone' => 'Zone D',
            'vehicle_type' => ParkingLayoutZone::VEHICLE_MOTORCYCLE,
        ]);
    }

    public function test_manager_live_map_shows_zone_vehicle_classification(): void
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
        ParkingLayoutZone::create([
            'zone' => 'Zone A',
            'vehicle_type' => ParkingLayoutZone::VEHICLE_MOTORCYCLE,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.map'))
            ->assertOk()
            ->assertSee('Zone A')
            ->assertSee('(Motorcycle)');
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

    public function test_occupied_and_reserved_slots_cannot_be_edited(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach ([ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_RESERVED] as $status) {
            $slot = ParkingSlot::create([
                'code' => $status === ParkingSlot::STATUS_OCCUPIED ? 'A-07' : 'A-08',
                'zone' => 'Zone A',
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => $status,
            ]);

            $this->actingAs($manager)
                ->get(route('manager.slots.edit', $slot))
                ->assertForbidden();

            $this->patch(route('manager.slots.update', $slot), [
                'zone' => 'Zone B',
                'type' => ParkingSlot::TYPE_PWD,
            ])->assertSessionHasErrors('slot');

            $this->assertDatabaseHas('parking_slots', [
                'id' => $slot->id,
                'zone' => 'Zone A',
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => $status,
            ]);
        }
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

        $this->get(route('manager.map'))
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
            ->assertSee('Add parking slot')
            ->assertSee('Floor 1')
            ->assertSee('value="1" selected', false)
            ->assertSee('Zones: 1 / 4')
            ->assertSee('A floor can only contain 4 zones.')
            ->assertSee('Add zone');

        $this->get(route('manager.slots.index', ['status' => ParkingSlot::STATUS_AVAILABLE, 'zone' => 'Zone A']))
            ->assertOk()
            ->assertSee('A-01')
            ->assertDontSee('A-02')
            ->assertSee('data-auto-filter', false)
            ->assertDontSee('>Apply<', false)
            ->assertSee('Floor');

        $this->get(route('manager.slots.index', ['floor' => 1]))
            ->assertOk()
            ->assertSee('A-01')
            ->assertSee('A-02');

        $this->get(route('manager.slots.index', ['floor' => 2]))
            ->assertOk()
            ->assertDontSee('>A-01<', false)
            ->assertDontSee('>A-02<', false);

        $this->get(route('manager.slots.edit', $available))
            ->assertOk()
            ->assertSee('Edit slot A-01')
            ->assertSee('readonly', false);

        $this->get(route('manager.slots.service.edit', $available))
            ->assertOk()
            ->assertSee('Take A-01 out of service');
    }

    public function test_slot_and_zone_limits_are_enforced_on_creation_and_zone_moves(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        SystemSetting::updateOrCreate(['key' => 'maximum_slots_per_zone'], ['value' => json_encode(2), 'group' => 'parking']);
        SystemSetting::updateOrCreate(['key' => 'maximum_active_zones'], ['value' => json_encode(2), 'group' => 'parking']);

        foreach (['A-01', 'A-02'] as $code) {
            ParkingSlot::create([
                'code' => $code,
                'zone' => 'Zone A',
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
        }

        $this->actingAs($manager)
            ->post(route('manager.slots.store'), ['code' => 'A-03', 'zone' => 'Zone A', 'type' => ParkingSlot::TYPE_STANDARD])
            ->assertSessionHasErrors('zone');

        ParkingSlot::create([
            'code' => 'B-01',
            'zone' => 'Zone B',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->post(route('manager.slots.store'), ['code' => 'C-01', 'zone' => 'Zone C', 'type' => ParkingSlot::TYPE_STANDARD])
            ->assertSessionHasErrors('zone');

        $zoneB = ParkingSlot::where('code', 'B-01')->firstOrFail();
        $this->patch(route('manager.slots.update', $zoneB), ['zone' => 'Zone A', 'type' => ParkingSlot::TYPE_STANDARD])
            ->assertSessionHasErrors('zone');

        $this->flushSession();

        $zoneA = ParkingSlot::where('code', 'A-01')->firstOrFail();
        $this->patch(route('manager.slots.update', $zoneA), ['zone' => 'Zone A', 'type' => ParkingSlot::TYPE_STANDARD, 'notes' => 'Near lift'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Near lift', $zoneA->fresh()->notes);
    }

    public function test_manager_cannot_open_a_fifth_zone_or_wrong_floor_on_a_full_floor(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach (['A' => 'A-01', 'B' => 'B-01', 'C' => 'C-01', 'D' => 'D-01'] as $letter => $code) {
            ParkingSlot::create([
                'code' => $code,
                'zone' => "Zone {$letter}",
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
        }

        $this->actingAs($manager)
            ->get(route('manager.slots.create'))
            ->assertOk()
            ->assertSee('Zones: 4 / 4')
            ->assertSee('This floor already has 4 zones.')
            ->assertSee('Floor 2')
            ->assertSee('disabled', false);

        $this->from(route('manager.slots.create'))
            ->post(route('manager.slots.store'), [
                'code' => 'E-01',
                'floor' => 1,
                'zone' => 'Zone E',
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.create'))
            ->assertSessionHasErrors([
                'zone' => 'Choose a zone that belongs to the selected floor. A floor can only contain 4 zones.',
            ]);

        $this->assertDatabaseMissing('parking_slots', ['code' => 'E-01']);

        $this->post(route('manager.slots.store'), [
            'code' => 'A-09',
            'floor' => 1,
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
        ])->assertRedirect(route('manager.slots.index'));

        $this->assertDatabaseHas('parking_slots', [
            'code' => 'A-09',
            'zone' => 'Zone A',
        ]);
    }

    public function test_next_floor_opens_only_after_four_zones_and_accepts_the_next_zone_group(): void
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
                'code' => 'E-01',
                'floor' => 2,
                'zone' => 'Zone E',
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.create'))
            ->assertSessionHasErrors([
                'floor' => 'That floor is not available yet. A new floor opens after the current floor has 4 zones.',
            ]);

        $this->assertDatabaseMissing('parking_slots', ['code' => 'E-01']);

        foreach (['B' => 'B-01', 'C' => 'C-01', 'D' => 'D-01'] as $letter => $code) {
            ParkingSlot::create([
                'code' => $code,
                'zone' => "Zone {$letter}",
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
        }

        $this->post(route('manager.slots.store'), [
            'code' => 'E-01',
            'floor' => 2,
            'zone' => 'Zone E',
            'type' => ParkingSlot::TYPE_STANDARD,
        ])->assertRedirect(route('manager.slots.index'));

        $this->assertDatabaseHas('parking_slots', [
            'code' => 'E-01',
            'zone' => 'Zone E',
        ]);
        $this->assertSame(2, ParkingSlot::where('code', 'E-01')->firstOrFail()->floorNumber());

        $this->get(route('manager.slots.create'))
            ->assertOk()
            ->assertSee('value="1" selected', false);

        $this->get(route('manager.slots.edit', ParkingSlot::where('code', 'E-01')->firstOrFail()))
            ->assertOk()
            ->assertSee('value="2" selected', false)
            ->assertDontSee('value="1" selected', false);
    }

    public function test_manager_cannot_move_a_slot_onto_a_full_floor_as_a_new_zone(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach (['A' => 'A-01', 'B' => 'B-01', 'C' => 'C-01', 'D' => 'D-01'] as $letter => $code) {
            ParkingSlot::create([
                'code' => $code,
                'zone' => "Zone {$letter}",
                'type' => ParkingSlot::TYPE_STANDARD,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);
        }
        $floorTwo = ParkingSlot::create([
            'code' => 'E-01',
            'zone' => 'Zone E',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        $this->actingAs($manager)
            ->from(route('manager.slots.edit', $floorTwo))
            ->patch(route('manager.slots.update', $floorTwo), [
                'floor' => 1,
                'zone' => 'Zone E',
                'type' => ParkingSlot::TYPE_STANDARD,
            ])
            ->assertRedirect(route('manager.slots.edit', $floorTwo))
            ->assertSessionHasErrors([
                'zone' => 'Choose a zone that belongs to the selected floor. A floor can only contain 4 zones.',
            ]);

        $this->assertSame('Zone E', $floorTwo->fresh()->zone);
    }

    public function test_manager_cannot_save_layout_positions_while_a_slot_is_occupied_without_confirmation(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $slot = ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_OCCUPIED,
        ]);

        $this->actingAs($manager)
            ->put(route('manager.layout.update'), [
                'positions' => [
                    ['id' => $slot->id, 'x' => 0.42, 'y' => 0.68],
                ],
            ])
            ->assertSessionHasErrors('positions');

        $slot->refresh();
        $this->assertSame(ParkingSlot::STATUS_OCCUPIED, $slot->status);
        $this->assertSame('Zone A', $slot->zone);
        $this->assertNull($slot->layout_x);
        $this->assertNull($slot->layout_y);
    }

    public function test_manager_can_confirm_password_before_saving_layout_with_an_occupied_slot(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $slot = ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_OCCUPIED,
        ]);

        $this->actingAs($manager)
            ->postJson(route('manager.layout.authorize'), ['password' => 'password'])
            ->assertOk()
            ->assertJson(['authorized' => true]);

        $this->put(route('manager.layout.update'), [
            'positions' => [
                ['id' => $slot->id, 'x' => 0.42, 'y' => 0.68],
            ],
        ])->assertRedirect(route('manager.map', ['floor' => 1]));

        $slot->refresh();
        $this->assertSame(ParkingSlot::STATUS_OCCUPIED, $slot->status);
        $this->assertEqualsWithDelta(0.42, $slot->layout_x, 0.000001);
        $this->assertEqualsWithDelta(0.68, $slot->layout_y, 0.000001);
    }

    public function test_manager_layout_confirmation_rejects_an_incorrect_password(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->postJson(route('manager.layout.authorize'), ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_manager_layout_rejects_unknown_slots_and_out_of_bounds_positions(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->put(route('manager.layout.update'), [
                'positions' => [
                    ['id' => 99999, 'x' => 0.5, 'y' => 0.5],
                ],
            ])
            ->assertSessionHasErrors('positions');

        $this->put(route('manager.layout.update'), [
            'positions' => [
                ['id' => 99999, 'x' => 1.1, 'y' => -0.1],
            ],
        ])->assertSessionHasErrors(['positions.0.x', 'positions.0.y']);
    }

    public function test_manager_can_save_zone_label_positions_separately_from_slots(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $slot = ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
            'layout_x' => 0.2,
            'layout_y' => 0.3,
        ]);

        $this->actingAs($manager)
            ->put(route('manager.layout.update'), [
                'positions' => [['id' => $slot->id, 'x' => 0.7, 'y' => 0.8]],
                'zone_positions' => [['zone' => 'Zone A', 'x' => 0.12, 'y' => 0.18]],
            ])
            ->assertRedirect(route('manager.map', ['floor' => 1]));

        $slot->refresh();
        $label = ParkingLayoutZone::where('zone', 'Zone A')->firstOrFail();
        $this->assertEqualsWithDelta(0.7, $slot->layout_x, 0.000001);
        $this->assertEqualsWithDelta(0.8, $slot->layout_y, 0.000001);
        $this->assertEqualsWithDelta(0.12, $label->label_x, 0.000001);
        $this->assertEqualsWithDelta(0.18, $label->label_y, 0.000001);
        $this->assertSame('Zone A', $slot->zone);
    }

    public function test_driver_sees_saved_layout_read_only(): void
    {
        $driver = User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'status' => User::STATUS_ACTIVE,
        ]);
        ParkingSlot::create([
            'code' => 'B-04',
            'zone' => 'Zone B',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_AVAILABLE,
            'layout_x' => 0.31,
            'layout_y' => 0.57,
        ]);

        $this->actingAs($driver)
            ->get(route('driver.find'))
            ->assertOk()
            ->assertSee('data-layout-readonly', false)
            ->assertSee('data-x="0.31"', false)
            ->assertSee('data-y="0.57"', false)
            ->assertSee('data-layout-zone-label', false)
            ->assertDontSee('data-layout-edit', false);
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

    public function test_demo_status_seeder_covers_each_live_map_status(): void
    {
        $this->seed(ParkingSlotSeeder::class);
        $this->seed(ParkingSlotStatusSeeder::class);

        $this->assertSame(ParkingSlot::STATUS_AVAILABLE, ParkingSlot::where('code', 'A-01')->value('status'));
        $this->assertSame(ParkingSlot::STATUS_OCCUPIED, ParkingSlot::where('code', 'A-02')->value('status'));
        $this->assertSame(ParkingSlot::STATUS_RESERVED, ParkingSlot::where('code', 'A-03')->value('status'));
        $this->assertSame(ParkingSlot::STATUS_PENDING_EXIT, ParkingSlot::where('code', 'A-04')->value('status'));
        $this->assertSame(ParkingSlot::STATUS_OUT_OF_SERVICE, ParkingSlot::where('code', 'A-05')->value('status'));
        $this->assertSame('Demo maintenance', ParkingSlot::where('code', 'A-05')->value('out_of_service_reason'));
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
