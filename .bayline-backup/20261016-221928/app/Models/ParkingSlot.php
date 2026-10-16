<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

    public function canBeTakenOutOfService(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function canBeRemoved(): bool
    {
        return in_array($this->status, [
            self::STATUS_AVAILABLE,
            self::STATUS_OUT_OF_SERVICE,
        ], true);
    }
}
