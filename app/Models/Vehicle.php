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

    /** What a driver can pick. Trucks do not use the lot, so the driver app does not offer them. */
    public const DRIVER_TYPES = ['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle'];

    /**
     * A plate as normalizePlate writes it, without spaces. Four shapes are accepted:
     * ABC1234 or ABC123 (cars and motorcycles), 1234ABC (older plates), AB12345, and the short
     * plates of high offices and government (1 to 4 digits such as 5, or letters and a number such as SENATE3).
     */
    public const PLATE_PATTERN = '/^(?:[A-Z]{3}[0-9]{3,4}|[0-9]{3,4}[A-Z]{2,3}|[A-Z]{2}[0-9]{4,5}|[0-9]{1,4}|[A-Z]{2,8}[0-9]{1,4}|[A-Z][0-9]{3}[A-Z]{2})$/';

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
     * Nicely formatted plate for display (e.g. "ABC 1234", "AB 12345", "1").
     */
    public function displayPlate(): string
    {
        return \App\Support\Lot::plate($this->plate_number);
    }

    /** "Toyota Vios, white sedan" style line. */
    public function describe(): string
    {
        $kind = strtolower($this->color.' '.$this->vehicle_type);

        return $this->make_model ? $this->make_model.', '.$kind : $kind;
    }
}
