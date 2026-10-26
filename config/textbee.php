<?php

/*
|--------------------------------------------------------------------------
| TextBee (SMS reminders)
|--------------------------------------------------------------------------
| TextBee sends text messages through an Android phone that runs its app.
| Reminders stay off until TEXTBEE_ENABLED=true and the key and device id are set.
*/
return [
    'enabled' => (bool) env('TEXTBEE_ENABLED', false),
    'api_key' => env('TEXTBEE_API_KEY'),
    'device_id' => env('TEXTBEE_DEVICE_ID'),
    'base_url' => rtrim((string) env('TEXTBEE_BASE_URL', 'https://api.textbee.dev/api/v1'), '/'),

    /** Minutes before the reservation time when the reminder goes out. */
    'reminder_minutes' => max(5, (int) env('TEXTBEE_REMINDER_MINUTES', 30)),
];
