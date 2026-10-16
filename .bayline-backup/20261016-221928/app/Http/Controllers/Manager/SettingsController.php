<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends ManagerController
{
    private const FIELDS = [
        'idle_timeout_minutes' => ['group' => 'security', 'label' => 'Idle timeout (minutes)', 'min' => 1, 'max' => 1440],
        'idle_warning_seconds' => ['group' => 'security', 'label' => 'Warning before sign out (seconds)', 'min' => 0, 'max' => 300],
        'failed_sign_ins_before_lock' => ['group' => 'security', 'label' => 'Failed sign ins before lock', 'min' => 1, 'max' => 20],
        'lock_duration_minutes' => ['group' => 'security', 'label' => 'Lock time (minutes)', 'min' => 1, 'max' => 1440],
        'long_parking_threshold_hours' => ['group' => 'parking', 'label' => 'Long-parking threshold (hours)', 'min' => 1, 'max' => 168],
        'reservation_grace_minutes' => ['group' => 'parking', 'label' => 'Reservation grace period (minutes)', 'min' => 0, 'max' => 240],
        'early_check_in_minutes' => ['group' => 'parking', 'label' => 'Early check-in window (minutes)', 'min' => 0, 'max' => 1440],
        'maximum_advance_booking_days' => ['group' => 'parking', 'label' => 'Maximum advance booking (days)', 'min' => 1, 'max' => 365],
        'exit_window_minutes' => ['group' => 'parking', 'label' => 'Exit window after payment (minutes)', 'min' => 1, 'max' => 240],
        'reservation_reminder_minutes' => ['group' => 'notifications', 'label' => 'Reservation reminder before arrival (minutes)', 'min' => 0, 'max' => 1440],
        'lot_name' => ['group' => 'receipt', 'label' => 'Lot name', 'max_length' => 120],
        'lot_address' => ['group' => 'receipt', 'label' => 'Address', 'max_length' => 255],
        'receipt_footer' => ['group' => 'receipt', 'label' => 'Receipt footer', 'max_length' => 500],
    ];

    private const NOTIFICATION_FIELDS = [
        'notify_reservation_confirmed',
        'notify_reservation_expired',
        'notify_long_parking',
        'notify_payment_exit_approved',
    ];

    public function index(): View
    {
        $settings = SystemSetting::values();

        return $this->managerPage('Settings', 'settings', view('staff.manager.settings.index', [
            'settings' => $settings,
            'fields' => self::FIELDS,
            'notifications' => self::NOTIFICATION_FIELDS,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::FIELDS as $key => $field) {
            if (isset($field['min'])) {
                $rules["settings.{$key}"] = ['required', 'integer', "min:{$field['min']}", "max:{$field['max']}"];
            } else {
                $rules["settings.{$key}"] = ['required', 'string', 'max:'.$field['max_length']];
            }
        }
        foreach (self::NOTIFICATION_FIELDS as $field) {
            $rules["settings.{$field}"] = ['sometimes', 'boolean'];
        }
        $validated = $request->validate($rules)['settings'];

        $before = SystemSetting::values();
        DB::transaction(function () use ($validated, $request): void {
            foreach (self::FIELDS as $key => $field) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $validated[$key], 'group' => $field['group']],
                );
            }
            foreach (self::NOTIFICATION_FIELDS as $key) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $request->boolean("settings.{$key}"), 'group' => 'notifications'],
                );
            }
        });
        $after = SystemSetting::values();

        ActivityLog::record('settings_updated', $request->user(), $request, 'Manager settings', 'manager', [
            'before' => array_intersect_key($before, $after),
            'after' => $after,
        ]);

        return redirect()->route('manager.settings.index')->with('status', 'Settings were saved.');
    }
}
