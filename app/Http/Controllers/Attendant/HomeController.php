<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Services\AttendantAlerts;
use App\Support\Lot;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Screen BA-01: First screen after sign in. Shows the state of the lot,
 * the next reserved arrivals and anything that needs attention.
 */
class HomeController extends Controller
{
    public function index(Request $request, AttendantAlerts $alerts): View
    {
        $attendant = $request->user();
        $now = Lot::now();

        // Count lot stats
        $vehiclesInLot = ParkingSession::query()->whereIn('status', ParkingSession::OPEN_STATUSES)->count();
        $pendingExits = ParkingSession::query()->where('status', ParkingSession::STATUS_PAID)->count();
        $availableSlots = ParkingSlot::query()->where('status', ParkingSlot::STATUS_AVAILABLE)->count();

        // Shift cash: payments received by this attendant during current shift
        $shiftStart = ShiftController::determineShiftStart($attendant);
        $shiftStartUtc = $shiftStart->copy()->setTimezone('UTC');

        $shiftCash = (float) ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', $shiftStartUtc)
            ->sum('total');

        // Reserved arrivals:
        // Next 30 minutes (check-in is open now)
        $in30Mins = $now->copy()->addMinutes(Lot::earlyMinutes());
        $next30MinReservations = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [$now->copy()->subMinutes(Lot::graceMinutes()), $in30Mins])
            ->with(['driver', 'slot'])
            ->orderBy('scheduled_at')
            ->get();

        // Later today
        $laterTodayReservations = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('scheduled_at', '>', $in30Mins)
            ->where('scheduled_at', '<=', Lot::dayEnd())
            ->with(['driver', 'slot'])
            ->orderBy('scheduled_at')
            ->get();

        // Alerts widget
        $alertItems = $alerts->items()->take(4);

        return view('staff.attendant.home', [
            'attendant' => $attendant,
            'shiftStartTime' => $shiftStart->format('g:i A'),
            'vehiclesInLot' => $vehiclesInLot,
            'pendingExits' => $pendingExits,
            'availableSlots' => $availableSlots,
            'shiftCash' => $shiftCash,
            'next30MinReservations' => $next30MinReservations,
            'laterTodayReservations' => $laterTodayReservations,
            'alertItems' => $alertItems,
        ]);
    }
}
