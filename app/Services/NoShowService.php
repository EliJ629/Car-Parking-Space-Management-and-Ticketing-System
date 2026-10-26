<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ParkingReservation;
use App\Models\ReservationSuspension;
use App\Models\User;
use App\Support\Lot;

/**
 * Missed reservations (no-shows) and the block on reserving that follows them.
 *
 * A no-show is a reservation the booth attendant had approved (or that needed no approval) and whose vehicle did not
 * check in within the grace time, so the system expired it. A reservation the driver cancelled, or a PWD request the
 * attendant never answered, is not a no-show.
 *
 * Every third no-show (3, 6, 9 ...) blocks the driver from reserving a slot. The first block lasts 1 week, the second 2 weeks,
 * the third 3 weeks, and so on, so the penalty keeps growing with every further set of three. The block only stops new
 * reservations. The driver can still walk in and park, and keeps every account feature.
 *
 * The limit (default 3) and the length of the first block in days (default 7) are system settings
 * (no_show_limit, no_show_ban_days).
 */
class NoShowService
{
    public function __construct(private NoticeService $notices) {}

    public function limit(): int
    {
        return min(10, max(1, (int) Lot::setting('no_show_limit', 3)));
    }

    public function baseDays(): int
    {
        return min(60, max(1, (int) Lot::setting('no_show_ban_days', 7)));
    }

    /** Days of the block for the nth block: 7, 14, 21 ... with the default of 7. */
    public function daysForTier(int $tier): int
    {
        return $this->baseDays() * max(1, $tier);
    }

    public function noShows(User $driver): int
    {
        return ParkingReservation::query()
            ->where('driver_id', $driver->id)
            ->where('status', ParkingReservation::STATUS_EXPIRED)
            ->where('approval_status', ParkingReservation::APPROVAL_APPROVED)
            ->count();
    }

    /** The block that is in force now, or null. */
    public function activeSuspension(User $driver): ?ReservationSuspension
    {
        return ReservationSuspension::query()->active()->where('user_id', $driver->id)->orderByDesc('ends_at')->first();
    }

    public function message(ReservationSuspension $suspension): string
    {
        return "You missed {$suspension->no_show_count} reservations, so you cannot reserve a slot until ".Lot::when($suspension->ends_at)
            .'. You can still park without a reservation.';
    }

    /**
     * Called when a reservation has just expired. Tells the driver how many misses are on record, and starts the block when
     * the count reaches a multiple of the limit. Returns the new block, or null.
     */
    public function recordNoShow(ParkingReservation $reservation): ?ReservationSuspension
    {
        $driver = $reservation->driver;

        if (! $driver || $reservation->status !== ParkingReservation::STATUS_EXPIRED || $reservation->approval_status !== ParkingReservation::APPROVAL_APPROVED) {
            return null;
        }

        $count = $this->noShows($driver);
        $limit = $this->limit();

        if ($count < 1) {
            return null;
        }

        if ($count % $limit !== 0) {
            $left = $limit - ($count % $limit);
            $this->notices->toDriver($driver, 'N-19', 'no_show_warning', "Missed reservation {$count}",
                "You did not arrive for {$reservation->code}. That is a missed reservation. After {$left} more you cannot reserve a slot for ".$this->daysLabel($this->daysForTier(intdiv($count, $limit) + 1)).'.',
                $reservation, [], "no-show-{$reservation->id}");

            return null;
        }

        $tier = intdiv($count, $limit);

        // Counted once: the same set of no-shows never starts two blocks.
        if (ReservationSuspension::query()->where('user_id', $driver->id)->where('no_show_count', $count)->exists()) {
            return null;
        }

        $days = $this->daysForTier($tier);
        $suspension = ReservationSuspension::create([
            'user_id' => $driver->id,
            'no_show_count' => $count,
            'tier' => $tier,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
            'reason' => "{$count} missed reservations. Block {$tier}: ".$this->daysLabel($days).'.',
        ]);

        $this->notices->toDriver($driver, 'N-18', 'reservation_suspended', 'Reservations paused',
            $this->message($suspension).' Each further '.$limit.' missed reservations make the pause longer.',
            $reservation, [], "no-show-block-{$suspension->id}");

        ActivityLog::record('reservation_suspended', null, null, (string) $driver->id, 'system', [
            'no_shows' => $count, 'tier' => $tier, 'days' => $days, 'ends_at' => $suspension->ends_at->toIso8601String(),
        ]);

        return $suspension;
    }

    private function daysLabel(int $days): string
    {
        if ($days % 7 === 0) {
            $weeks = intdiv($days, 7);

            return $weeks === 1 ? '1 week' : "{$weeks} weeks";
        }

        return $days === 1 ? '1 day' : "{$days} days";
    }
}
