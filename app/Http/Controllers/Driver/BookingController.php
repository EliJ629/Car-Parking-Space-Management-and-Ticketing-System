<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ParkingReservation;
use App\Services\ReservationService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'past' ? 'past' : 'upcoming';
        $query = ParkingReservation::query()->where('driver_id', $request->user()->id)->with(['slot', 'vehicle']);

        $items = $tab === 'upcoming'
            ? $query->whereIn('status', [ParkingReservation::STATUS_BOOKED, ParkingReservation::STATUS_CHECKED_IN])->orderBy('scheduled_at')->get()
            : $query->whereNotIn('status', [ParkingReservation::STATUS_BOOKED, ParkingReservation::STATUS_CHECKED_IN])->orderByDesc('scheduled_at')->get();

        return view('driver.app.bookings.index', [
            'tab' => $tab,
            'items' => $items,
            'neverBooked' => ! ParkingReservation::query()->where('driver_id', $request->user()->id)->exists(),
        ]);
    }

    public function show(Request $request, ParkingReservation $reservation): View
    {
        $this->own($request, $reservation);
        $reservation->load(['slot', 'vehicle', 'session']);

        return view('driver.app.bookings.show', [
            'reservation' => $reservation,
            'fresh' => (bool) $request->query('new'),
            'opens' => $reservation->scheduled_at->copy()->subMinutes(Lot::earlyMinutes()),
            'holdEnds' => $reservation->scheduled_at->copy()->addMinutes(Lot::graceMinutes()),
        ]);
    }

    public function cancel(Request $request, ParkingReservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $this->own($request, $reservation);

        if (! $reservations->cancel($reservation, $request->user(), $request)) {
            return redirect()->route('driver.bookings.show', $reservation)
                ->with('toast', ['Nothing changed', 'This reservation is '.strtolower($reservation->fresh()->statusLabel()).' and can no longer be canceled.']);
        }

        return redirect()->route('driver.bookings.show', $reservation)->with('canceled', true);
    }

    private function own(Request $request, ParkingReservation $reservation): void
    {
        abort_unless($reservation->driver_id === $request->user()->id, 404);
    }
}
