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

    /** The compact plate key pattern. The formats themselves live in App\Support\PlateNumber. */
    public const PLATE_PATTERN = PlateNumber::PATTERN;

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
     * The compact plate key: "abc 1234" and "ABC-1234" both become "ABC1234" (see PlateNumber).
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
