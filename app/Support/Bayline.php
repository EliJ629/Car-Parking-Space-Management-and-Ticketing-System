<?php

namespace App\Support;

/** Keeps the zone and slot ordering in one place so every board looks the same. */
class Bayline
{
    /** Slot statuses in the order the legends list them. */
    public const LEGEND = [
        'available' => 'Available',
        'reserved' => 'Reserved',
        'occupied' => 'Occupied',
        'pending_exit' => 'Pending exit',
        'out_of_service' => 'Out of service',
    ];

    /** CSS class used by the slot cells. */
    public static function cell(string $status): string
    {
        return match ($status) {
            'occupied' => 'occ',
            'reserved' => 'res',
            'pending_exit' => 'pend',
            'out_of_service' => 'out',
            default => 'av',
        };
    }
}
