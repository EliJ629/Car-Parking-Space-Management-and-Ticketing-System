<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public const OPENING_FLOAT = 500.00;

    /** BA-62: Shift summary and cash reconciliation */
    public function index(Request $request): View
    {
        $attendant = $request->user();

        $shiftStart = $attendant->last_login_at
            ? Lot::local($attendant->last_login_at)
            : Lot::dayStart();

        $paymentsQuery = ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', $shiftStart->setTimezone('UTC'));

        $payments = (clone $paymentsQuery)->with('session')->latest('paid_at')->get();

        $transactionsCount = $payments->count();
        $cashCollected = (float) $payments->sum('total');
        $discountsGiven = (float) $payments->sum('discount');
        $penaltiesCharged = (float) $payments->sum('penalty');

        $expectedInDrawer = self::OPENING_FLOAT + $cashCollected;

        // Discount and penalty breakdowns
        $seniorDiscounts = $payments->filter(fn ($p) => ($p->receipt_snapshot['discount_name'] ?? '') === 'Senior citizen');
        $pwdDiscounts = $payments->filter(fn ($p) => ($p->receipt_snapshot['discount_name'] ?? '') === 'PWD');
        $lostTicketPenalties = $payments->filter(fn ($p) => ($p->session?->lost_ticket ?? false));
        $overstayPenalties = $payments->filter(fn ($p) => ($p->penalty > 0 && ! ($p->session?->lost_ticket ?? false)));

        // Entries handled during this shift
        $walkInsCount = ParkingSession::query()
            ->where('attendant_id', $attendant->id)
            ->whereNull('driver_id')
            ->where('entered_at', '>=', $shiftStart->setTimezone('UTC'))
            ->count();

        $reservedCheckInsCount = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_CHECKED_IN)
            ->where('checked_in_at', '>=', $shiftStart->setTimezone('UTC'))
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

        $shiftStart = $attendant->last_login_at
            ? Lot::local($attendant->last_login_at)
            : Lot::dayStart();

        $cashCollected = (float) ParkingPayment::query()
            ->where('received_by', $attendant->id)
            ->where('paid_at', '>=', $shiftStart->setTimezone('UTC'))
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
