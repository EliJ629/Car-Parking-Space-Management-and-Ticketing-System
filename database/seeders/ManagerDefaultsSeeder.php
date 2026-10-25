<?php

namespace Database\Seeders;

use App\Models\ParkingDiscount;
use App\Models\ParkingRateSet;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class ManagerDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ParkingRateSet::firstOrCreate(
            ['effective_at' => '2026-10-01 00:00:00'],
            [
                'first_hour_fee' => 30,
                'additional_hour_fee' => 20,
                'daily_maximum' => 300,
                'lost_ticket_penalty' => 100,
                'overstay_penalty' => 150,
                'reason' => 'Initial Bayline parking rates',
            ],
        );

        foreach ([
            ['name' => 'Senior citizen', 'percent' => 20, 'proof_label' => 'ID number', 'requires_manager_pin' => false],
            ['name' => 'PWD', 'percent' => 20, 'proof_label' => 'ID number', 'requires_manager_pin' => false],
            ['name' => 'Tenant validation', 'percent' => 50, 'proof_label' => 'Stamp code', 'requires_manager_pin' => true],
        ] as $discount) {
            ParkingDiscount::firstOrCreate(['name' => $discount['name']], $discount);
        }

        foreach ([
            'idle_timeout_minutes' => ['group' => 'security', 'value' => 15],
            'idle_warning_seconds' => ['group' => 'security', 'value' => 60],
            'failed_sign_ins_before_lock' => ['group' => 'security', 'value' => 5],
            'lock_duration_minutes' => ['group' => 'security', 'value' => 15],
            'long_parking_threshold_hours' => ['group' => 'parking', 'value' => 12],
            'reservation_grace_minutes' => ['group' => 'parking', 'value' => 10],
            'early_check_in_minutes' => ['group' => 'parking', 'value' => 30],
            'maximum_advance_booking_days' => ['group' => 'parking', 'value' => 2],
            'exit_window_minutes' => ['group' => 'parking', 'value' => 15],
            'notify_reservation_confirmed' => ['group' => 'notifications', 'value' => true],
            'notify_reservation_expired' => ['group' => 'notifications', 'value' => true],
            'notify_long_parking' => ['group' => 'notifications', 'value' => true],
            'notify_payment_exit_approved' => ['group' => 'notifications', 'value' => true],
            'reservation_reminder_minutes' => ['group' => 'notifications', 'value' => 30],
            'lot_name' => ['group' => 'receipt', 'value' => 'Bayline Central Lot'],
            'lot_address' => ['group' => 'receipt', 'value' => '12 Rizal Avenue, Davao City'],
            'receipt_footer' => ['group' => 'receipt', 'value' => 'Keep this receipt. Pay at the booth before the exit gate.'],
        ] as $key => $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $setting['value'], 'group' => $setting['group']],
            );
        }
    }
}
