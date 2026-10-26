<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code', 'driver_id', 'slot_id', 'slot_code', 'plate_number', 'scheduled_at', 'status', 'session_id',
    'vehicle_id', 'checked_in_at', 'expired_at', 'canceled_at',
    'approval_status', 'requires_id', 'id_photo_path', 'approved_by', 'approved_at', 'rejected_at', 'rejection_reason',
])]
class ParkingReservation extends Model
{
    /** Reserved. The screens call this Confirmed. */
    public const STATUS_BOOKED = 'booked';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    /** The booth attendant turned the request down. The slot is released and the driver picks again. */
    public const STATUS_REJECTED = 'rejected';

    /** The attendant has not looked at the request yet. No QR code until it is approved. */
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    /** Statuses that still hold the slot and the vehicle. */
    public const OPEN_STATUSES = [self::STATUS_BOOKED];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'expired_at' => 'datetime',
            'canceled_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'requires_id' => 'boolean',
        ];
    }

    /** Waiting for the booth attendant to look at it. */
    /**
     * Bookings whose hold on the slot is in effect right now: from the start of the hold window (the early check-in time
     * plus the grace time, before the arrival time) until the grace period after it ends. A booking for later today,
     * or tomorrow, does not touch the live slot. A PWD request waiting for the booth attendant only counts when
     * $includePending is true (used to keep walk-ins out of a slot someone has asked for).
     */
    public function scopeHoldingNow($query, bool $includePending = false)
    {
        $before = \App\Support\Lot::earlyMinutes() + \App\Support\Lot::graceMinutes();
        $after = \App\Support\Lot::graceMinutes();

        return $query
            ->where('status', self::STATUS_BOOKED)
            ->when(! $includePending, fn ($q) => $q->where('approval_status', self::APPROVAL_APPROVED))
            ->whereBetween('scheduled_at', [now()->subMinutes($after), now()->addMinutes($before)]);
    }

    public static function slotHeldNow(int $slotId, bool $includePending = false): bool
    {
        return static::query()->where('slot_id', $slotId)->holdingNow($includePending)->exists();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === self::STATUS_BOOKED && $this->approval_status === self::APPROVAL_PENDING;
    }

    /** Approved and not used yet: the only state in which the QR code works. */
    public function hasQr(): bool
    {
        return $this->status === self::STATUS_BOOKED && $this->approval_status === self::APPROVAL_APPROVED;
    }

    /** Checked in, and the driver has paid at the booth: the car is waiting for the guard at the gate. */
    public function isPendingExit(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN
            && $this->session !== null
            && $this->session->status === ParkingSession::STATUS_PAID;
    }

    /** The word the status tag uses for the CSS class and the label, so Pending exit and Awaiting approval have their own colors. */
    public function statusKey(): string
    {
        return match (true) {
            $this->isAwaitingApproval() => 'awaiting',
            $this->isPendingExit() => 'pending_exit',
            default => (string) $this->status,
        };
    }

    public function statusLabel(): string
    {
        if ($this->isAwaitingApproval()) {
            return 'Awaiting approval';
        }

        if ($this->isPendingExit()) {
            return 'Pending exit';
        }

        return match ($this->status) {
            self::STATUS_BOOKED => 'Confirmed',
            self::STATUS_REJECTED => 'Rejected',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'session_id');
    }
}
