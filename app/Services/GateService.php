<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gate validation (process document 6.7): the exit queue, Good to Go, manual
 * search, and the exit window job.
 */
class GateService
{
    public function __construct(private NoticeService $notices, private FeeCalculator $fees) {}

    /** The three things the Guard ticks before approving. */
    public const CHECKS = ['plate', 'details', 'ticket'];

    public function windowEnd(ParkingSession $session): ?Carbon
    {
        $paidAt = $session->payment?->paid_at;

        return $paidAt ? $paidAt->copy()->addMinutes(Lot::exitWindowMinutes()) : null;
    }

    public function minutesLeft(ParkingSession $session): int
    {
        $end = $this->windowEnd($session);

        return $end ? max(0, (int) ceil(($end->getTimestamp() - now()->getTimestamp()) / 60)) : 0;
    }

    /** Paid vehicles waiting for the Guard, the oldest request first. */
    public function pendingExits(): Collection
    {
        return ParkingSession::query()
            ->where('status', ParkingSession::STATUS_PAID)
            ->whereHas('payment')
            ->with(['payment', 'slot'])
            ->get()
            ->sortBy(fn (ParkingSession $s) => $s->payment->paid_at)
            ->values();
    }

    public function approvedToday(): int
    {
        return ParkingSession::query()
            ->where('status', ParkingSession::STATUS_COMPLETED)
            ->whereNotNull('exit_approved_by')
            ->whereBetween('exited_at', [Lot::dayStart(), Lot::dayEnd()])
            ->count();
    }

    public function openLongParkingAlerts(): Collection
    {
        return Incident::query()
            ->where('category', 'Long-parked vehicle')
            ->where('source', 'auto')
            ->whereIn('status', ['open', 'under_review'])
            ->with(['session', 'slot'])
            ->latest('reported_at')
            ->get();
    }

    /**
     * Approve the exit. The server re-checks everything the screen showed.
     *
     * @param  array<string, mixed>  $checks
     */
    public function approve(ParkingSession $session, User $guard, array $checks, ?Request $request = null): ParkingSession
    {
        foreach (self::CHECKS as $check) {
            if (empty($checks[$check])) {
                throw new GateException('checks', 'Finish all 3 checks before Good to Go. Unchecked items are outlined in red. The slot stays Pending exit.');
            }
        }

        $windowEnded = false;

        $approved = DB::transaction(function () use ($session, $guard, &$windowEnded) {
            $fresh = ParkingSession::query()->lockForUpdate()->with('payment')->find($session->id);

            if (! $fresh || $fresh->status !== ParkingSession::STATUS_PAID || ! $fresh->payment) {
                throw new GateException('not_paid', 'This vehicle has not paid. The exit cannot be approved.');
            }

            if (now()->greaterThan($this->windowEnd($fresh))) {
                $windowEnded = true;

                return null;
            }

            $fresh->update([
                'status' => ParkingSession::STATUS_COMPLETED,
                'exited_at' => now(),
                'exit_approved_by' => $guard->id,
            ]);

            if ($fresh->slot_id) {
                ParkingSlot::query()->whereKey($fresh->slot_id)
                    ->whereIn('status', [ParkingSlot::STATUS_PENDING_EXIT, ParkingSlot::STATUS_OCCUPIED])
                    ->update(['status' => $this->heldToday($fresh->slot_id) ? ParkingSlot::STATUS_RESERVED : ParkingSlot::STATUS_AVAILABLE]);
            }

            ParkingReservation::query()
                ->where('session_id', $fresh->id)
                ->where('status', ParkingReservation::STATUS_CHECKED_IN)
                ->update(['status' => ParkingReservation::STATUS_COMPLETED]);

            Incident::query()
                ->where('session_id', $fresh->id)
                ->where('category', 'Long-parked vehicle')
                ->where('source', 'auto')
                ->where('status', '!=', 'resolved')
                ->get()
                ->each(function (Incident $incident) use ($guard, $fresh): void {
                    $note = "Vehicle exited. Exit approved by {$guard->name}.";
                    $incident->update([
                        'status' => 'resolved',
                        'resolved_by' => $guard->id,
                        'resolved_at' => now(),
                        'resolution_note' => $note,
                    ]);
                    IncidentNote::create(['incident_id' => $incident->id, 'user_id' => null, 'kind' => 'system', 'body' => "Resolved. {$note}"]);
                });

            return $fresh;
        });

        if ($windowEnded) {
            $this->expireOne($session->id);

            throw new GateException('window_ended', 'The exit window ended. The session is open again and the vehicle goes back to the booth.');
        }

        $approved->load('slot');
        $this->notices->parkingCompleted($approved);
        ActivityLog::record('exit_approved', $guard, $request, $approved->ticket_number, 'guard', [
            'plate' => $approved->plate_number, 'slot' => $approved->slot_code, 'receipt' => $approved->payment?->receipt_number,
        ]);

        return $approved;
    }

    /** The Guard did not approve because plate, color or type, or ticket did not match. */
    public function recordMismatch(ParkingSession $session, User $guard, array $reasons, ?Request $request = null): void
    {
        ActivityLog::record('exit_mismatch', $guard, $request, $session->ticket_number, 'guard', [
            'reasons' => array_values($reasons), 'plate' => $session->plate_number,
        ]);
    }

    /** Scheduled job, every minute. A paid session whose exit window ended goes back to Active. */
    public function expireWindows(): int
    {
        $count = 0;

        ParkingSession::query()
            ->where('status', ParkingSession::STATUS_PAID)
            ->whereHas('payment', fn ($query) => $query->where('paid_at', '<', now()->subMinutes(Lot::exitWindowMinutes())))
            ->pluck('id')
            ->each(function (int $id) use (&$count): void {
                if ($this->expireOne($id)) {
                    $count++;
                }
            });

        return $count;
    }

    private function expireOne(int $id): bool
    {
        $expired = DB::transaction(function () use ($id) {
            $session = ParkingSession::query()->lockForUpdate()->with('payment')->find($id);

            if (! $session || $session->status !== ParkingSession::STATUS_PAID || ! $session->payment) {
                return null;
            }

            if (now()->lessThanOrEqualTo($this->windowEnd($session))) {
                return null;
            }

            $session->update(['status' => ParkingSession::STATUS_ACTIVE, 'exit_window_expired_at' => now()]);

            if ($session->slot_id) {
                ParkingSlot::query()->whereKey($session->slot_id)
                    ->where('status', ParkingSlot::STATUS_PENDING_EXIT)
                    ->update(['status' => ParkingSlot::STATUS_OCCUPIED]);
            }

            return $session;
        });

        if (! $expired) {
            return false;
        }

        $this->notices->exitWindowEnded($expired);
        $this->notices->staffExitWindowEnded($expired);
        ActivityLog::record('exit_window_ended', null, null, $expired->ticket_number, 'system', ['plate' => $expired->plate_number]);

        return true;
    }

    /** A booked reservation for today still holds this slot, so it goes back to Reserved, not Available. */
    private function heldToday(int $slotId): bool
    {
        return ParkingReservation::query()
            ->where('slot_id', $slotId)
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [Lot::dayStart(), Lot::dayEnd()])
            ->exists();
    }

    /** Look up a session by ticket number or plate. A plate with an open session wins over older ones. */
    public function search(string $query): ?ParkingSession
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $sessions = ParkingSession::query()->with(['payment', 'slot', 'exitApprover', 'incidents']);

        if (preg_match('/^PK-?(\d+)$/i', $query, $m)) {
            $ticket = 'PK-'.str_pad($m[1], 4, '0', STR_PAD_LEFT);

            return (clone $sessions)->where('ticket_number', $ticket)->first()
                ?? (clone $sessions)->where('ticket_number', strtoupper($query))->first();
        }

        $plate = Vehicle::normalizePlate($query);

        return (clone $sessions)->where('plate_number', $plate)->whereIn('status', ParkingSession::OPEN_STATUSES)->latest('entered_at')->first()
            ?? (clone $sessions)->where('plate_number', $plate)->latest('entered_at')->first();
    }

    /** What an unpaid session owes now. */
    public function amountDue(ParkingSession $session): float
    {
        return (float) $this->fees->estimate($session)['total'];
    }
}
