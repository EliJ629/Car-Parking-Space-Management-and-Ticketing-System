<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Support\Lot;
use Illuminate\View\View;

/** Read only slot board. The Guard never edits slots. */
class SlotsController extends Controller
{
    public function index(): View
    {
        $slots = ParkingSlot::query()->orderBy('zone')->orderBy('code')->get();
        $sessions = ParkingSession::query()->whereIn('status', ParkingSession::OPEN_STATUSES)->get()->keyBy('slot_id');
        $reservations = ParkingReservation::query()->where('status', ParkingReservation::STATUS_BOOKED)->orderBy('scheduled_at')->get()->groupBy('slot_id');

        $info = [];
        $hours = Lot::longParkingHours();
        $longCount = 0;

        foreach ($slots as $slot) {
            $session = $sessions->get($slot->id);
            $reservation = $reservations->get($slot->id)?->first();

            $long = $session?->isLongParked($hours) ?? false;
            $longCount += $long ? 1 : 0;

            $info[$slot->id] = [
                'long' => $long,
                'plate' => $session ? Lot::plate($session->plate_number) : null,
                'duration' => $session ? Lot::duration($session->parkedMinutes()) : null,
                'vehicle' => $session ? Lot::plate($session->plate_number).', ticket '.$session->ticket_number : null,
                'since' => $session ? 'In since '.Lot::stamp($session->entered_at).', parked '.Lot::duration($session->parkedMinutes()) : null,
                'booked' => $reservation ? \App\Support\SlotLayout::tileTime($reservation->scheduled_at) : null,
                'booked_plate' => $reservation ? Lot::plate($reservation->plate_number) : null,
                'report' => $session ? route('guard.incidents.create', ['category' => 'Wrong slot', 'plate' => $session->plate_number, 'location' => $slot->code, 'session' => $session->id]) : null,
                'booking' => $reservation ? $reservation->code.' for '.Lot::plate($reservation->plate_number).', '.Lot::when($reservation->scheduled_at) : null,
                'reason' => $slot->status === ParkingSlot::STATUS_OUT_OF_SERVICE ? ($slot->out_of_service_reason ?: 'No reason recorded') : null,
            ];
        }

        return view('guard.slots', [
            'zones' => $slots->groupBy('zone'),
            'zonePositions' => \App\Support\SlotLayout::zonePositions($slots),
            'counts' => $slots->countBy('status'),
            'total' => $slots->count(),
            'info' => $info,
            'longCount' => $longCount,
            'longHours' => $hours,
        ]);
    }
}
