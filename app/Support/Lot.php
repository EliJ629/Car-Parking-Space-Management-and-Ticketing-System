<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Small helpers shared by the Driver app and the Guard console: the lot's clock,
 * how times and money are written on screen, and the plate and phone formats.
 *
 * Times are stored in UTC and shown in the lot's own time zone.
 */
class Lot
{
    public const TIMEZONE = 'Asia/Manila';

    public static function now(): Carbon
    {
        return now()->setTimezone(self::TIMEZONE);
    }

    public static function local(?CarbonInterface $time): ?Carbon
    {
        return $time ? Carbon::instance($time)->setTimezone(self::TIMEZONE) : null;
    }

    /** "6:15 PM" */
    public static function time(?CarbonInterface $time): string
    {
        return $time ? self::local($time)->format('g:i A') : '-';
    }

    /** "Oct 15, 5:28 PM" */
    public static function stamp(?CarbonInterface $time): string
    {
        return $time ? self::local($time)->format('M j, g:i A') : '-';
    }

    /** "Today, 6:15 PM" or "Fri, Oct 16, 8:00 AM" */
    public static function when(?CarbonInterface $time): string
    {
        if (! $time) {
            return '-';
        }

        $local = self::local($time);

        return $local->isSameDay(self::now())
            ? 'Today, '.$local->format('g:i A')
            : $local->format('D, M j, g:i A');
    }

    /** "today at 6:15 PM" or "Fri, Oct 16 at 8:00 AM", for use inside a sentence. */
    public static function whenText(?CarbonInterface $time): string
    {
        if (! $time) {
            return '-';
        }

        $local = self::local($time);

        return $local->isSameDay(self::now())
            ? 'today at '.$local->format('g:i A')
            : $local->format('D, M j').' at '.$local->format('g:i A');
    }

    /** "Fri, Oct 16, 2026" */
    public static function date(?CarbonInterface $time): string
    {
        return $time ? self::local($time)->format('D, M j, Y') : '-';
    }

    /** UTC start of a local calendar day. */
    public static function dayStart(?CarbonInterface $time = null): Carbon
    {
        return ($time ? self::local($time) : self::now())->startOfDay()->setTimezone('UTC');
    }

    /** UTC end of a local calendar day. */
    public static function dayEnd(?CarbonInterface $time = null): Carbon
    {
        return ($time ? self::local($time) : self::now())->endOfDay()->setTimezone('UTC');
    }

    /** A local date and time typed by a person, read as UTC. */
    public static function parse(string $date, string $time = '00:00'): Carbon
    {
        return Carbon::parse("{$date} {$time}", self::TIMEZONE)->setTimezone('UTC');
    }

    /** "P70.00" with the peso sign. */
    public static function peso(float|int|string|null $amount): string
    {
        return "\u{20B1}".number_format((float) $amount, 2);
    }

    /** "2 h 14 min", "45 min", "24 h 30 min" */
    public static function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? "{$hours} h ".str_pad((string) $rest, 2, '0', STR_PAD_LEFT).' min' : "{$rest} min";
    }

    /** Whole minutes between two times. Seconds are dropped. */
    public static function minutesBetween(CarbonInterface $from, ?CarbonInterface $to = null): int
    {
        $to ??= now();

        return max(0, intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 60));
    }

    /** Format Philippine plates cleanly: "ABC1234" -> "ABC 1234", "AB12345" -> "AB 12345", "1" -> "1". */
    public static function plate(?string $plate): string
    {
        $plate = (string) $plate;
        if ($plate === '') {
            return '';
        }

        // 1. Current & Legacy Cars: LLL-DDDD or LLL-DDD (ABC1234 -> ABC 1234, ABC123 -> ABC 123)
        if (preg_match('/^[A-Z]{3}[0-9]{3,4}$/', $plate)) {
            return substr($plate, 0, 3).' '.substr($plate, 3);
        }

        // 2. Modern & Legacy Motorcycles: LL-DDDDD or LL-DDDD (AB12345 -> AB 12345, AB1234 -> AB 1234)
        if (preg_match('/^[A-Z]{2}[0-9]{4,5}$/', $plate)) {
            return substr($plate, 0, 2).' '.substr($plate, 2);
        }

        // 3. Regional Motorcycle: DDDD-LL (1234AB -> 1234 AB)
        if (preg_match('/^[0-9]{4}[A-Z]{2}$/', $plate)) {
            return substr($plate, 0, 4).' '.substr($plate, 4);
        }

        // 4. Vintage: L-DDD-LL (A123BC -> A-123-BC)
        if (preg_match('/^[A-Z][0-9]{3}[A-Z]{2}$/', $plate)) {
            return substr($plate, 0, 1).'-'.substr($plate, 1, 3).'-'.substr($plate, 4);
        }

        // 5. Vintage: DDD-LLL (123ABC -> 123 ABC)
        if (preg_match('/^[0-9]{3}[A-Z]{3}$/', $plate)) {
            return substr($plate, 0, 3).' '.substr($plate, 3);
        }

        // 6. Protocol (1 to 17) or other formats
        return strlen($plate) > 3 ? substr($plate, 0, 3).' '.substr($plate, 3) : $plate;
    }

    /** "0917 555 0142" */
    public static function phone(?string $phone): string
    {
        $phone = (string) $phone;

        return preg_match('/^09\d{9}$/', $phone)
            ? substr($phone, 0, 4).' '.substr($phone, 4, 3).' '.substr($phone, 7)
            : $phone;
    }

    /** 0917 123 4567, 0917-123-4567 and +63 917 123 4567 all become 09171234567. */
    public static function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/[\s()\-]+/', '', (string) $phone);

        if (preg_match('/^\+?63(9\d{9})$/', $phone, $matches)) {
            return '0'.$matches[1];
        }

        return $phone;
    }

    /** A setting from the manager's Settings screen. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        return SystemSetting::value($key, $default);
    }

    public static function longParkingHours(): int
    {
        return max(1, (int) self::setting('long_parking_threshold_hours', 12));
    }

    public static function graceMinutes(): int
    {
        return max(0, (int) self::setting('reservation_grace_minutes', 10));
    }

    public static function earlyMinutes(): int
    {
        return max(0, (int) self::setting('early_check_in_minutes', 30));
    }

    public static function advanceDays(): int
    {
        return max(1, (int) self::setting('maximum_advance_booking_days', 7));
    }

    public static function exitWindowMinutes(): int
    {
        return max(1, (int) self::setting('exit_window_minutes', 15));
    }

    public static function lotName(): string
    {
        return (string) self::setting('lot_name', 'Bayline');
    }

    public static function lotAddress(): string
    {
        return (string) self::setting('lot_address', '123 Commercial Ave, Metro Manila');
    }

    public static function receiptFooter(): string
    {
        return (string) self::setting('receipt_footer', "Exit gate approval is required before you leave.\nValid for 15 minutes.");
    }
}
