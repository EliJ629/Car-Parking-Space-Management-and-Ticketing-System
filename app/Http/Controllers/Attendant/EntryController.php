<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\NoticeService;
use App\Services\BanService;
use App\Services\VehicleBanned;
use App\Support\Lot;
use App\Support\PlateNumber;
use App\Support\Rules;
use App\Support\TicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EntryController extends Controller
{
    /** BA-11: Choose entry type: Walk-in or Reserved */
    public function index(): View
    {
        $counts = [
            'available' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_AVAILABLE)->count(),
            'reserved' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_RESERVED)->count(),
            'occupied' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_OCCUPIED)->count(),
            'pending_exit' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_PENDING_EXIT)->count(),
            'out_of_service' => ParkingSlot::query()->where('status', ParkingSlot::STATUS_OUT_OF_SERVICE)->count(),
        ];

        return view('staff.attendant.entry.index', [
            'counts' => $counts,
        ]);
    }

    /** BA-12 to BA-17: Walk-in entry workflow */
    public function walkIn(Request $request): View
    {
        $slots = ParkingSlot::query()->orderBy('code')->get();

        $counts = [
            'available' => $slots->where('status', ParkingSlot::STATUS_AVAILABLE)->count(),
            'reserved' => $slots->where('status', ParkingSlot::STATUS_RESERVED)->count(),
            'pending_exit' => $slots->where('status', ParkingSlot::STATUS_PENDING_EXIT)->count(),
            'out_of_service' => $slots->where('status', ParkingSlot::STATUS_OUT_OF_SERVICE)->count(),
        ];

        // Generate next ticket number
        $nextTicket = $this->generateNextTicketNumber();

        // Check nearest pending exit for BA-23 if lot is full
        $nearestPending = null;
        if ($counts['available'] === 0) {
            $nearestPending = ParkingSession::query()
                ->where('status', ParkingSession::STATUS_PAID)
                ->with(['payment', 'slot'])
                ->orderBy('updated_at')
                ->first();
        }

        $zonePositions = ParkingLayoutZone::query()
            ->whereIn('zone', $slots->pluck('zone')->unique())
            ->get()
            ->keyBy('zone');

        return view('staff.attendant.entry.walk-in', [
            'slots' => $slots,
            'counts' => $counts,
            'nextTicket' => $nextTicket,
            'vehicleTypes' => Vehicle::DRIVER_TYPES,
            'vehicleColors' => Vehicle::COLORS,
            'nearestPending' => $nearestPending,
            'zonePositions' => $zonePositions,
        ]);
    }

    /** BA-12/13/14/21: Plate lookup */
    public function lookupPlate(Request $request): JsonResponse
    {
        $rawPlate = trim((string) $request->input('plate', ''));
        $normPlate = Vehicle::normalizePlate($rawPlate);

        if (! PlateNumber::isValid($normPlate)) {
            return response()->json([
                'ok' => false,
                'error' => PlateNumber::problem($rawPlate),
            ], 422);
        }

        if (app(BanService::class)->isBanned($normPlate)) {
            return response()->json([
                'ok' => false,
                'status' => 'banned',
                'error' => VehicleBanned::MESSAGE,
            ], 422);
        }

        // BR-09: Check if plate already has an active or paid session (BA-21)
        $existing = ParkingSession::query()
            ->where('plate_number', $normPlate)
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => false,
                'status' => 'active_session_exists',
                'session' => [
                    'id' => $existing->id,
                    'ticket_number' => $existing->ticket_number,
                    'plate' => Lot::plate($existing->plate_number),
                    'slot' => $existing->slot_code,
                    'entry' => Lot::stamp($existing->entered_at),
                ],
            ]);
        }

        // Check if plate belongs to a registered driver (BA-13)
        $vehicle = Vehicle::query()
            ->where('plate_number', $normPlate)
            ->with('user')
            ->first();

        // Check for any expired reservation on this plate
        $expiredRsv = ParkingReservation::query()
            ->where('plate_number', $normPlate)
            ->where('status', ParkingReservation::STATUS_EXPIRED)
            ->latest('id')
            ->first();

        if ($vehicle && $vehicle->user) {
            return response()->json([
                'ok' => true,
                'status' => 'registered',
                'vehicle' => [
                    'plate' => Lot::plate($vehicle->plate_number),
                    'driver_id' => $vehicle->user_id,
                    'driver_name' => $vehicle->user->name,
                    'phone' => Lot::phone($vehicle->user->phone ?? $vehicle->contact_number),
                    'color' => $vehicle->color,
                    'type' => $vehicle->vehicle_type,
                    'last_visit' => Lot::date($vehicle->updated_at),
                ],
                'expired_reservation' => $expiredRsv ? [
                    'code' => $expiredRsv->code,
                    'time' => Lot::time($expiredRsv->expired_at ?? $expiredRsv->scheduled_at),
                ] : null,
            ]);
        }

        // Not found: guest vehicle (BA-14)
        return response()->json([
            'ok' => true,
            'status' => 'guest',
            'plate' => Lot::plate($normPlate),
        ]);
    }

    /** BA-17/BA-18: Store Walk-in and Create Ticket */
    public function storeWalkIn(Request $request, NoticeService $notices): RedirectResponse
    {
        $rawPhone = $request->input('phone');
        $cleanPhone = is_string($rawPhone) ? preg_replace('/[\s-]+/', '', $rawPhone) : $rawPhone;

        $request->merge([
            'plate' => Vehicle::normalizePlate($request->input('plate')),
            'phone' => Lot::normalizePhone($cleanPhone),
        ]);

        $validated = $request->validate([
            'plate' => Rules::parkablePlate(true),
            'phone' => Rules::mobile(false),
            'color' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z\s-]+$/'],
            'type' => ['required', 'string', Rule::in(Vehicle::DRIVER_TYPES), function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                // The plate must be a shape this kind of vehicle may carry (a car plate on a motorcycle is a mistake at the booth).
                if (! \App\Support\PlateNumber::isValid((string) $request->input('plate'), (string) $value)) {
                    $fail(\App\Support\PlateNumber::problem((string) $request->input('plate'), (string) $value) ?? 'The plate does not match the vehicle type.');
                }
            }],
            'slot_code' => ['required', 'string', 'exists:parking_slots,code'],
            'driver_id' => ['nullable', 'exists:users,id'],
        ], [
            'type.in' => 'Please select an allowed vehicle type (Car, SUV, Van, Pickup, or Motorcycle).',
            'color.regex' => 'Vehicle color must contain letters only.',
        ]);

        $normPlate = Vehicle::normalizePlate($validated['plate']);
        $attendant = $request->user();

        // Start transaction with row lock
        $session = DB::transaction(function () use ($validated, $normPlate, $attendant, $notices, $request) {
            // Check plate not already in lot
            $alreadyParked = ParkingSession::query()
                ->where('plate_number', $normPlate)
                ->whereIn('status', ParkingSession::OPEN_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($alreadyParked) {
                return null;
            }

            // Lock slot
            $slot = ParkingSlot::query()
                ->where('code', $validated['slot_code'])
                ->lockForUpdate()
                ->first();

            if (! $slot || $slot->status !== ParkingSlot::STATUS_AVAILABLE || \App\Models\ParkingReservation::slotHeldNow($slot->id, true)) {
                return false; // Slot taken (BA-22), or a booking is about to arrive
            }

            // Motorcycles park in motorcycle zones, every other vehicle in car zones.
            if ($zoneProblem = $slot->wrongZoneMessage($validated['type'])) {
                throw ValidationException::withMessages(['slot_code' => $zoneProblem]);
            }

            $ticketNumber = $this->generateNextTicketNumber();

            $newSession = ParkingSession::create([
                'ticket_number' => $ticketNumber,
                'driver_id' => $validated['driver_id'] ?? null,
                'slot_id' => $slot->id,
                'slot_code' => $slot->code,
                'attendant_id' => $attendant->id,
                'plate_number' => $normPlate,
                'vehicle_color' => $validated['color'],
                'vehicle_type' => $validated['type'],
                'driver_phone' => Lot::normalizePhone($validated['phone'] ?? null),
                'entered_at' => now(),
                'status' => ParkingSession::STATUS_ACTIVE,
            ]);

            $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

            ActivityLog::record('ticket_created', $attendant, $request, $ticketNumber, 'attendant', [
                'ticket' => $ticketNumber,
                'plate' => $normPlate,
                'slot' => $slot->code,
                'kind' => 'walk-in',
            ]);

            // Notify registered driver if applicable (N-05)
            $notices->parkingStarted($newSession);

            return $newSession;
        });

        if ($session === false) {
            return back()->with('error_modal', [
                'type' => 'slot_just_taken',
                'slot' => $validated['slot_code'],
            ]);
        }

        if ($session === null) {
            $existing = ParkingSession::query()
                ->where('plate_number', $normPlate)
                ->whereIn('status', ParkingSession::OPEN_STATUSES)
                ->first();

            return back()->with('error_modal', [
                'type' => 'active_session_exists',
                'plate' => Lot::plate($normPlate),
                'ticket' => $existing?->ticket_number,
                'slot' => $existing?->slot_code,
                'entry' => Lot::stamp($existing?->entered_at),
                'session_id' => $existing?->id,
            ]);
        }

        return redirect()->route('attendant.entry.ticket', $session)->with('toast', ['Ticket created', $session->ticket_number]);
    }

    /** BA-18 to BA-20: Ticket generated screen */
    public function ticket(ParkingSession $session): View
    {
        $session->load(['slot', 'attendant', 'driver', 'reservation']);

        return view('staff.attendant.entry.ticket', [
            'session' => $session,
            'isReserved' => $session->reservation !== null,
        ]);
    }

    /** BA-24 to BA-32: Reserved check-in workflow */
    public function reserved(Request $request): View
    {
        $code = trim((string) $request->query('code', ''));

        return view('staff.attendant.entry.reserved', [
            'code' => $code,
            'earlyMinutes' => Lot::earlyMinutes(),
            'graceMinutes' => Lot::graceMinutes(),
        ]);
    }

    /** BA-25 to BA-31: Look up reservation */
    public function lookupReservation(Request $request): JsonResponse
    {
        $rawCode = strtoupper(trim((string) $request->input('code', '')));

        if (preg_match('/^[A-Z0-9]{6}$/', $rawCode)) {
            $rawCode = 'RSV-'.$rawCode;
        } elseif (preg_match('/^RSV([A-Z0-9]{6})$/', $rawCode, $m)) {
            $rawCode = 'RSV-'.$m[1];
        }

        if (! preg_match('/^RSV-[A-Z0-9]{6}$/', $rawCode)) {
            return response()->json([
                'ok' => false,
                'status' => 'invalid_format',
                'message' => 'Enter a code like RSV-4F7K29.',
            ], 422);
        }

        $rsv = ParkingReservation::query()
            ->where('code', $rawCode)
            ->with(['driver', 'slot', 'vehicle', 'session'])
            ->first();

        if (! $rsv) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'message' => "No reservation found for {$rawCode}. Check each character with the driver.",
            ]);
        }

        // Expired (BA-29)
        if ($rsv->status === ParkingReservation::STATUS_EXPIRED) {
            return response()->json([
                'ok' => false,
                'status' => 'expired',
                'reservation' => [
                    'code' => $rsv->code,
                    'driver' => $rsv->driver?->name ?? 'Driver',
                    'plate' => Lot::plate($rsv->plate_number),
                    'scheduled' => Lot::time($rsv->scheduled_at),
                    'grace_ended' => Lot::time($rsv->scheduled_at->copy()->addMinutes(Lot::graceMinutes())),
                    'slot' => $rsv->slot_code,
                ],
            ]);
        }

        // Canceled or Invalid (BA-30)
        if ($rsv->status === ParkingReservation::STATUS_CANCELED) {
            return response()->json([
                'ok' => false,
                'status' => 'canceled',
                'reservation' => [
                    'code' => $rsv->code,
                    'status' => 'CANCELED',
                    'canceled_by' => 'Driver, '.Lot::stamp($rsv->canceled_at ?? $rsv->updated_at),
                ],
            ]);
        }

        // Not approved yet, or turned down: no check in, and the QR code should not have been shown.
        if ($rsv->status === ParkingReservation::STATUS_REJECTED || $rsv->isAwaitingApproval()) {
            $rejected = $rsv->status === ParkingReservation::STATUS_REJECTED;

            return response()->json([
                'ok' => false,
                'status' => $rejected ? 'rejected' : 'awaiting_approval',
                'message' => $rejected
                    ? "Reservation {$rsv->code} was not approved. The driver was told to choose a new slot."
                    : "Reservation {$rsv->code} is not approved yet. Approve it first on the Reservations screen, then check the driver in.",
                'url' => route('attendant.reservations.index'),
            ]);
        }

        // Already checked in, pending exit, or completed (exited)
        if (in_array($rsv->status, [ParkingReservation::STATUS_CHECKED_IN, ParkingReservation::STATUS_COMPLETED], true)) {
            $isCompleted = ($rsv->status === ParkingReservation::STATUS_COMPLETED);
            $isPendingExit = (! $isCompleted && $rsv->session?->status === ParkingSession::STATUS_PAID);

            $statusText = $isCompleted
                ? 'COMPLETED (EXITED)'
                : ($isPendingExit ? 'PAID (PENDING EXIT)' : 'ALREADY CHECKED IN');

            $message = $isCompleted
                ? "Reservation {$rsv->code} has already completed its stay and exited."
                : ($isPendingExit
                    ? "Reservation {$rsv->code} has already paid and is currently waiting to exit at the barrier gate."
                    : "Reservation {$rsv->code} is already checked in and inside the parking lot.");

            $details = $isCompleted
                ? 'This driver has already completed parking and exited the lot. This QR cannot be used again.'
                : ($isPendingExit
                    ? 'This driver has already paid their fee and is in the 15-minute exit window heading to the barrier gate. Direct them to the exit barrier gate.'
                    : 'This vehicle is currently parked inside the lot under an active ticket.');

            return response()->json([
                'ok' => false,
                'status' => 'already_used',
                'message' => $message,
                'reservation' => [
                    'code' => $rsv->code,
                    'status' => $statusText,
                    'is_pending_exit' => $isPendingExit,
                    'is_completed' => $isCompleted,
                    'driver' => $rsv->driver?->name ?? 'Driver',
                    'plate' => Lot::plate($rsv->plate_number),
                    'details' => $details,
                ],
            ]);
        }

        // Check time window (BA-31: too early or grace ended)
        $now = Lot::now();
        $earlyOpen = $rsv->scheduled_at->copy()->subMinutes(Lot::earlyMinutes());
        $graceEnd = $rsv->scheduled_at->copy()->addMinutes(Lot::graceMinutes());

        if ($now->lt($earlyOpen)) {
            return response()->json([
                'ok' => false,
                'status' => 'too_early',
                'reservation' => [
                    'code' => $rsv->code,
                    'driver' => $rsv->driver?->name ?? 'Driver',
                    'plate' => Lot::plate($rsv->plate_number),
                    'scheduled' => Lot::time($rsv->scheduled_at),
                    'check_in_opens' => Lot::time($earlyOpen),
                    'slot' => $rsv->slot_code,
                ],
            ]);
        }

        if ($now->gt($graceEnd)) {
            // Grace ended
            return response()->json([
                'ok' => false,
                'status' => 'expired',
                'reservation' => [
                    'code' => $rsv->code,
                    'driver' => $rsv->driver?->name ?? 'Driver',
                    'plate' => Lot::plate($rsv->plate_number),
                    'scheduled' => Lot::time($rsv->scheduled_at),
                    'grace_ended' => Lot::time($graceEnd),
                    'slot' => $rsv->slot_code,
                ],
            ]);
        }

        // Valid reservation found! (BA-27)
        return response()->json([
            'ok' => true,
            'status' => 'confirmed',
            'reservation' => [
                'id' => $rsv->id,
                'code' => $rsv->code,
                'status' => 'CONFIRMED',
                'driver' => $rsv->driver?->name ?? 'Driver',
                'phone' => Lot::phone($rsv->driver?->phone),
                'scheduled' => Lot::stamp($rsv->scheduled_at),
                'check_in_window' => Lot::time($earlyOpen).' to '.Lot::time($graceEnd),
                'slot_code' => $rsv->slot_code,
                'slot_type' => $rsv->slot?->type ?? 'Standard',
                'slot_zone' => $rsv->slot?->zone ?? 'Zone A',
                'plate_raw' => $rsv->plate_number,
                'plate' => Lot::plate($rsv->plate_number),
                'color' => $rsv->vehicle?->color ?? 'White',
                'type' => $rsv->vehicle?->vehicle_type ?? 'Sedan',
            ],
        ]);
    }

    /** BA-32/33: Check in reservation */
    public function checkInReservation(Request $request, NoticeService $notices): RedirectResponse
    {
        $validated = $request->validate([
            'reservation_id' => ['required', 'exists:parking_reservations,id'],
            'entered_plate' => ['required', 'string'],
        ]);

        $attendant = $request->user();
        $enteredPlate = Vehicle::normalizePlate($validated['entered_plate']);

        $rsv = ParkingReservation::query()
            ->whereKey($validated['reservation_id'])
            ->with(['slot', 'driver', 'vehicle', 'session'])
            ->firstOrFail();

        // Cannot check in canceled, expired, already used, or completed reservations
        if ($rsv->status !== ParkingReservation::STATUS_BOOKED) {
            $isPendingExit = ($rsv->status === ParkingReservation::STATUS_CHECKED_IN && $rsv->session?->status === ParkingSession::STATUS_PAID);

            return back()->with('error_modal', [
                'type' => 'reservation_not_bookable',
                'status' => $isPendingExit ? 'PAID (PENDING EXIT)' : $rsv->statusLabel(),
                'code' => $rsv->code,
                'message' => $rsv->status === ParkingReservation::STATUS_CANCELED
                    ? "Reservation {$rsv->code} was canceled and cannot create a ticket."
                    : ($rsv->status === ParkingReservation::STATUS_COMPLETED
                        ? "Reservation {$rsv->code} has already completed its stay and exited."
                        : ($isPendingExit
                            ? "Reservation {$rsv->code} has already paid and is waiting to exit at the barrier gate."
                            : "Reservation {$rsv->code} is {$rsv->statusLabel()} and cannot be checked in.")),
            ]);
        }

        // The QR code only works for a reservation the booth attendant approved.
        if (! $rsv->hasQr()) {
            return back()->with('modal', [
                'title' => 'This reservation is not approved',
                'body' => "Reservation {$rsv->code} cannot be checked in until it is approved on the Reservations screen.",
                'primary' => ['label' => 'Open reservations', 'url' => route('attendant.reservations.index')],
            ]);
        }

        // BA-28: Plate mismatch check
        if ($enteredPlate !== Vehicle::normalizePlate($rsv->plate_number)) {
            return back()->with('error_modal', [
                'type' => 'plate_mismatch',
                'expected' => Lot::plate($rsv->plate_number),
                'entered' => Lot::plate($enteredPlate),
            ]);
        }

        // A vehicle banned after it reserved cannot check in. The reservation is left as it is.
        if (app(BanService::class)->isBanned($enteredPlate)) {
            return back()->with('modal', [
                'title' => 'This vehicle cannot park',
                'body' => VehicleBanned::MESSAGE,
                'primary' => ['label' => 'OK', 'close' => true],
                'tone' => 'stop',
            ]);
        }

        // Process check-in inside transaction
        $session = DB::transaction(function () use ($rsv, $attendant, $notices, $request) {
            $slot = ParkingSlot::query()->whereKey($rsv->slot_id)->lockForUpdate()->first();

            $ticketNumber = $this->generateNextTicketNumber();

            $newSession = ParkingSession::create([
                'ticket_number' => $ticketNumber,
                'driver_id' => $rsv->driver_id,
                'slot_id' => $slot->id,
                'slot_code' => $slot->code,
                'attendant_id' => $attendant->id,
                'plate_number' => $rsv->plate_number,
                'vehicle_color' => $rsv->vehicle?->color ?? 'White',
                'vehicle_type' => $rsv->vehicle?->vehicle_type ?? 'Sedan',
                'driver_phone' => $rsv->driver?->phone,
                'entered_at' => now(),
                'status' => ParkingSession::STATUS_ACTIVE,
            ]);

            $rsv->update([
                'status' => ParkingReservation::STATUS_CHECKED_IN,
                'session_id' => $newSession->id,
                'checked_in_at' => now(),
            ]);

            $slot->update(['status' => ParkingSlot::STATUS_OCCUPIED]);

            ActivityLog::record('reservation_checked_in', $attendant, $request, $rsv->code, 'attendant', [
                'code' => $rsv->code,
                'ticket' => $ticketNumber,
                'slot' => $slot->code,
            ]);

            $notices->parkingStarted($newSession);

            return $newSession;
        });

        return redirect()->route('attendant.entry.ticket', $session)->with('toast', ['Ticket created', $session->ticket_number]);
    }

    private function generateNextTicketNumber(): string
    {
        return TicketNumber::next();
    }
}
