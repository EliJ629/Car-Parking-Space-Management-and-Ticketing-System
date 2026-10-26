<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * Works out what the plate box and the "still in registration" checkbox mean for one submitted form, so the sign up, the
 * Google finish form and the vehicle form all follow the same rules.
 *
 *   New vehicle (sign up, Google, Add vehicle)
 *     box ticked    the plate is optional. A plate that is typed must be a temporary plate and is saved as temporary.
 *     box unticked  a permanent plate is required.
 *   Existing vehicle with a permanent plate
 *     the plate is final. It cannot be changed, and a different plate in the form is refused.
 *   Existing vehicle with a temporary plate, or with none yet
 *     the plate can be set once: a permanent plate replaces a temporary one (and the old plate stays on record for the
 *     violations), a vehicle with no plate can be given a temporary or a permanent plate. A temporary plate cannot be
 *     swapped for another temporary plate.
 *
 * "changing" is true when a plate is being set or replaced on a vehicle that already exists. That needs the "all details are correct" confirmation,
 * the same as every new vehicle does.
 */
final class PlateEntry
{
    public const PERMANENT_FINAL = 'A saved plate cannot be changed. It stays with the vehicle.';

    public const TEMPORARY_ONLY_ONCE = 'A temporary plate can only be replaced by the permanent plate. Untick "still in registration" and enter the permanent plate.';

    public const SAME_AS_TEMPORARY = 'This is the temporary plate. Enter the permanent plate the LTO issued.';

    /**
     * @param  array<int, mixed>  $rules
     */
    private function __construct(
        public readonly ?string $plate,
        public readonly bool $temporary,
        public readonly bool $changing,
        public readonly array $rules,
    ) {}

    /**
     * Reads the form, puts the plate in its compact form into the request, and returns what to validate and save.
     *
     * @param  array<int, mixed>  $uniqueRules  extra rules for a plate that is going to be saved (for example "not used by another account")
     */
    public static function resolve(Request $request, ?Vehicle $vehicle = null, array $uniqueRules = []): self
    {
        $ticked = $request->boolean('no_plate');
        $typed = Vehicle::normalizePlate($request->input('plate_number'));

        // A new vehicle.
        if ($vehicle === null) {
            if ($ticked) {
                $request->merge(['plate_number' => $typed]);

                return new self($typed !== '' ? $typed : null, $typed !== '', false, array_merge(Rules::temporaryPlate('vehicle_type'), $uniqueRules));
            }

            $request->merge(['plate_number' => $typed]);

            return new self($typed, false, false, array_merge(Rules::parkablePlate(true, 'vehicle_type'), $uniqueRules));
        }

        // A permanent plate is final.
        if ($vehicle->hasPermanentPlate()) {
            $kept = (string) $vehicle->plate_number;
            $currentType = (string) $vehicle->vehicle_type;
            $request->merge(['plate_number' => $typed === '' ? $kept : $typed]);

            return new self($kept, false, false, [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($kept, $currentType): void {
                    if (is_string($value) && $value !== '' && Vehicle::normalizePlate($value) !== $kept) {
                        $fail(self::PERMANENT_FINAL);
                        return;
                    }

                    // The vehicle type may change (a car that is really an SUV). The plate must still suit the new type.
                    $type = (string) request()->input('vehicle_type', '');

                    if ($type !== $currentType && ! PlateNumber::isValid($kept, $type)) {
                        $fail(PlateNumber::problem($kept, $type) ?? PlateNumber::MESSAGE);
                    }
                },
            ]);
        }

        // No plate yet, or a temporary one.
        if ($ticked) {
            if ($vehicle->hasTemporaryPlate()) {
                $kept = (string) $vehicle->plate_number;
                $request->merge(['plate_number' => $typed === '' ? $kept : $typed]);

                return new self($kept, true, false, [
                    'nullable',
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail) use ($kept): void {
                        if (is_string($value) && $value !== '' && Vehicle::normalizePlate($value) !== $kept) {
                            $fail(self::TEMPORARY_ONLY_ONCE);
                        }
                    },
                ]);
            }

            $request->merge(['plate_number' => $typed]);

            return new self($typed !== '' ? $typed : null, $typed !== '', $typed !== '', array_merge(Rules::temporaryPlate('vehicle_type'), $uniqueRules));
        }

        // Unticked: the permanent plate.
        $request->merge(['plate_number' => $typed]);
        $old = $vehicle->hasTemporaryPlate() ? (string) $vehicle->plate_number : null;

        return new self($typed, false, true, array_merge(Rules::parkablePlate(true, 'vehicle_type'), [
            function (string $attribute, mixed $value, \Closure $fail) use ($old): void {
                if ($old !== null && is_string($value) && Vehicle::normalizePlate($value) === $old) {
                    $fail(self::SAME_AS_TEMPORARY);
                }
            },
        ], $uniqueRules));
    }
}
