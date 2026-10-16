<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code', 'driver_id', 'slot_id', 'slot_code', 'plate_number', 'scheduled_at', 'status', 'session_id',
    'vehicle_id', 'checked_in_at', 'expired_at', 'canceled_at',
])]
class ParkingReservation extends Model
{
    /** Reserved. The screens call this Confirmed. */
    public const STATUS_BOOKED = 'booked';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    /** Statuses that still hold the slot and the vehicle. */
    public const OPEN_STATUSES = [self::STATUS_BOOKED];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'expired_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_BOOKED => 'Confirmed',
            self::STATUS_CHECKED_IN => 'Checked in',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELED => 'Canceled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_BOOKED;
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'session_id');
    }
}
