<?php

namespace App\Models;

use App\Support\Lot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'zone', 'type', 'status', 'notes', 'out_of_service_reason'])]
class ParkingSlot extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PENDING_EXIT = 'pending_exit';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const TYPE_STANDARD = 'Standard';

    public const TYPE_PWD = 'PWD';

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_PENDING_EXIT => 'Pending exit',
            self::STATUS_OUT_OF_SERVICE => 'Out of service',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? 'Unknown';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ParkingReservation::class, 'slot_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'slot_id');
    }

    public function currentSession(): ?ParkingSession
    {
        return $this->sessions()
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    /** The next reservation that still holds this slot (booked, not yet used), on any day. */
    public function nextOpenBooking(): ?ParkingReservation
    {
        return $this->reservations()
            ->whereIn('status', [ParkingReservation::STATUS_BOOKED, ParkingReservation::STATUS_CHECKED_IN])
            ->orderBy('scheduled_at')
            ->first();
    }

    public function canBeTakenOutOfService(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->nextOpenBooking() === null;
    }

    public function canBeRemoved(): bool
    {
        return in_array($this->status, [
            self::STATUS_AVAILABLE,
            self::STATUS_OUT_OF_SERVICE,
        ], true) && $this->nextOpenBooking() === null;
    }

    /** Why the slot cannot leave service or be removed, in words for the screen. Null when it can. */
    public function blockedReason(): ?string
    {
        if ($booking = $this->nextOpenBooking()) {
            return "{$this->code} has reservation {$booking->code} for ".Lot::whenText($booking->scheduled_at).'. Cancel it or let it finish first.';
        }

        if ($this->status !== self::STATUS_AVAILABLE && $this->status !== self::STATUS_OUT_OF_SERVICE) {
            return "{$this->code} is {$this->statusLabel()} and must be available first.";
        }

        return null;
    }
}
