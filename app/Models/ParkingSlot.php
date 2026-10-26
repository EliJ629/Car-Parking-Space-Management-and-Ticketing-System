<?php

namespace App\Models;

use App\Support\Lot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'zone', 'type', 'vehicle_class', 'status', 'notes', 'out_of_service_reason', 'layout_x', 'layout_y'])]
class ParkingSlot extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PENDING_EXIT = 'pending_exit';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const CLASS_CAR = 'car';

    public const CLASS_MOTORCYCLE = 'motorcycle';

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

    protected function casts(): array
    {
        return [
            'layout_x' => 'float',
            'layout_y' => 'float',
        ];
    }

    /** @return array<string, string> */
    public static function vehicleClasses(): array
    {
        return [self::CLASS_CAR => 'Car zone', self::CLASS_MOTORCYCLE => 'Motorcycle zone'];
    }

    public function classLabel(): string
    {
        return self::vehicleClasses()[$this->vehicle_class] ?? 'Car zone';
    }

    /** True for a slot in a motorcycle zone. */
    public function isMotorcycleZone(): bool
    {
        return $this->vehicle_class === self::CLASS_MOTORCYCLE;
    }

    /** Can a vehicle of this type park here? Motorcycles only in motorcycle zones, everything else only in car zones. */
    public function acceptsType(?string $vehicleType): bool
    {
        return ($this->vehicle_class ?: self::CLASS_CAR) === Vehicle::zoneClassFor($vehicleType);
    }

    /** Why a vehicle type cannot use this slot, in words for the screen. Null when it can. */
    public function wrongZoneMessage(?string $vehicleType): ?string
    {
        if ($this->acceptsType($vehicleType)) {
            return null;
        }

        $type = $vehicleType ?: 'This vehicle';

        return $this->isMotorcycleZone()
            ? "Slot {$this->code} is in a motorcycle zone, for motorcycles only. A {$type} parks in a car zone. Choose a slot in a car zone."
            : "Slot {$this->code} is in a car zone. A {$type} parks in a motorcycle zone. Choose a slot in a motorcycle zone.";
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
