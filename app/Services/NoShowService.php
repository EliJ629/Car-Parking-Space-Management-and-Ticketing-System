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
 * Three CONSECUTIVE no-shows block the driver from reserving a slot for one week. A reservation that was used (the vehicle
 * checked in) breaks the run, and after a block the count starts again from zero. The block only stops new reservations.
 * The driver can still walk in, park and see their active sessions, and keeps every account feature.
 *
 * The limit (default 3) and the length of the block in days (default 7) are system settings (no_show_limit, no_show_ban_days).
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

    /** The block lasts the same time every time. The tier is kept as 1 for old screens. */
    public function daysForTier(int $tier = 1): int
    {
        return $this->baseDays();
    }

    /**
     * Reservations that were either missed or used, oldest first. Cancelled, rejected and unanswered ones are left out,
     * so they neither add to a run of misses nor break it.
     *
     * @return \Illuminate\Support\Collection<int, ParkingReservation>
     */
    private function outcomes(User $driver)
    {
        return ParkingReservation::query()
            ->where('driver_id', $driver->id)
            ->where(fn ($q) => $q
                ->whereIn('status', [ParkingReservation::STATUS_CHECKED_IN, ParkingReservation::STATUS_COMPLETED])
                ->orWhere(fn ($q) => $q->where('status', ParkingReservation::STATUS_EXPIRED)->where('approval_status', ParkingReservation::APPROVAL_APPROVED)))
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get(['id', 'code', 'status', 'scheduled_at']);
    }

    /**
     * Walk the driver's history: misses add up, a used reservation clears them, and reaching the limit is a block that
     * clears them too.
     *
     * @return array{run:int,blocks:list<array{id:int,code:string,misses:int}>}
     */
    private function walk(User $driver): array
    {
        $limit = $this->limit();
        $run = 0;
        $blocks = [];

        foreach ($this->outcomes($driver) as $r) {
            if ($r->status !== ParkingReservation::STATUS_EXPIRED) {
                $run = 0;

                continue;
            }

            $run++;

            if ($run >= $limit) {
                $blocks[] = ['id' => $r->id, 'code' => $r->code, 'misses' => $run];
                $run = 0;
            }
        }

        return ['run' => $run, 'blocks' => $blocks];
    }

    /** Missed reservations in a row that have not yet led to a block. */
    public function noShows(User $driver): int
    {
        return $this->walk($driver)['run'];
    }

    /** The block that is in force now, or null. */
    public function activeSuspension(User $driver): ?ReservationSuspension
    {
        return ReservationSuspension::query()->active()->where('user_id', $driver->id)->orderByDesc('ends_at')->first();
    }

    public function message(ReservationSuspension $suspension): string
    {
        return "You missed {$suspension->no_show_count} reservations in a row, so you cannot reserve a slot until ".Lot::when($suspension->ends_at)
            .'. You can still park without a reservation and see your active sessions.';
    }

    /**
     * Called when a reservation has just expired. Tells the driver how many misses in a row are on record, and starts the
     * block when this miss is the one that reaches the limit. Returns the new block, or null.
     */
    public function recordNoShow(ParkingReservation $reservation): ?ReservationSuspension
    {
        $driver = $reservation->driver;

        if (! $driver || $reservation->status !== ParkingReservation::STATUS_EXPIRED || $reservation->approval_status !== ParkingReservation::APPROVAL_APPROVED) {
            return null;
        }

        $limit = $this->limit();
        $walk = $this->walk($driver);
        $trigger = collect($walk['blocks'])->firstWhere('id', $reservation->id);

        if (! $trigger) {
            $count = $walk['run'];

            if ($count < 1 || $count >= $limit) {
                return null;
            }

            $left = $limit - $count;
            $this->notices->toDriver($driver, 'N-19', 'no_show_warning', "Missed reservation {$count} of {$limit}",
                "You did not arrive for {$reservation->code}. That is missed reservation {$count} of {$limit} in a row. After {$left} more in a row you cannot reserve a slot for ".$this->daysLabel($this->baseDays()).'.'
                .' Missing three consecutive reservations will result in the loss of the reservation feature for one week.',
                $reservation, [], "no-show-{$reservation->id}");

            return null;
        }

        // Counted once: the same run of misses never starts two blocks.
        if (ReservationSuspension::query()->where('user_id', $driver->id)->where('reason', 'like', '%'.$trigger['code'].'%')->exists()) {
            return null;
        }

        $days = $this->baseDays();
        $suspension = ReservationSuspension::create([
            'user_id' => $driver->id,
            'no_show_count' => $trigger['misses'],
            'tier' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
            'reason' => "{$trigger['misses']} missed reservations in a row. Last missed: {$trigger['code']}. Reservations paused for ".$this->daysLabel($days).'.',
        ]);

        $this->notices->toDriver($driver, 'N-18', 'reservation_suspended', 'Reservations paused',
            $this->message($suspension), $reservation, [], "no-show-block-{$suspension->id}");

        ActivityLog::record('reservation_suspended', null, null, (string) $driver->id, 'system', [
            'no_shows' => $trigger['misses'], 'days' => $days, 'ends_at' => $suspension->ends_at->toIso8601String(),
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
