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

    /**
     * Philippine LTO formats supported:
     * - Current standard: LLL-DDDD (car) and LL-DDDDD (motorcycle)
     * - Legacy (1981-2014): LLL-DDD (car), LL-DDDD (motorcycle), DDDD-LL (regional motorcycle)
     * - Vintage (pre-1981): L-DDD-LL, DDD-LLL
     * - Official Protocol plates: 1 (President) through 17 (Trial Judges)
     */
    public const PLATE_PATTERN = '/^(?:[A-Z]{3}[0-9]{3,4}|[A-Z]{2}[0-9]{4,5}|[0-9]{4}[A-Z]{2}|[A-Z][0-9]{3}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[1-9]|1[0-7])$/';

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
