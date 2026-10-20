<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\ParkingDiscount;
use App\Models\ParkingPayment;
use App\Models\ParkingRateSet;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\ManagerDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_database_backed_rates_discounts_and_settings(): void
    {
        $this->seed(ManagerDefaultsSeeder::class);

        $this->assertDatabaseCount('parking_discounts', 3);
        $this->assertDatabaseHas('parking_rate_sets', [
            'first_hour_fee' => 30,
            'additional_hour_fee' => 20,
            'daily_maximum' => 300,
        ]);
        $this->assertSame(12, SystemSetting::value('long_parking_threshold_hours'));
        $this->assertTrue(SystemSetting::value('notify_long_parking'));
    }

    public function test_manager_can_schedule_rates_and_manage_discounts_with_audit_history(): void
    {
        $manager = $this->manager();
        $this->seed(ManagerDefaultsSeeder::class);

        $this->actingAs($manager)
            ->post(route('manager.rates.store'), [
                'first_hour_fee' => 35,
                'additional_hour_fee' => 25,
                'daily_maximum' => 320,
                'lost_ticket_penalty' => 110,
                'overstay_penalty' => 150,
                'effective_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'reason' => 'Peak season adjustment',
            ])
            ->assertRedirect(route('manager.rates.index'));

        $rate = ParkingRateSet::latest('id')->firstOrFail();
        $this->assertSame(35.0, (float) $rate->first_hour_fee);
        $this->assertSame($manager->id, $rate->created_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'rate_set_created']);

        $this->post(route('manager.discounts.store'), [
            'name' => 'Community day',
            'percent' => 15,
            'proof_label' => 'Validation card',
            'requires_manager_pin' => '1',
        ])->assertRedirect(route('manager.rates.index'));

        $discount = ParkingDiscount::where('name', 'Community day')->firstOrFail();
        $this->assertTrue($discount->requires_manager_pin);
        $this->patch(route('manager.discounts.update', $discount), [
            'name' => 'Community day',
            'percent' => 10,
            'proof_label' => 'Validation card',
            'active' => '1',
        ])->assertRedirect(route('manager.rates.index'));
        $this->assertSame(10, $discount->fresh()->percent);

        $this->delete(route('manager.discounts.destroy', $discount))->assertRedirect(route('manager.rates.index'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'discount_deleted']);
    }

    public function test_manager_can_create_and_update_a_private_pin_after_confirming_password(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)
            ->put(route('manager.rates.manager-pin'), [
                'manager_pin' => '028419',
                'manager_pin_confirmation' => '028419',
                'current_password' => 'password',
            ])
            ->assertRedirect(route('manager.rates.index'));

        $manager->refresh();
        $this->assertTrue($manager->hasManagerPin());
        $this->assertTrue($manager->managerPinMatches('028419'));
        $this->assertFalse($manager->managerPinMatches('123456'));
        $this->assertNotSame('028419', $manager->manager_pin_hash);
        $this->assertArrayNotHasKey('manager_pin_hash', $manager->toArray());
        $this->assertDatabaseHas('activity_logs', ['action' => 'manager_pin_created']);

        $this->put(route('manager.rates.manager-pin'), [
            'manager_pin' => '731905',
            'manager_pin_confirmation' => '731905',
            'current_password' => 'password',
        ])->assertRedirect(route('manager.rates.index'));

        $manager->refresh();
        $this->assertTrue($manager->managerPinMatches('731905'));
        $this->assertFalse($manager->managerPinMatches('028419'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'manager_pin_updated']);
    }

    public function test_manager_pin_requires_current_password_matching_confirmation_and_a_nontrivial_pin(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);

        $this->put(route('manager.rates.manager-pin'), [
            'manager_pin' => '028419',
            'manager_pin_confirmation' => '028419',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertFalse($manager->fresh()->hasManagerPin());

        $this->put(route('manager.rates.manager-pin'), [
            'manager_pin' => '028419',
            'manager_pin_confirmation' => '028418',
            'current_password' => 'password',
        ])->assertSessionHasErrors('manager_pin');
        $this->assertFalse($manager->fresh()->hasManagerPin());
        $this->assertArrayNotHasKey('manager_pin', session('_old_input', []));
        $this->assertArrayNotHasKey('manager_pin_confirmation', session('_old_input', []));

        $this->put(route('manager.rates.manager-pin'), [
            'manager_pin' => '123456',
            'manager_pin_confirmation' => '123456',
            'current_password' => 'password',
        ])->assertSessionHasErrors('manager_pin');
        $this->assertFalse($manager->fresh()->hasManagerPin());
    }

    public function test_manager_can_create_edit_reset_and_deactivate_staff_account(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)
            ->post(route('manager.accounts.store'), [
                'name' => 'Carlo Mendoza',
                'username' => 'attendant04',
                'email' => null,
                'phone' => '09175550128',
                'role' => User::ROLE_ATTENDANT,
            ])
            ->assertRedirect(route('manager.accounts.index'))
            ->assertSessionHas('temporaryPassword');

        $account = User::where('username', 'attendant04')->firstOrFail();
        $this->assertNull($account->email);
        $this->assertTrue($account->must_change_password);
        $this->assertSame(User::ROLE_ATTENDANT, $account->role);

        $this->patch(route('manager.accounts.update', $account), [
            'name' => 'Carlo M.',
            'username' => 'attendant04',
            'phone' => '09175550129',
            'role' => User::ROLE_GUARD,
        ])->assertRedirect(route('manager.accounts.index'));
        $this->assertSame(User::ROLE_GUARD, $account->fresh()->role);

        $this->post(route('manager.accounts.reset-password', $account))
            ->assertRedirect()
            ->assertSessionHas('temporaryPassword');
        $account->refresh();
        $this->assertTrue($account->must_change_password);
        $this->assertNull($account->locked_until);

        $this->patch(route('manager.accounts.status', $account))->assertRedirect();
        $this->assertSame('inactive', $account->fresh()->status);
    }

    public function test_temporary_staff_password_must_be_changed_before_staff_tools_are_available(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_ATTENDANT,
            'status' => 'active',
            'username' => 'temporary01',
            'must_change_password' => true,
        ]);

        $this->actingAs($staff)->get(route('staff.home'))
            ->assertRedirect(route('staff.password.change'));

        $this->put(route('staff.password.change.update'), [
            'password' => 'A_New_Secret_123',
            'password_confirmation' => 'A_New_Secret_123',
        ])->assertRedirect(route('staff.home'));

        $this->assertFalse($staff->fresh()->must_change_password);
        $this->assertTrue(Hash::check('A_New_Secret_123', $staff->fresh()->password));
    }

    public function test_manager_can_save_settings_and_changes_are_audited(): void
    {
        $manager = $this->manager();
        $this->seed(ManagerDefaultsSeeder::class);

        $this->actingAs($manager)
            ->put(route('manager.settings.update'), ['settings' => [
                'idle_timeout_minutes' => 20,
                'idle_warning_seconds' => 45,
                'failed_sign_ins_before_lock' => 6,
                'lock_duration_minutes' => 25,
                'long_parking_threshold_hours' => 14,
                'reservation_grace_minutes' => 12,
                'early_check_in_minutes' => 25,
                'maximum_advance_booking_days' => 5,
                'exit_window_minutes' => 18,
                'reservation_reminder_minutes' => 20,
                'notify_reservation_confirmed' => '1',
                'notify_reservation_expired' => '1',
                'notify_long_parking' => '1',
                'notify_payment_exit_approved' => '1',
                'lot_name' => 'Bayline Test Lot',
                'lot_address' => '1 Test Road',
                'receipt_footer' => 'Pay at the booth.',
            ]])
            ->assertRedirect(route('manager.settings.index'));

        $this->assertSame(14, SystemSetting::value('long_parking_threshold_hours'));
        $this->assertSame('Bayline Test Lot', SystemSetting::value('lot_name'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings_updated']);
    }

    public function test_records_sales_reports_and_csv_exports_use_persisted_transactions(): void
    {
        $manager = $this->manager();
        $slot = ParkingSlot::create([
            'code' => 'A-01',
            'zone' => 'Zone A',
            'type' => ParkingSlot::TYPE_STANDARD,
            'status' => ParkingSlot::STATUS_OCCUPIED,
        ]);
        $session = ParkingSession::create([
            'ticket_number' => 'PK-0001',
            'slot_id' => $slot->id,
            'slot_code' => $slot->code,
            'attendant_id' => $manager->id,
            'plate_number' => 'ABC1234',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Car',
            'entered_at' => now()->subHours(2),
            'exited_at' => now()->subHour(),
            'status' => 'completed',
        ]);
        ParkingPayment::create([
            'receipt_number' => 'RC-0001',
            'session_id' => $session->id,
            'received_by' => $manager->id,
            'base_fee' => 30,
            'additional_fee' => 20,
            'penalty' => 0,
            'discount' => 0,
            'total' => 50,
            'method' => 'cash',
            'paid_at' => now(),
            'receipt_snapshot' => ['lot_name' => 'Bayline Central Lot'],
        ]);
        ParkingReservation::create([
            'code' => 'RSV-0001',
            'slot_id' => $slot->id,
            'slot_code' => $slot->code,
            'plate_number' => 'ABC1234',
            'scheduled_at' => now(),
            'status' => 'checked_in',
            'session_id' => $session->id,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.records.index', ['date' => today()->toDateString()]))
            ->assertOk()->assertSee('PK-0001')->assertSee('ABC1234');
        $this->get(route('manager.records.index', ['tab' => 'payments', 'date' => today()->toDateString()]))
            ->assertOk()->assertSee('RC-0001')->assertSee('₱50.00');
        $this->get(route('manager.records.index', ['tab' => 'reservations', 'date' => today()->toDateString()]))
            ->assertOk()->assertSee('RSV-0001');
        $this->get(route('manager.records.sessions.show', $session))->assertOk()->assertSee('RC-0001');

        $this->get(route('manager.sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()->assertSee('Sales and revenue')->assertSee('₱50.00');
        $this->get(route('manager.reports.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()->assertSee('Occupancy and activity');

        $this->get(route('manager.records.export', ['tab' => 'sessions', 'date' => today()->toDateString()]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('manager.sales.export', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('manager.reports.export', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_manager_can_report_and_progress_incidents_and_view_activity_details(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)
            ->post(route('manager.incidents.store'), [
                'category' => 'Vehicle damage',
                'plate_number' => 'ABC1234',
                'location' => 'Zone A',
                'description' => 'Scratched panel by A-01',
            ])
            ->assertRedirect(route('manager.incidents.index'));

        $incident = Incident::firstOrFail();
        $this->get(route('manager.incidents.index'))->assertOk()->assertSee($incident->code);
        $this->get(route('manager.incidents.show', $incident))->assertOk()->assertSee('Scratched panel by A-01');
        $this->patch(route('manager.incidents.update', $incident), [
            'status' => 'resolved',
            'resolution_note' => 'Owner notified',
        ])->assertSessionHasErrors('status');

        $this->patch(route('manager.incidents.update', $incident), [
            'status' => 'under_review',
            'resolution_note' => 'Contacting owner',
        ])->assertRedirect(route('manager.incidents.show', $incident));
        $this->patch(route('manager.incidents.update', $incident), [
            'status' => 'resolved',
            'resolution_note' => 'Owner notified',
        ])->assertRedirect(route('manager.incidents.show', $incident));

        $log = ActivityLog::where('action', 'incident_status_updated')->firstOrFail();
        $this->get(route('manager.activity.index'))->assertOk()->assertSee('incident_status_updated');
        $this->get(route('manager.activity.show', $log))->assertOk()->assertSee('under_review');
        $this->assertDatabaseHas('activity_logs', ['action' => 'incident_reported']);
    }

    public function test_manager_workspace_screens_render_from_persisted_database_state(): void
    {
        $manager = $this->manager();
        $this->seed(ManagerDefaultsSeeder::class);
        $this->actingAs($manager);

        $this->get(route('manager.rates.index'))
            ->assertOk()
            ->assertSee('Rates and policies')
            ->assertSee('manager-pin-dialog')
            ->assertSee('Create manager PIN')
            ->assertSee('Current account password');
        $this->get(route('manager.accounts.index'))->assertOk()->assertSee('Staff accounts');
        $this->get(route('manager.accounts.create'))->assertOk()->assertSee('Create staff account');
        $this->get(route('manager.drivers.index'))->assertOk()->assertSee('Driver accounts');
        $this->get(route('manager.records.index'))->assertOk()->assertSee('Parking sessions');
        $this->get(route('manager.sales.index'))->assertOk()->assertSee('Sales and revenue');
        $this->get(route('manager.reports.index'))->assertOk()->assertSee('Occupancy and activity');
        $this->get(route('manager.activity.index'))->assertOk()->assertSee('Activity log');
        $this->get(route('manager.incidents.index'))->assertOk()->assertSee('Incidents');
        $this->get(route('manager.incidents.create'))->assertOk()->assertSee('Report an incident');
        $this->get(route('manager.settings.index'))->assertOk()->assertSee('Save settings');
        $this->get(route('manager.my-account'))->assertOk()->assertSee('My account');
    }

    private function manager(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
