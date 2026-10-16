<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'plate_number', 'vehicle_type', 'color'])]
class Vehicle extends Model
{
    public const TYPES = ['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle', 'Truck'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Uppercase the plate and drop spaces and dashes: "abc 1234" becomes "ABC1234".
     */
    public static function normalizePlate(?string $plate): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', (string) $plate));
    }

    /**
     * "ABC 1234" for display. Plates of seven characters or fewer split after three.
     */
    public function displayPlate(): string
    {
        $plate = $this->plate_number;

        return strlen($plate) > 3 ? substr($plate, 0, 3).' '.substr($plate, 3) : $plate;
    }
}
