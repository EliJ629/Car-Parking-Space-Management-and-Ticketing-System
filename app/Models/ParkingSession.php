<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'exit_approved_by',
    'lost_ticket',
    'driver_phone',
    'exit_window_expired_at',
])]
class ParkingSession extends Model
{
    /** Parked and not yet paid. */
    public const STATUS_ACTIVE = 'active';

    /** Paid at the booth and waiting for the Guard to approve the exit. */
    public const STATUS_PAID = 'paid';

    /** The Guard approved the exit and the slot was released. */
    public const STATUS_COMPLETED = 'completed';

    /** Sessions that still hold a slot and a plate. */
    public const OPEN_STATUSES = [self::STATUS_ACTIVE, self::STATUS_PAID];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'exit_window_expired_at' => 'datetime',
            'lost_ticket' => 'boolean',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
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

    public function exitApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exit_approved_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(ParkingPayment::class, 'session_id');
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(ParkingReservation::class, 'session_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'session_id');
    }
}
