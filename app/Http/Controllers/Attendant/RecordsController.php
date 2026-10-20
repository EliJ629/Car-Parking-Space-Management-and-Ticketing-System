<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ParkingSession;
use App\Support\Lot;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordsController extends Controller
{
    /** BA-57 to BA-60: Parking records list, search, and date filter */
    public function index(Request $request): View
    {
        $dateParam = $request->query('date');
        $q = trim((string) $request->query('q', ''));

        $selectedDate = $dateParam ? Carbon::parse($dateParam, Lot::TIMEZONE) : Lot::now();

        $dayStart = $selectedDate->copy()->startOfDay()->setTimezone('UTC');
        $dayEnd = $selectedDate->copy()->endOfDay()->setTimezone('UTC');

        $query = ParkingSession::query()
            ->with(['slot', 'payment', 'attendant', 'exitApprover'])
            ->where(function ($w) use ($dayStart, $dayEnd) {
                $w->whereBetween('entered_at', [$dayStart, $dayEnd])
                    ->orWhereBetween('exited_at', [$dayStart, $dayEnd]);
            });

        if ($q !== '') {
            $plateQuery = Rules::squish($q);
            $query->where(function ($w) use ($q, $plateQuery) {
                $w->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('plate_number', 'like', "%{$plateQuery}%");
            });
        }

        $sessions = $query->latest('entered_at')->paginate(15)->withQueryString();

        return view('staff.attendant.records.index', [
            'sessions' => $sessions,
            'selectedDate' => $selectedDate,
            'q' => $q,
            'searched' => $q !== '',
        ]);
    }

    /** BA-61: Record detail popup */
    public function show(ParkingSession $session): View
    {
        $session->load(['slot', 'payment', 'attendant', 'exitApprover', 'driver']);

        return view('staff.attendant.records.show', [
            'session' => $session,
        ]);
    }
}
