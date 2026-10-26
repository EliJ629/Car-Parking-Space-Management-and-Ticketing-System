<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\User;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public const OPENING_FLOAT = 500.00;

    /**
     * Determine when the attendant's active shift started.
     * Prevents re-logins from zeroing out prior transactions from the shift.
     */
    public static function determineShiftStart(User $attendant): Carbon
    {
        // 1. If attendant closed a shift earlier today, the active shift started right after that
        $lastShiftClosed = ActivityLog::query()
            ->where('user_id', $attendant->id)
            ->where('action', 'shift_closed')
            ->latest('created_at')
            ->first();

        if ($lastShiftClosed && $lastShiftClosed->created_at->gt(Lot::dayStart())) {
            return Lot::local($lastShiftClosed->created_at);
        }

        // 2. Earliest transaction or activity today by this attendant
        $earliestPayment = ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', Lot::dayStart())
            ->oldest('paid_at')
            ->value('paid_at');

        $earliestSession = ParkingSession::query()
            ->where('attendant_id', $attendant->id)
            ->where('entered_at', '>=', Lot::dayStart())
            ->oldest('entered_at')
            ->value('entered_at');

        $candidates = array_filter([
            $earliestPayment ? Lot::local($earliestPayment) : null,
            $earliestSession ? Lot::local($earliestSession) : null,
            $attendant->last_login_at && Lot::local($attendant->last_login_at)->isSameDay(Lot::now())
                ? Lot::local($attendant->last_login_at)
                : null,
        ]);

        if (! empty($candidates)) {
            usort($candidates, fn ($a, $b) => $a->timestamp <=> $b->timestamp);

            return $candidates[0];
        }

        return $attendant->last_login_at ? Lot::local($attendant->last_login_at) : Lot::local(Lot::dayStart());
    }

    /** BA-62: Shift summary and cash reconciliation */
    public function index(Request $request): View
    {
        $attendant = $request->user();

        $shiftStart = self::determineShiftStart($attendant);
        $shiftStartUtc = $shiftStart->copy()->setTimezone('UTC');

        $paymentsQuery = ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', $shiftStartUtc);

        $payments = (clone $paymentsQuery)->with('session')->latest('paid_at')->get();

        $transactionsCount = $payments->count();
        $cashCollected = (float) $payments->sum('total');
        $discountsGiven = (float) $payments->sum('discount');
        $penaltiesCharged = (float) $payments->sum('penalty');

        $expectedInDrawer = self::OPENING_FLOAT + $cashCollected;

        // Discount and penalty breakdowns
        $seniorDiscounts = $payments->filter(fn ($p) => stripos($p->receipt_snapshot['discount_name'] ?? '', 'senior') !== false);
        $pwdDiscounts = $payments->filter(fn ($p) => stripos($p->receipt_snapshot['discount_name'] ?? '', 'pwd') !== false);
        $lostTicketPenalties = $payments->filter(fn ($p) => ($p->session?->lost_ticket ?? false) || stripos(json_encode($p->receipt_snapshot), 'lost') !== false);
        $overstayPenalties = $payments->filter(fn ($p) => $p->penalty > 0 && ! (($p->session?->lost_ticket ?? false) || stripos(json_encode($p->receipt_snapshot), 'lost') !== false));

        // Entries handled during this shift
        $walkInsCount = ParkingSession::query()
            ->where('attendant_id', $attendant->id)
            ->whereNull('driver_id')
            ->where('entered_at', '>=', $shiftStartUtc)
            ->count();

        $reservedCheckInsCount = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_CHECKED_IN)
            ->where('checked_in_at', '>=', $shiftStartUtc)
            ->count();

        $closedSummary = session('closed_shift');

        return view('staff.attendant.shift.index', [
            'shiftStart' => $shiftStart->format('g:i A'),
            'transactionsCount' => $transactionsCount,
            'cashCollected' => $cashCollected,
            'discountsGiven' => $discountsGiven,
            'penaltiesCharged' => $penaltiesCharged,
            'openingFloat' => self::OPENING_FLOAT,
            'expectedInDrawer' => $expectedInDrawer,
            'latestPayments' => $payments->take(7),
            'seniorCount' => $seniorDiscounts->count(),
            'seniorTotal' => (float) $seniorDiscounts->sum('discount'),
            'pwdCount' => $pwdDiscounts->count(),
            'pwdTotal' => (float) $pwdDiscounts->sum('discount'),
            'lostTicketCount' => $lostTicketPenalties->count(),
            'lostTicketTotal' => (float) $lostTicketPenalties->sum('penalty'),
            'overstayCount' => $overstayPenalties->count(),
            'overstayTotal' => (float) $overstayPenalties->sum('penalty'),
            'walkInsCount' => $walkInsCount,
            'reservedCheckInsCount' => $reservedCheckInsCount,
            'exitsPaidCount' => $transactionsCount,
            'closedSummary' => $closedSummary,
        ]);
    }

    /** BA-63 & BA-64: Close shift */
    public function closeShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_counted' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $attendant = $request->user();
        $counted = (float) $validated['cash_counted'];

        $shiftStart = self::determineShiftStart($attendant);
        $shiftStartUtc = $shiftStart->copy()->setTimezone('UTC');

        $cashCollected = (float) ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', $shiftStartUtc)
            ->sum('total');

        $expected = self::OPENING_FLOAT + $cashCollected;
        $difference = round($counted - $expected, 2);

        // A note is required when the difference is not zero
        if ($difference !== 0.0 && empty($validated['note'])) {
            return back()->with('error_modal', [
                'type' => 'close_shift_note_required',
                'difference' => $difference,
                'expected' => $expected,
                'counted' => $counted,
            ]);
        }

        $closeTime = Lot::now();

        ActivityLog::record('shift_closed', $attendant, $request, 'Shift report', 'attendant', [
            'shift_start' => $shiftStart->format('g:i A'),
            'shift_end' => $closeTime->format('g:i A'),
            'opening_float' => self::OPENING_FLOAT,
            'cash_collected' => $cashCollected,
            'expected' => $expected,
            'counted' => $counted,
            'difference' => $difference,
            'note' => $validated['note'] ?? null,
        ]);

        $closedSummary = [
            'closed_at' => $closeTime->format('g:i A'),
            'opening_float' => self::OPENING_FLOAT,
            'cash_collected' => $cashCollected,
            'expected' => $expected,
            'counted' => $counted,
            'difference' => $difference,
            'note' => $validated['note'] ?? null,
            'attendant_name' => $attendant->name,
        ];

        return redirect()->route('attendant.shift.index')->with('closed_shift', $closedSummary);
    }
}
