<?php

namespace App\Support;

use App\Models\ParkingSession;

/**
 * Ticket numbers, kept apart from plates. A ticket is PK- and four digits: PK-0001 to PK-9999.
 *
 * Input is forgiving ("pk3", "PK 3", "pk-03" all mean PK-0003) but a bare number is only a ticket
 * where the screen says it is a ticket field, because a bare number can also be a protocol plate.
 * Tickets that are already stored are never rewritten. Older tickets that do not fit the pattern
 * still open by their exact number (see lookupCandidates).
 */
class TicketNumber
{
    public const PREFIX = 'PK';

    public const MIN = 1;

    public const MAX = 9999;

    public const PATTERN = '/^PK-[0-9]{4}$/';

    public const MESSAGE = 'Enter the ticket number like PK-0003: PK and up to four digits.';

    /** True when the text starts like a ticket (PK, pk, "PK 3"). */
    public static function looksLikeTicket(?string $input): bool
    {
        return (bool) preg_match('/^\s*PK/i', (string) $input);
    }

    /**
     * PK-0003 for "pk3", "PK 3", "PK-03", "pk_0003". With $prefixOptional, "3" and "0003" work too.
     * Null when the text is not a ticket number or is outside PK-0001..PK-9999.
     */
    public static function normalize(?string $input, bool $prefixOptional = false): ?string
    {
        $text = strtoupper(preg_replace('/[\s_.]+/', '', (string) $input));

        if (preg_match('/^PK-?([0-9]{1,4})$/', $text, $m)) {
            $digits = $m[1];
        } elseif ($prefixOptional && preg_match('/^([0-9]{1,4})$/', $text, $m)) {
            $digits = $m[1];
        } else {
            return null;
        }

        $number = (int) $digits;

        return $number >= self::MIN && $number <= self::MAX ? self::PREFIX.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT) : null;
    }

    public static function isValid(?string $ticket): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $ticket) && self::normalize($ticket) === $ticket;
    }

    /** The values to try, in order, when looking a typed ticket up in the database. */
    public static function lookupCandidates(?string $input): array
    {
        $candidates = array_filter([self::normalize($input), strtoupper(trim((string) $input))]);

        return array_values(array_unique($candidates));
    }

    /**
     * The next free ticket number. Numbers go up from the highest one used. After PK-9999 the
     * lowest free number is reused, so a long-running lot never makes a number with five digits.
     *
     * @throws \RuntimeException when all 9999 numbers are in use
     */
    public static function next(): string
    {
        $used = ParkingSession::query()
            ->where('ticket_number', 'like', self::PREFIX.'-%')
            ->pluck('ticket_number')
            ->map(fn ($t) => preg_match('/^PK-([0-9]+)$/', $t, $m) ? (int) $m[1] : null)
            ->filter()
            ->flip();

        $highest = $used->keys()->max() ?? 0;

        for ($n = $highest + 1; $n <= self::MAX; $n++) {
            if (! $used->has($n)) {
                return self::PREFIX.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }
        }

        for ($n = self::MIN; $n <= self::MAX; $n++) {
            if (! $used->has($n)) {
                return self::PREFIX.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }
        }

        throw new \RuntimeException('All ticket numbers PK-0001 to PK-9999 are in use.');
    }
}
