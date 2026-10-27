<?php

namespace App\Models;

use App\Support\Lot;
use App\Support\PlateNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'plate_number', 'plate_is_temporary', 'vehicle_type', 'color', 'make_model', 'contact_number'])]
class Vehicle extends Model
{
    /** A removed vehicle keeps its row (a removed mark) so past records still point at it. */
    use SoftDeletes;

    public const TYPES = ['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle', 'Tricycle', 'Truck'];

    /** What a driver can pick. Trucks do not use the lot, so the driver app does not offer them. */
    public const DRIVER_TYPES = ['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle', 'Tricycle'];

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
    public const PLATE_PATTERN = '/^(?:[A-Z]{3}[0-9]{2,4}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{4,5}|[A-Z][0-9]{3,4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{4}[A-Z]{2}|[0-9]{2}-[0-9]{4}|[1-9]|1[0-7]|(?:SENATE|CONGRESS|GOV|PNP)[0-9]{1,4}|[0-9]{6,7}|[0-9]{4}-[0-9]{11}|[0-9]{15})$/';

    public const COLORS = ['White', 'Black', 'Gray', 'Silver', 'Red', 'Blue', 'Green', 'Yellow', 'Orange', 'Brown', 'Other'];

    /** The kind of zone this vehicle parks in: motorcycles in motorcycle zones, everything else (tricycles, SUVs, vans...) in car zones. */
    public static function zoneClassFor(?string $type): string
    {
        return $type === 'Motorcycle' ? ParkingSlot::CLASS_MOTORCYCLE : ParkingSlot::CLASS_CAR;
    }

    public function zoneClass(): string
    {
        return self::zoneClassFor($this->vehicle_type);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Uppercase the plate and drop separators where appropriate ("abc 1234" becomes "ABC1234", "12-3456" stays "12-3456").
     */
    public static function normalizePlate(?string $plate): string
    {
        return PlateNumber::normalize($plate);
    }

    /**
     * Nicely formatted plate for display (e.g. "ABC 1234", "A 123 BC", "1").
     */
    public function displayPlate(): string
    {
        return $this->hasPlate() ? Lot::plate($this->plate_number) : 'No plate yet';
    }

    /** The plate with a note when it is temporary: "T 1234 (temporary)". */
    public function plateLabel(): string
    {
        return $this->displayPlate().($this->hasTemporaryPlate() ? ' (temporary)' : '');
    }

    protected function casts(): array
    {
        return ['plate_is_temporary' => 'boolean'];
    }

    /** The plates this vehicle carried before the current one. */
    public function previousPlates(): HasMany
    {
        return $this->hasMany(VehiclePreviousPlate::class);
    }

    /** True when a plate belongs to a vehicle already: as its current plate (removed vehicles too) or as a plate it carried before. */
    public static function plateTaken(?string $plate): bool
    {
        $key = PlateNumber::normalize($plate);

        return $key !== '' && (self::withTrashed()->where('plate_number', $key)->exists()
            || VehiclePreviousPlate::query()->where('plate_number', $key)->exists());
    }

    /** A temporary plate (the vehicle is still in registration). It can be replaced once, by the permanent plate. */
    public function hasTemporaryPlate(): bool
    {
        return $this->hasPlate() && (bool) $this->plate_is_temporary;
    }

    /** A permanent plate is final: it cannot be changed. */
    public function hasPermanentPlate(): bool
    {
        return $this->hasPlate() && ! $this->plate_is_temporary;
    }

    /** True when the plate can still be set or replaced: there is none yet, or it is a temporary one. */
    public function plateCanBeSet(): bool
    {
        return ! $this->hasPermanentPlate();
    }

    /** False for a vehicle that is still being registered: the driver sets the plate after the LTO issues it. */
    public function hasPlate(): bool
    {
        return $this->plate_number !== null && $this->plate_number !== '';
    }

    /** "Toyota Vios, white sedan" style line. */
    public function describe(): string
    {
        $kind = strtolower($this->color.' '.$this->vehicle_type);

        return $this->make_model ? $this->make_model.', '.$kind : $kind;
    }
}
