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
     * Philippine LTO plate formats supported:
     * - Current Standard 4-Wheel & EV/Hybrid 4-Wheel: LLL DDDD (e.g. ABC 1234)
     * - Current Standard Motorcycle & EV/Hybrid Motorcycle: L DDD LL (e.g. N 123 AB), DDD LLL (e.g. 123 ABC), LL DDDDD (e.g. AB 12345)
     * - Legacy 4-Wheel (1981–2014): LLL DDD (e.g. ABC 123)
     * - Legacy Motorcycle / Tricycle: LL DDDD (e.g. AB 1234), DDDD LL (e.g. 1234 AB)
     * - Pre-1981 Historical: LL DDD, L DDDDD, DD-DDDD (e.g. 12-3456)
     * - Vintage Vehicles (RA 11698): LLL DDD, DDD LLL, L DDDD LL (e.g. A 1234 BC)
     * - Commercial Trailers: LL DDDD
     * - Vanity Plates (OMVSP): LLL DD (e.g. ABC 12), LLL DDD, LLL DDDD
     * - Official Protocol plates: 1 (President) through 17 (Trial Judges)
     * - Official Government Agency plates: SENATE, CONGRESS, GOV, PNP + number
     * - Foreign / Diplomatic Organizations: DDD DDDD, DDD DDD
     * - Temporary Transit Formats (Cars): L DDDD, LL DDDD
     * - Temporary Transit Formats (Motorcycles): DDDD-DDDDDDDDDDD (15 digits)
     */
    public const PLATE_PATTERN = '/^(?:[A-Z]{3}[0-9]{2,4}|[A-Z]{2}[0-9]{3,5}|[A-Z][0-9]{4,5}|[A-Z][0-9]{3,4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{4}[A-Z]{2}|[1-9]|1[0-7]|(?:SENATE|CONGRESS|GOV|PNP)[0-9]{1,4}|[0-9]{6,7}|[0-9]{15})$/';

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
