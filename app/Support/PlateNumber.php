<?php

namespace App\Support;

/**
 * The one place that knows what a plate looks like. Every screen, controller, service and
 * query uses this class (Vehicle::normalizePlate and Lot::plate call into it), so the
 * Driver, Guard, Booth Attendant and Manager screens can never disagree.
 *
 * Storage rule: a plate is stored and compared as its compact key, upper case, with no separators
 * ("ABC1234"). The two hyphenated formats keep their hyphen, because it is part of the plate
 * ("12-3456", "1234-12345678901"). The layout of letters and digits decides where the space goes
 * when the key is shown, so "asd1234", "ASD 1234" and "ASD-1234" are the same vehicle.
 *
 * Supported formats (L = letter, D = digit), from the Bayline plate list of October 2026:
 *   Standard four-wheel (current)        LLL DDDD
 *   Standard motorcycle (current)        L DDD LL, DDD LLL
 *   Legacy four-wheel (1981-2014)        LLL DDD
 *   Legacy motorcycle or tricycle        LL DDDD, DDDD LL
 *   Pre-1981 historical                  LL DDD, L DDDDD, DD-DDDD
 *   Vintage (RA 11698)                   LLL DDD (TX, TY, TZ series), DDD LLL, L DDDD LL
 *   Commercial trailer                   LL DDDD (second letter U)
 *   Electric and hybrid four-wheel       LLL DDDD (V, W, X, Y or Z suffix)
 *   Electric and hybrid motorcycle       L DDD LL (V, W, X, Y or Z second-to-last letter)
 *   Vanity (OMVSP)                       LLL DD, LLL DDD, LLL DDDD
 *   Government protocol                  D (1 to 9), DD (10 to 17)
 *   Foreign and diplomatic               DDD DDDD, DDD DDD
 *   Temporary transit, cars              L DDDD, LL DDDD
 *   Temporary transit, motorcycles       DDDD-DDDDDDDDDDD
 * Several of these share a shape (an electric plate is also LLL DDDD), so the pattern below lists each
 * shape once. The fixed letters (U, TX/TY/TZ, V to Z) are series rules inside a shape that is already valid, so
 * they decide the label shown to staff, not whether a plate is accepted.
 *
 * Vehicle type: some shapes belong to one kind of vehicle. A plate typed for a car, SUV, van, pickup or truck
 * ("four") may not be a motorcycle-only shape (L DDD LL, DDDD LL, DDDD-DDDDDDDDDDD), and a plate typed for a
 * motorcycle or tricycle ("two") may not be a car-only shape (LLL DDDD, LLL DDD, LLL DD, L DDDD). The shapes that the
 * list gives to every kind of vehicle (pre-1981, vintage, diplomatic, LL DDDD, DDD LLL, government 1 to 17) pass for both.
 * Other verified special formats can be added in config('bayline.plate_special_patterns') as compact
 * regular expressions, without code changes.
 */
class PlateNumber
{
    /** Longest text a plate field accepts, including separators. The longest plate is 16 characters. */
    public const MAX_INPUT_LENGTH = 20;

    /** Compact-key pattern. The same pattern is used in public/js/plate.js. */
    public const PATTERN = '/^(?:[A-Z]{3}[0-9]{2,4}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{3,4}[A-Z]{2}|[A-Z][0-9]{4,5}|[0-9]{3}[A-Z]{3}|[0-9]{4}[A-Z]{2}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[0-9]{4}-[0-9]{11}|[1-9]|1[0-7])$/';

    public const MESSAGE = 'This is not a supported Philippine plate format. Examples: ABC 1234, ABC 123, A 123 BC, 123 ABC, AB 1234, 1234 AB, 12-3456, 123 4567, or a government plate from 1 to 17.';

    /** Cars, SUVs, vans, pickups and trucks. The same patterns are in public/js/plate.js. */
    public const PATTERN_FOUR = '/^(?:[A-Z]{3}[0-9]{2,4}|[A-Z][0-9]{4}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{5}|[A-Z][0-9]{4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[1-9]|1[0-7])$/';

    /** Motorcycles and tricycles. */
    public const PATTERN_TWO = '/^(?:[A-Z][0-9]{3}[A-Z]{2}|[0-9]{4}[A-Z]{2}|[0-9]{4}-[0-9]{11}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{5}|[A-Z][0-9]{4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[1-9]|1[0-7])$/';

    /**
     * A temporary plate, entered while the vehicle is still in registration. Cars, SUVs, vans and pickups: L DDDD or LL DDDD.
     * Motorcycles and tricycles: DDDD-DDDDDDDDDDD. The same patterns are in public/js/plate.js.
     */
    public const PATTERN_TEMP_FOUR = '/^(?:[A-Z][0-9]{4}|[A-Z]{2}[0-9]{4})$/';

    public const PATTERN_TEMP_TWO = '/^[0-9]{4}-[0-9]{11}$/';

    public const TEMPORARY_MESSAGE = 'A temporary plate looks like T 1234 or AB 1234 for a car, SUV, van or pickup, and 1234-12345678901 for a motorcycle or tricycle.';

    /** What the staff see for a plate, first match wins (several formats share a shape). */
    private const TYPES = [
        '/^[A-Z]U[0-9]{4}$/' => 'Trailer, motorcycle or tricycle',
        '/^T[XYZ][A-Z][0-9]{3}$/' => 'Vintage (TX, TY, TZ series) or legacy four-wheel',
        '/^[A-Z]{3}[0-9]{4}$/' => 'Four-wheel (standard, electric or hybrid)',
        '/^[A-Z]{3}[0-9]{3}$/' => 'Four-wheel (legacy or vintage)',
        '/^[A-Z]{3}[0-9]{2}$/' => 'Vanity plate',
        '/^[A-Z][0-9]{3}[A-Z]{2}$/' => 'Motorcycle (standard, electric or hybrid)',
        '/^[0-9]{3}[A-Z]{3}$/' => 'Motorcycle (standard) or vintage',
        '/^[A-Z]{2}[0-9]{4}$/' => 'Motorcycle, tricycle or temporary (car)',
        '/^[0-9]{4}[A-Z]{2}$/' => 'Motorcycle or tricycle (legacy)',
        '/^[A-Z]{2}[0-9]{3}$/' => 'Pre-1981 plate',
        '/^[A-Z][0-9]{5}$/' => 'Pre-1981 plate',
        '/^[0-9]{2}-[0-9]{4}$/' => 'Pre-1981 plate',
        '/^[A-Z][0-9]{4}[A-Z]{2}$/' => 'Vintage',
        '/^[A-Z][0-9]{4}$/' => 'Temporary transit (car)',
        '/^[0-9]{6,7}$/' => 'Foreign or diplomatic',
        '/^[0-9]{4}-[0-9]{11}$/' => 'Temporary transit (motorcycle)',
        '/^(?:[1-9]|1[0-7])$/' => 'Government protocol',
    ];

    /**
     * The compact key: upper case, separators (space, dash, dot, underscore) removed.
     * A separator is dropped where it sits between a letter and a digit. Between two digits it is kept as "-" when it is a
     * dash (12-3456), dropped after the first three digits of an all-number plate ("123 4567"), and otherwise kept as "_". The same goes for a
     * separator inside a run of letters. A kept "_" leaves the plate invalid, so "AB C 1234" and "ABC 12 34" are never
     * quietly repaired into a different plate.
     */
    public static function normalize(?string $plate): string
    {
        $plate = strtoupper(trim((string) $plate));

        if ($plate === '') {
            return '';
        }

        $tokens = preg_split('/([\s\-._]+)/', $plate, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $numeric = ! preg_match('/[^0-9\s\-._]/', $plate);
        $key = '';
        $sep = null;

        foreach ($tokens as $token) {
            if (preg_match('/^[\s\-._]+$/', $token)) {
                $sep = $token;

                continue;
            }

            if ($key !== '' && $sep !== null) {
                $a = self::kind(substr($key, -1));
                $b = self::kind($token[0]);

                if ($a !== $b && $a !== 'x' && $b !== 'x') {
                    $key .= '';
                } elseif ($a === 'd' && $b === 'd') {
                    $key .= str_contains($sep, '-') ? '-' : ($numeric && strlen($key) === 3 ? '' : '_');
                } else {
                    $key .= '_';
                }
            }

            $key .= $token;
            $sep = null;
        }

        return $key;
    }

    /** 'four' for cars, SUVs, vans, pickups and trucks, 'two' for motorcycles and tricycles, null when the type is not known. */
    public static function wheels(?string $vehicleType): ?string
    {
        $type = strtolower(trim((string) $vehicleType));

        if ($type === '') {
            return null;
        }

        if (in_array($type, ['motorcycle', 'tricycle'], true)) {
            return 'two';
        }

        return in_array($type, ['car', 'suv', 'van', 'pickup', 'truck'], true) ? 'four' : null;
    }

    /** With a vehicle type, the plate must also be a shape that kind of vehicle may carry. */
    public static function isValid(?string $plate, ?string $vehicleType = null): bool
    {
        $key = self::normalize($plate);

        if ($key === '' || strlen((string) $plate) > self::MAX_INPUT_LENGTH * 2) {
            return false;
        }

        $wheels = self::wheels($vehicleType);
        $pattern = $wheels === 'four' ? self::PATTERN_FOUR : ($wheels === 'two' ? self::PATTERN_TWO : self::PATTERN);

        if (preg_match($pattern, $key)) {
            return true;
        }

        foreach (self::specialPatterns() as $special) {
            if (is_string($special) && @preg_match($special, $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /** A temporary registration plate for this kind of vehicle. With no vehicle type, either kind passes. */
    public static function isTemporary(?string $plate, ?string $vehicleType = null): bool
    {
        $key = self::normalize($plate);

        if ($key === '' || strlen((string) $plate) > self::MAX_INPUT_LENGTH * 2) {
            return false;
        }

        $wheels = self::wheels($vehicleType);

        return match ($wheels) {
            'four' => (bool) preg_match(self::PATTERN_TEMP_FOUR, $key),
            'two' => (bool) preg_match(self::PATTERN_TEMP_TWO, $key),
            default => (bool) preg_match(self::PATTERN_TEMP_FOUR, $key) || (bool) preg_match(self::PATTERN_TEMP_TWO, $key),
        };
    }

    public static function temporaryProblem(?string $plate, ?string $vehicleType = null): ?string
    {
        if (self::isTemporary($plate, $vehicleType)) {
            return null;
        }

        // Anything that is not a plate at all gets the ordinary message.
        if (! self::isValid($plate, $vehicleType)) {
            return self::problem($plate, $vehicleType);
        }

        return match (self::wheels($vehicleType)) {
            'four' => 'A temporary plate for a '.strtolower(trim((string) $vehicleType)).' looks like T 1234 or AB 1234. Untick "still in registration" to enter a permanent plate.',
            'two' => 'A temporary plate for a '.strtolower(trim((string) $vehicleType)).' looks like 1234-12345678901. Untick "still in registration" to enter a permanent plate.',
            default => self::TEMPORARY_MESSAGE,
        };
    }

    /** "ASD1234" becomes "ASD 1234", "A1234BC" becomes "A 1234 BC", "1234567" becomes "123 4567". An invalid key is shown as typed, with spaces for the marks. */
    public static function format(?string $plate): string
    {
        $key = self::normalize($plate);

        if ($key === '') {
            return '';
        }

        if (str_contains($key, '_')) {
            return str_replace('_', ' ', $key);
        }

        if (str_contains($key, '-')) {
            return $key;
        }

        if (ctype_digit($key)) {
            return in_array(strlen($key), [6, 7], true) ? substr($key, 0, 3).' '.substr($key, 3) : $key;
        }

        preg_match_all('/[A-Z]+|[0-9]+/', $key, $runs);

        return $key === implode('', $runs[0]) ? implode(' ', $runs[0]) : $key;
    }

    /** A plate with the middle hidden, for places where anyone may look: "LMN1234" becomes "LMN ••34". */
    public static function mask(?string $plate): string
    {
        $key = self::normalize($plate);
        $n = strlen($key);

        if ($n < 4) {
            return $n === 0 ? '' : str_repeat('•', $n);
        }

        $head = $n >= 7 ? 3 : 1;
        $tail = $n >= 7 ? 2 : 1;

        return substr($key, 0, $head).' '.str_repeat('•', $n - $head - $tail).substr($key, -$tail);
    }

    /** What kind of plate this is, or null when it is not a supported plate. */
    public static function type(?string $plate): ?string
    {
        $key = self::normalize($plate);

        foreach (self::TYPES as $pattern => $label) {
            if (preg_match($pattern, $key)) {
                return $label;
            }
        }

        return self::isValid($plate) ? 'Special plate' : null;
    }

    /** The message for an invalid plate, specific to what is wrong. */
    public static function problem(?string $plate, ?string $vehicleType = null): ?string
    {
        if (self::isValid($plate, $vehicleType)) {
            return null;
        }

        $raw = trim((string) $plate);

        if ($raw === '') {
            return 'Enter the plate number.';
        }

        if (strlen($raw) > self::MAX_INPUT_LENGTH) {
            return 'A plate has at most '.self::MAX_INPUT_LENGTH.' characters.';
        }

        if (preg_match('/[^A-Za-z0-9\s\-._]/', $raw)) {
            return 'Use letters and numbers only.';
        }

        $wheels = self::wheels($vehicleType);

        if ($wheels === null) {
            return self::MESSAGE;
        }

        $name = strtolower(trim((string) $vehicleType));

        // A real plate, but for the other kind of vehicle.
        if (self::isValid($plate)) {
            return $wheels === 'four'
                ? 'This plate format belongs to a motorcycle or tricycle. A '.$name.' plate looks like ABC 1234, ABC 123 or ZEN 88.'
                : 'This plate format belongs to a car, SUV, van or pickup. A '.$name.' plate looks like A 123 BC, 123 ABC, AB 1234 or 1234 AB.';
        }

        return $wheels === 'four'
            ? 'This is not a supported plate for a '.$name.'. Examples: ABC 1234, ABC 123, ZEN 88, T 1234, 12-3456, 123 4567, or a government plate from 1 to 17.'
            : 'This is not a supported plate for a '.$name.'. Examples: A 123 BC, 123 ABC, AB 1234, 1234 AB, 12-3456, or 1234-12345678901 for a temporary plate.';
    }

    /**
     * Laravel validation rules: use these everywhere a plate is typed. Pass the name of the vehicle type field
     * ("vehicle_type") where the form has one, so the plate is also checked against the type.
     */
    public static function rules(bool $required = true, ?string $typeField = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            function (string $attribute, mixed $value, \Closure $fail) use ($typeField): void {
                if ($value === null || $value === '') {
                    return;
                }

                $type = $typeField !== null ? (string) request()->input($typeField, '') : null;

                if (! self::isValid((string) $value, $type)) {
                    $fail(self::problem((string) $value, $type) ?? self::MESSAGE);
                }
            },
        ];
    }

    /** Rules for the plate box while "still in registration" is ticked: empty is fine, a plate must be a temporary one. */
    public static function temporaryRules(?string $typeField = null): array
    {
        return [
            'nullable',
            'string',
            function (string $attribute, mixed $value, \Closure $fail) use ($typeField): void {
                if ($value === null || $value === '') {
                    return;
                }

                $type = $typeField !== null ? (string) request()->input($typeField, '') : null;
                $message = self::temporaryProblem((string) $value, $type);

                if ($message !== null) {
                    $fail($message);
                }
            },
        ];
    }

    /** @return array<int, string> */
    private static function specialPatterns(): array
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return [];
        }

        return (array) config('bayline.plate_special_patterns', []);
    }

    private static function kind(string $char): string
    {
        return ctype_digit($char) ? 'd' : (ctype_alpha($char) ? 'l' : 'x');
    }
}
