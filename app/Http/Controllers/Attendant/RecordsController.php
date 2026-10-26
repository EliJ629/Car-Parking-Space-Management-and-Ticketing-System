<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ParkingSession;
use App\Models\Vehicle;
use App\Support\Lot;
use App\Support\Rules;
use App\Support\TicketNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordsController extends Controller
{
    /** BA-57 to BA-60: Parking records list, search, and date filter */
    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'today');
        $dateParam = $request->query('date');
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 20);

        // If a date is explicitly provided without an explicit filter, default to custom
        if ($request->filled('date') && ! $request->has('filter')) {
            $filter = 'custom';
        }

        $now = Lot::now();
        $customDate = $dateParam ?: $now->format('Y-m-d');

        switch ($filter) {
            case 'this_week':
                $start = $now->copy()->startOfWeek()->setTimezone('UTC');
                $end = $now->copy()->endOfWeek()->setTimezone('UTC');
                $filterLabel = 'This week';
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth()->setTimezone('UTC');
                $end = $now->copy()->endOfMonth()->setTimezone('UTC');
                $filterLabel = 'This month';
                break;
            case 'custom':
                try {
                    $parsed = Carbon::parse($customDate, Lot::TIMEZONE);
                } catch (\Throwable) {
                    $parsed = $now;
                    $customDate = $now->format('Y-m-d');
                }
                $start = $parsed->copy()->startOfDay()->setTimezone('UTC');
                $end = $parsed->copy()->endOfDay()->setTimezone('UTC');
                $filterLabel = $parsed->format('M j, Y');
                break;
            case 'today':
            default:
                $filter = 'today';
                $start = $now->copy()->startOfDay()->setTimezone('UTC');
                $end = $now->copy()->endOfDay()->setTimezone('UTC');
                $filterLabel = 'Today';
                break;
        }

        $query = ParkingSession::query()
            ->with(['slot', 'payment', 'attendant', 'exitApprover'])
            ->where(function ($w) use ($start, $end) {
                $w->whereBetween('entered_at', [$start, $end])
                    ->orWhereBetween('exited_at', [$start, $end]);
            });

        if ($q !== '') {
            $plateQuery = Vehicle::normalizePlate($q);
            $ticket = TicketNumber::looksLikeTicket($q) ? TicketNumber::normalize($q) : null;
            $query->where(function ($w) use ($q, $plateQuery, $ticket) {
                $w->where('ticket_number', 'like', '%'.strtoupper($q).'%')
                    ->orWhere('plate_number', 'like', "%{$plateQuery}%");

                if ($ticket) {
                    $w->orWhere('ticket_number', $ticket);
                }
            });
        }

        $sessions = $query->latest('entered_at')->paginate(15)->withQueryString();

        return view('staff.attendant.records.index', [
            'sessions' => $sessions,
            'filter' => $filter,
            'filterLabel' => $filterLabel,
            'customDate' => $customDate,
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
