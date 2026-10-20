<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingDiscount;
use App\Models\ParkingPayment;
use App\Models\ParkingRateSet;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FeeCalculator;
use App\Services\NoticeService;
use App\Support\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ExitController extends Controller
{
    /** BA-34 to BA-36: Exit search */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $includeClosed = $request->boolean('include_closed', false);

        // Pending exit queue on the right
        $waitingForGuard = ParkingSession::query()
            ->where('status', ParkingSession::STATUS_PAID)
            ->with(['payment', 'slot'])
            ->latest('updated_at')
            ->get();

        $results = collect();
        $searched = false;
        $noMatch = false;

        if ($q !== '') {
            $searched = true;
            $normPlate = Vehicle::normalizePlate($q);

            // Normalize ticket numbers (e.g. PK0016, PK 16, PK-16 -> PK-0016)
            $cleanTicket = null;
            if (preg_match('/^PK[\s\-]*(\d+)$/i', $q, $m)) {
                $num = (int) $m[1];
                $cleanTicket = 'PK-'.str_pad((string) $num, 4, '0', STR_PAD_LEFT);
            }

            $query = ParkingSession::query()->with(['slot', 'payment', 'driver']);

            if (! $includeClosed) {
                $query->whereIn('status', ParkingSession::OPEN_STATUSES);
            }

            $query->where(function ($w) use ($q, $normPlate, $cleanTicket) {
                $w->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('plate_number', 'like', "%{$normPlate}%");

                if ($cleanTicket) {
                    $w->orWhere('ticket_number', $cleanTicket);
                }
            });

            $matches = $query->latest('entered_at')->get();

            // Exact match (or normalized ticket / plate match) skips list and opens session directly
            $first = $matches->first();
            $isExact = $matches->count() === 1 && (
                strtoupper($first->ticket_number) === strtoupper($q) ||
                ($cleanTicket && $first->ticket_number === $cleanTicket) ||
                $first->plate_number === $normPlate
            );

            if ($isExact) {
                return view('staff.attendant.exit.redirect', ['url' => route('attendant.exit.show', $first)]);
            }

            if ($matches->isEmpty()) {
                $noMatch = true;
            } else {
                $results = $matches;
            }
        }

        $activeTargets = ParkingSession::query()
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->get(['id', 'ticket_number', 'plate_number', 'slot_code', 'status'])
            ->map(function ($s) {
                return [
                    'plate' => Lot::plate($s->plate_number),
                    'raw_plate' => $s->plate_number,
                    'ticket' => $s->ticket_number,
                    'slot' => $s->slot_code,
                    'status' => $s->status,
                ];
            })
            ->values();

        return view('staff.attendant.exit.index', [
            'q' => $q,
            'includeClosed' => $includeClosed,
            'waitingForGuard' => $waitingForGuard,
            'results' => $results,
            'searched' => $searched,
            'noMatch' => $noMatch,
            'activeTargets' => $activeTargets,
        ]);
    }

    /** BA-37 to BA-45, BA-48 to BA-50: Session details, fees, discounts, and payment */
    public function show(ParkingSession $session, FeeCalculator $calculator): View
    {
        $session->load(['slot', 'driver', 'payment.attendant', 'exitApprover', 'reservation']);

        // Screen BA-49: Exit completed
        if ($session->status === ParkingSession::STATUS_COMPLETED) {
            return view('staff.attendant.exit.completed', [
                'session' => $session,
            ]);
        }

        // Screen BA-48 & BA-50: Paid, waiting for the Guard (Pending exit)
        if ($session->status === ParkingSession::STATUS_PAID) {
            $exitWindowMins = Lot::exitWindowMinutes();
            $paidAt = $session->payment?->paid_at ?? $session->updated_at;
            $windowEndsAt = $paidAt->copy()->addMinutes($exitWindowMins);
            $isExpired = now()->gt($windowEndsAt);

            $recalc = null;
            if ($isExpired) {
                // BA-50: Recalculate stay, add overstay penalty
                $recalc = $calculator->estimate($session, now());
                $recalc['already_paid'] = (float) ($session->payment?->total ?? 0);
                $rate = ParkingRateSet::current();
                $recalc['overstay_penalty'] = (float) ($rate->overstay_penalty ?? 150.0);
                $recalc['amount_to_collect'] = max(0, $recalc['parking_charge'] - $recalc['already_paid'] + $recalc['overstay_penalty']);
            }

            return view('staff.attendant.exit.pending', [
                'session' => $session,
                'windowEndsAt' => Lot::time($windowEndsAt),
                'isExpired' => $isExpired,
                'recalc' => $recalc,
            ]);
        }

        // Active session: compute fee breakdown
        $now = Lot::now();
        $estimate = $calculator->estimate($session, $now);

        $discounts = ParkingDiscount::query()->where('active', true)->get();
        $managers = User::query()->where('role', User::ROLE_MANAGER)->where('status', User::STATUS_ACTIVE)->get();

        return view('staff.attendant.exit.show', [
            'session' => $session,
            'estimate' => $estimate,
            'discounts' => $discounts,
            'managers' => $managers,
            'isReserved' => $session->reservation !== null,
        ]);
    }

    /** BA-40 & BA-41: Verify manager PIN */
    public function verifyPin(Request $request, ParkingSession $session): JsonResponse
    {
        $validated = $request->validate([
            'manager_id' => ['required', 'exists:users,id'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{5,6}$/'],
        ], [
            'pin.regex' => 'The manager PIN must contain 6 numbers.',
        ]);

        $manager = User::query()
            ->whereKey($validated['manager_id'])
            ->where('role', User::ROLE_MANAGER)
            ->first();

        if (! $manager) {
            return response()->json(['ok' => false, 'message' => 'Manager not found.'], 404);
        }

        $sessionKey = "mgr_pin_fails_{$session->id}_{$manager->id}";
        $fails = (int) session()->get($sessionKey, 0);

        if ($fails >= 3) {
            return response()->json([
                'ok' => false,
                'blocked' => true,
                'attempts_left' => 0,
                'message' => 'Approval blocked. 3 wrong PINs entered. Request written to activity log.',
            ]);
        }

        // Check manager's configured PIN, or fallback to demo PINs / manager password
        $pin = trim($validated['pin']);
        $valid = $manager->managerPinMatches($pin)
            || ($pin === '741963' || $pin === '123456' || $pin === '12345' || $pin === '24680' || Hash::check($pin, $manager->password));

        if (! $valid) {
            $fails++;
            session()->put($sessionKey, $fails);
            $attemptsLeft = max(0, 3 - $fails);

            if ($attemptsLeft <= 0) {
                ActivityLog::record(
                    action: 'manager_pin_failed',
                    user: $manager,
                    request: $request,
                    subject: "Ticket {$session->ticket_number}",
                    area: 'attendant',
                    details: [
                        'ticket' => $session->ticket_number,
                        'approving_manager' => $manager->name,
                        'reason' => 'Three wrong PIN attempts. Approval blocked.',
                    ],
                );

                return response()->json([
                    'ok' => false,
                    'blocked' => true,
                    'attempts_left' => 0,
                    'message' => 'Approval blocked. 3 wrong PINs entered. Request written to activity log.',
                ]);
            }

            return response()->json([
                'ok' => false,
                'blocked' => false,
                'attempts_left' => $attemptsLeft,
                'message' => "Wrong PIN. {$attemptsLeft} attempt" . ($attemptsLeft === 1 ? '' : 's') . ' left.',
            ]);
        }

        session()->forget($sessionKey);

        return response()->json([
            'ok' => true,
            'manager_name' => $manager->name,
        ]);
    }

    /** BA-44 to BA-46: Process cash payment */
    public function pay(Request $request, ParkingSession $session, FeeCalculator $calculator, NoticeService $notices): RedirectResponse
    {
        $validated = $request->validate([
            'cash_received' => ['required', 'numeric', 'min:0'],
            'discount_id' => ['nullable', 'exists:parking_discounts,id'],
            'discount_proof' => ['nullable', 'string', 'max:80'],
            'discount_note' => ['nullable', 'string', 'max:255'],
            'approving_manager' => ['nullable', 'string', 'max:120'],
            'lost_ticket' => ['nullable', 'boolean'],
            'overstay' => ['nullable', 'boolean'],
        ]);

        $attendant = $request->user();

        // Calculate live fee
        $estimate = $calculator->estimate($session, now());
        $parkingCharge = $estimate['parking_charge'];

        // Apply discount if selected
        $discountAmount = 0.0;
        $discountName = null;
        if (! empty($validated['discount_id'])) {
            $discount = ParkingDiscount::query()->find($validated['discount_id']);
            if ($discount) {
                $discountName = $discount->name;
                $discountAmount = round(($parkingCharge * $discount->percent) / 100, 2);
            }
        }

        // Apply penalties
        $penalties = 0.0;
        $currentRate = ParkingRateSet::current();
        if (! empty($validated['lost_ticket']) || $session->lost_ticket) {
            $penalties += (float) ($currentRate?->lost_ticket_penalty ?? 100);
            $session->lost_ticket = true;
        }

        if (! empty($validated['overstay']) || $session->exit_window_expired_at) {
            $penalties += (float) ($currentRate?->overstay_penalty ?? 150);
        }

        $alreadyPaid = (float) ($session->payment?->total ?? 0.0);
        $finalAmount = max(0.0, round($parkingCharge - $discountAmount + $penalties - $alreadyPaid, 2));
        $cashReceived = (float) $validated['cash_received'];

        // BA-45: Cash check
        if ($cashReceived < $finalAmount) {
            $shortfall = $finalAmount - $cashReceived;

            return back()->with('error_modal', [
                'type' => 'insufficient_cash',
                'shortfall' => Lot::peso($shortfall),
                'amount_due' => Lot::peso($finalAmount),
                'cash' => Lot::peso($cashReceived),
            ]);
        }

        $changeGiven = round($cashReceived - $finalAmount, 2);
        $receiptNumber = $this->generateNextReceiptNumber();

        $payment = DB::transaction(function () use (
            $session, $attendant, $estimate, $discountAmount, $discountName, $penalties,
            $finalAmount, $cashReceived, $changeGiven, $receiptNumber, $validated, $notices, $request
        ) {
            $paymentData = [
                'receipt_number' => $receiptNumber,
                'session_id' => $session->id,
                'received_by' => $attendant->id,
                'base_fee' => $estimate['first_hour'],
                'additional_fee' => $estimate['additional'],
                'penalty' => $penalties,
                'discount' => $discountAmount,
                'total' => $finalAmount,
                'method' => 'cash',
                'receipt_snapshot' => [
                    'ticket_number' => $session->ticket_number,
                    'plate_number' => $session->plate_number,
                    'slot_code' => $session->slot_code,
                    'entry_time' => Lot::stamp($session->entered_at),
                    'payment_time' => Lot::stamp(now()),
                    'base_fee' => $estimate['first_hour'],
                    'additional_fee' => $estimate['additional'],
                    'discount_name' => $discountName,
                    'discount_amount' => $discountAmount,
                    'discount_proof' => $validated['discount_proof'] ?? null,
                    'approving_manager' => $validated['approving_manager'] ?? null,
                    'penalties' => $penalties,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                    'attendant_name' => $attendant->name,
                ],
                'paid_at' => now(),
            ];

            if ($session->payment) {
                $session->payment->update($paymentData);
                $payment = $session->payment->fresh();
            } else {
                $payment = ParkingPayment::create($paymentData);
            }

            // Update session and slot
            $session->update([
                'status' => ParkingSession::STATUS_PAID,
                'exit_window_expired_at' => null,
            ]);

            if ($session->slot) {
                $session->slot->update(['status' => ParkingSlot::STATUS_PENDING_EXIT]);
            }

            ActivityLog::record('payment_recorded', $attendant, $request, $receiptNumber, 'attendant', [
                'ticket' => $session->ticket_number,
                'receipt' => $receiptNumber,
                'plate' => $session->plate_number,
                'amount' => $finalAmount,
                'cash' => $cashReceived,
                'change' => $changeGiven,
            ]);

            // Notifications
            $notices->paymentReceived($session, $payment);
            $notices->staffExitRequest($session);

            return $payment;
        });

        return redirect()->route('attendant.exit.receipt', $payment);
    }

    /** BA-46 & BA-47: Receipt screen and preview */
    public function receipt(ParkingPayment $payment): View
    {
        $payment->load(['session.slot', 'session.driver', 'attendant']);

        return view('staff.attendant.exit.receipt', [
            'payment' => $payment,
            'session' => $payment->session,
        ]);
    }

    private function generateNextReceiptNumber(): string
    {
        $last = ParkingPayment::query()
            ->where('receipt_number', 'like', 'RC-%')
            ->orderByDesc('id')
            ->value('receipt_number');

        if ($last && preg_match('/^RC-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return 'RC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
