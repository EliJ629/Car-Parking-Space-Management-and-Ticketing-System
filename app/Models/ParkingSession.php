<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'ticket_number',
    'driver_id',
    'slot_id',
    'slot_code',
    'attendant_id',
    'plate_number',
    'vehicle_color',
    'vehicle_type',
    'entered_at',
    'exited_at',
    'status',
])]
class ParkingSession extends Model
{
    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'exited_at' => 'datetime'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(ParkingPayment::class, 'session_id');
    }
}
