<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Support\Rules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsController extends ManagerController
{
    private const FIELDS = [
        'idle_timeout_minutes' => ['group' => 'security', 'label' => 'Idle timeout (minutes)', 'min' => 5, 'max' => 60],
        'idle_warning_seconds' => ['group' => 'security', 'label' => 'Warning before sign out (seconds)', 'min' => 10, 'max' => 120],
        'failed_sign_ins_before_lock' => ['group' => 'security', 'label' => 'Failed sign ins before lock', 'min' => 3, 'max' => 10],
        'lock_duration_minutes' => ['group' => 'security', 'label' => 'Lock time (minutes)', 'min' => 5, 'max' => 60],
        'long_parking_threshold_hours' => ['group' => 'parking', 'label' => 'Long-parking threshold (hours)', 'min' => 1, 'max' => 72],
        'reservation_grace_minutes' => ['group' => 'parking', 'label' => 'Reservation grace period (minutes)', 'min' => 0, 'max' => 240],
        'early_check_in_minutes' => ['group' => 'parking', 'label' => 'Early check-in window (minutes)', 'min' => 0, 'max' => 1440],
        'maximum_advance_booking_days' => ['group' => 'parking', 'label' => 'Maximum advance booking (days)', 'min' => 1, 'max' => 30],
        'maximum_slots_per_zone' => ['group' => 'parking', 'label' => 'Maximum parking slots per zone', 'min' => 1, 'max' => 16, 'default' => 16],
        'maximum_active_zones' => ['group' => 'parking', 'label' => 'Maximum active zones', 'min' => 1, 'max' => 26, 'default' => 26],
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
        $defaults = collect(self::FIELDS)
            ->mapWithKeys(fn (array $field, string $key): array => isset($field['default']) ? [$key => $field['default']] : [])
            ->all();
        $settings = array_merge($defaults, SystemSetting::values());

        return $this->managerPage('Settings', 'settings', view('staff.manager.settings.index', [
            'settings' => $settings,
            'fields' => self::FIELDS,
            'notifications' => self::NOTIFICATION_FIELDS,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $messages = [];
        $attributes = [];
        foreach (self::FIELDS as $key => $field) {
            $attributes["settings.{$key}"] = strtolower($field['label']);

            if (isset($field['min'])) {
                $rules["settings.{$key}"] = ['required', 'integer', "min:{$field['min']}", "max:{$field['max']}"];
                $messages["settings.{$key}.required"] = "Enter a value between {$field['min']} and {$field['max']}.";
                $messages["settings.{$key}.integer"] = "Enter a whole number between {$field['min']} and {$field['max']}.";
                $messages["settings.{$key}.min"] = "Enter a value between {$field['min']} and {$field['max']}.";
                $messages["settings.{$key}.max"] = "Enter a value between {$field['min']} and {$field['max']}.";
            } else {
                $request->merge(['settings' => array_merge((array) $request->input('settings', []), [
                    $key => $key === 'receipt_footer'
                        ? trim((string) data_get($request->input('settings'), $key))
                        : Rules::squish((string) data_get($request->input('settings'), $key)),
                ])]);
                $rules["settings.{$key}"] = ['required', 'string', 'min:2', 'max:'.$field['max_length']];
                $messages["settings.{$key}.required"] = 'This field cannot be empty.';
                $messages["settings.{$key}.min"] = 'Write at least 2 characters.';
                $messages["settings.{$key}.max"] = "Use up to {$field['max_length']} characters.";
            }
        }
        foreach (self::NOTIFICATION_FIELDS as $field) {
            $rules["settings.{$field}"] = ['sometimes', 'boolean'];
        }
        $validated = $request->validate($rules, $messages, $attributes)['settings'];

        // The warning has to fit inside the idle time, or the warning would never show.
        if ((int) $validated['idle_warning_seconds'] >= (int) $validated['idle_timeout_minutes'] * 60) {
            throw ValidationException::withMessages([
                'settings.idle_warning_seconds' => 'The warning must be shorter than the idle timeout.',
            ]);
        }

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
