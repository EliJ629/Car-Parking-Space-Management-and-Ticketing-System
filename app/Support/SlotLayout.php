<?php

namespace App\Support;

use App\Models\ParkingLayoutZone;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** The lot map the Parking Manager arranges. Every slot board (driver, guard) draws it from the same data. */
class SlotLayout
{
    /** Where each zone label sits, keyed by zone name. */
    public static function zonePositions(Collection $slots): Collection
    {
        return ParkingLayoutZone::query()
            ->whereIn('zone', $slots->pluck('zone')->unique()->values())
            ->get()
            ->keyBy('zone');
    }

    /**
     * What a slot square shows besides its code: how long a parked vehicle has been in (parked_label), and for a
     * reserved slot the time of the booking (booked_label). Plates are not set here; the Guard board adds those itself.
     *
     * @param  Collection<int, \App\Models\ParkingSlot>  $slots
     * @return Collection<int, \App\Models\ParkingSlot>
     */
    public static function decorate(Collection $slots, bool $withBookings = true): Collection
    {
        $sessions = ParkingSession::query()->whereIn('status', ParkingSession::OPEN_STATUSES)->get()->keyBy('slot_id');
        $bookings = ParkingReservation::query()->where('status', ParkingReservation::STATUS_BOOKED)->orderBy('scheduled_at')->get()->groupBy('slot_id');

        return $slots->each(function ($slot) use ($sessions, $bookings, $withBookings): void {
            $session = $sessions->get($slot->id);
            $booking = $bookings->get($slot->id)?->first();

            $slot->setAttribute('parked_label', $session ? Lot::duration($session->parkedMinutes()) : null);
            if ($withBookings) {
                $slot->setAttribute('booked_label', $booking ? self::tileTime($booking->scheduled_at) : null);
            }
        });
    }

    /** A time short enough for a slot square: "3:22 AM", "Tmrw 3:22 AM", or "Oct 29 3:22 AM". */
    public static function tileTime(?CarbonInterface $time): string
    {
        if (! $time) {
            return '';
        }

        $local = Lot::local($time);
        $clock = $local->format('g:i A');

        if ($local->isSameDay(Lot::now())) {
            return $clock;
        }

        return $local->isSameDay(Lot::now()->addDay()) ? "Tmrw {$clock}" : $local->format('M j').' '.$clock;
    }
}
