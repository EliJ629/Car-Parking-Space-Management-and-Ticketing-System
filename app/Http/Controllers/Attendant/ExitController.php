<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingDiscount;
use App\Models\ParkingPayment;
use App\Models\ParkingRateSet;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\ExitAuthorizationService;
use App\Services\FeeCalculator;
use App\Services\GateService;
use App\Services\NoticeService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use App\Support\TicketNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\ExitAuthorization;
use Illuminate\View\View;

class ExitController extends Controller
{
    /** BA-34 to BA-36: Exit search */
    public function index(Request $request): View
    {
        $rawQ = trim((string) $request->query('q', ''));
        $q = substr(preg_replace('/[^A-Za-z0-9\s-]/', '', $rawQ), 0, 20);

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
            $cleanTicket = TicketNumber::looksLikeTicket($q) ? TicketNumber::normalize($q) : null;

            $query = ParkingSession::query()
                ->with(['slot', 'payment', 'driver'])
                ->whereIn('status', ParkingSession::OPEN_STATUSES);

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
                $recalc['already_paid'] = ExitAuthorization::paidFor($session);
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

        // For attendant checkout, initial penalty is 0 unless attendant explicitly adds one at the booth
        $estimate['penalties'] = 0.0;
        $estimate['lost_ticket_penalty'] = 0.0;
        $estimate['overstay_penalty'] = 0.0;
        $estimate['total'] = max(0.0, round($estimate['parking_charge'] - ($estimate['already_paid'] ?? 0.0), 2));

        $discounts = ParkingDiscount::query()->where('active', true)->get();

        return view('staff.attendant.exit.show', [
            'session' => $session,
            'estimate' => $estimate,
            'discounts' => $discounts,
            'isReserved' => $session->reservation !== null,
            'currentRate' => ParkingRateSet::current(),
        ]);
    }

    /**
     * BA-44 to BA-46: Process cash payment.
     *
     * One payment, one exit permission. The session row is locked for the whole request, so two clicks or two
     * counters cannot both charge it: the second one finds the vehicle already paid (inside its window) and is
     * sent to the receipt without charging again. A permission that ran out is closed first, and the vehicle pays
     * only the difference between what it owes now and everything it has paid before.
     */
    public function pay(Request $request, ParkingSession $session, FeeCalculator $calculator, NoticeService $notices, GateService $gate, ExitAuthorizationService $authorizations): RedirectResponse
    {
        $validated = $request->validate([
            'cash_received' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'discount_id' => ['nullable', 'exists:parking_discounts,id'],
            'discount_proof' => ['nullable', 'string', 'max:80'],
            'discount_note' => ['nullable', 'string', 'max:255'],
            'lost_ticket' => ['nullable', 'boolean'],
            'overstay' => ['nullable', 'boolean'],
        ], [
            'cash_received.required' => 'Please enter the cash received.',
            'cash_received.numeric' => 'Cash received must be a valid number.',
            'cash_received.min' => 'Cash received cannot be negative.',
            'cash_received.max' => 'Cash received cannot exceed ₱9,999,999.99.',
        ]);

        $attendant = $request->user();

        // A permission whose time is up is closed before anything is charged (a no-op when it is still valid).
        if ($session->status === ParkingSession::STATUS_PAID) {
            $gate->expireOne($session->id);
        }

        $outcome = DB::transaction(function () use ($session, $validated, $request, $attendant, $calculator, $notices, $authorizations): array {
            $locked = ParkingSession::query()->lockForUpdate()->with(['payment', 'slot'])->findOrFail($session->id);

            if ($locked->status === ParkingSession::STATUS_COMPLETED) {
                return ['refused' => 'This vehicle has already left. Its exit was confirmed, so nothing can be charged.'];
            }

            // Already paid and still inside the exit window: a repeat click or a second counter. Charge nothing.
            if ($locked->status === ParkingSession::STATUS_PAID && $locked->payment) {
                return ['payment' => $locked->payment, 'repeat' => true];
            }

            $estimate = $calculator->estimate($locked, now());
            $parkingCharge = $estimate['parking_charge'];

            $penalties = 0.0;
            $currentRate = ParkingRateSet::current();
            $locked->lost_ticket = $request->boolean('lost_ticket');

            if ($locked->lost_ticket) {
                $penalties += (float) ($currentRate?->lost_ticket_penalty ?? 100);
            }

            if ($request->boolean('overstay')) {
                $penalties += (float) ($currentRate?->overstay_penalty ?? 150);
            }

            $subtotal = $parkingCharge + $penalties;

            $discountAmount = 0.0;
            $discountName = null;

            if (! empty($validated['discount_id']) && ($discount = ParkingDiscount::query()->find($validated['discount_id']))) {
                $proof = trim((string) ($validated['discount_proof'] ?? ''));

                if ($proof === '') {
                    throw ValidationException::withMessages(['discount_proof' => 'An ID number or proof is required to apply this discount.']);
                }

                $lowerName = strtolower($discount->name);
                $isDummy = (bool) preg_match('/^(?:0+|12345|test|none|asdf|demo)$/i', $proof);

                if (str_contains($lowerName, 'senior')) {
                    $validSenior = preg_match('/^(?:(?:SC|OSCA|SR)[-\s]?[A-Z0-9-]{4,18}|[0-9]{2,4}[-\s]?[0-9]{4,10}|[0-9]{4}[-\s][0-9]{4}[-\s][0-9]{4}[-\s]?[0-9]{4}|[A-Z0-9-]{5,20})$/i', $proof);

                    if (! $validSenior || $isDummy || strlen($proof) < 5) {
                        throw ValidationException::withMessages(['discount_proof' => 'Invalid Senior Citizen (OSCA) ID format. Example: SC-0048215 or 12-3456-789']);
                    }
                } elseif (str_contains($lowerName, 'pwd')) {
                    $validPwd = preg_match('/^(?:PWD[-\s]?[A-Z0-9-]{4,18}|[0-9]{2}[-\s][0-9]{4}[-\s][0-9]{3}[-\s]?[0-9]{4,7}|[0-9]{2,4}[-\s]?[0-9]{4,10}|[A-Z0-9-]{5,20})$/i', $proof);

                    if (! $validPwd || $isDummy || strlen($proof) < 5) {
                        throw ValidationException::withMessages(['discount_proof' => 'Invalid PWD ID format. Example: PWD-2024-00142 or 11-2402-001-0001234']);
                    }
                }

                $discountName = $discount->name;
                $discountAmount = round(($subtotal * $discount->percent) / 100, 2);
            }

            // What the vehicle still owes: the whole bill minus every payment it has already made.
            $alreadyPaid = $authorizations->alreadyPaid($locked);
            $finalAmount = max(0.0, round($subtotal - $discountAmount - $alreadyPaid, 2));
            $cashReceived = (float) $validated['cash_received'];

            // BA-45: the cash must cover the balance before any exit permission is issued.
            if ($cashReceived < $finalAmount) {
                return ['short' => [
                    'type' => 'insufficient_cash',
                    'shortfall' => Lot::peso($finalAmount - $cashReceived),
                    'amount_due' => Lot::peso($finalAmount),
                    'cash' => Lot::peso($cashReceived),
                ]];
            }

            $changeGiven = round($cashReceived - $finalAmount, 2);
            $receiptNumber = $locked->payment?->receipt_number ?: $this->generateNextReceiptNumber();
            $previousSnapshot = (array) ($locked->payment?->receipt_snapshot ?? []);

            $paymentData = [
                'receipt_number' => $receiptNumber,
                'session_id' => $locked->id,
                'received_by' => $attendant->id,
                'base_fee' => $estimate['first_hour'],
                'additional_fee' => $estimate['additional'],
                'penalty' => $penalties,
                'discount' => $discountAmount,
                // This payment only. Sales and shift cash add up payments, so each one counts once.
                'total' => $finalAmount,
                'method' => 'cash',
                'receipt_snapshot' => [
                    'ticket_number' => $locked->ticket_number,
                    'plate_number' => $locked->plate_number,
                    'slot_code' => $locked->slot_code,
                    'entry_time' => Lot::stamp($locked->entered_at),
                    'payment_time' => Lot::stamp(now()),
                    'base_fee' => $estimate['first_hour'],
                    'additional_fee' => $estimate['additional'],
                    'discount_name' => $discountName,
                    'discount_amount' => $discountAmount,
                    'discount_proof' => $validated['discount_proof'] ?? null,
                    'penalties' => $penalties,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                    'attendant_name' => $attendant->name,
                    'previously_paid' => $alreadyPaid,
                    'bill_total' => round($subtotal - $discountAmount, 2),
                    // The earlier payments stay on the receipt record. The ledger keeps them as well.
                    'earlier_payments' => $locked->payment
                        ? array_merge((array) ($previousSnapshot['earlier_payments'] ?? []), [[
                            'receipt' => $locked->payment->receipt_number,
                            'amount' => (float) $locked->payment->total,
                            'paid_at' => Lot::stamp($locked->payment->paid_at),
                            'cashier' => $previousSnapshot['attendant_name'] ?? null,
                        ]])
                        : [],
                ],
                'paid_at' => now(),
            ];

            if ($locked->payment) {
                $locked->payment->update($paymentData);
                $payment = $locked->payment->fresh();
            } else {
                $payment = ParkingPayment::create($paymentData);
            }

            $authorization = $authorizations->issue($locked, $payment, $attendant, $finalAmount, round($alreadyPaid + $finalAmount, 2));

            $payment->update(['receipt_snapshot' => array_merge($payment->receipt_snapshot, ['authorization' => $authorization->code, 'authorization_expires_at' => Lot::stamp($authorization->expires_at)])]);

            // The slot stays held (Pending exit) until the Guard confirms the exit.
            $locked->update(['status' => ParkingSession::STATUS_PAID, 'exit_window_expired_at' => null]);
            $locked->slot?->update(['status' => ParkingSlot::STATUS_PENDING_EXIT]);

            ActivityLog::record('payment_recorded', $attendant, $request, $receiptNumber, 'attendant', [
                'ticket' => $locked->ticket_number,
                'receipt' => $receiptNumber,
                'authorization' => $authorization->code,
                'plate' => $locked->plate_number,
                'amount' => $finalAmount,
                'previously_paid' => $alreadyPaid,
                'cash' => $cashReceived,
                'change' => $changeGiven,
            ]);

            $notices->paymentReceived($locked, $payment);
            $notices->staffExitRequest($locked);

            return ['payment' => $payment];
        });

        if (isset($outcome['refused'])) {
            return redirect()->route('attendant.exit.show', $session)->with('modal', [
                'title' => 'Nothing was charged', 'body' => $outcome['refused'], 'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        if (isset($outcome['short'])) {
            return back()->with('error_modal', $outcome['short']);
        }

        $redirect = redirect()->route('attendant.exit.receipt', $outcome['payment']);

        return empty($outcome['repeat']) ? $redirect->with('fresh_payment', true) : $redirect->with('modal', [
            'title' => 'Already paid', 'body' => 'This vehicle was already paid and its exit permission is still valid. No second charge was made.', 'primary' => ['label' => 'OK', 'close' => true],
        ]);
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
        $all = ParkingPayment::query()
            ->where('receipt_number', 'like', 'RC-%')
            ->pluck('receipt_number');

        $max = 0;
        foreach ($all as $rc) {
            if (preg_match('/^RC-(\d+)$/', $rc, $m)) {
                $num = (int) $m[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $next = $max + 1;
        while (ParkingPayment::where('receipt_number', 'RC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return 'RC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
