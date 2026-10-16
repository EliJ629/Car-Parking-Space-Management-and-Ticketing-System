<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'plate_number', 'vehicle_type', 'color', 'make_model', 'contact_number'])]
class Vehicle extends Model
{
    /** A removed vehicle keeps its row (a removed mark) so past records still point at it. */
    use SoftDeletes;

    public const TYPES = ['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle', 'Truck'];

    /** Three letters then three or four digits, as normalizePlate writes it (ABC1234). */
    public const PLATE_PATTERN = '/^[A-Z]{3}[0-9]{3,4}$/';

    public const COLORS = ['White', 'Black', 'Gray', 'Silver', 'Red', 'Blue', 'Green', 'Yellow', 'Orange', 'Brown', 'Other'];

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

    /** "Toyota Vios, white sedan" style line. */
    public function describe(): string
    {
        $kind = strtolower($this->color.' '.$this->vehicle_type);

        return $this->make_model ? $this->make_model.', '.$kind : $kind;
    }
}
