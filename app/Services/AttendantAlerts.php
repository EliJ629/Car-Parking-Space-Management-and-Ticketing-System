<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Support\Lot;
use Illuminate\Support\Collection;

/**
 * What the Alerts chip and panel on the Booth Attendant console shows (BA-65):
 * - Expired reservations
 * - Vehicles paid and waiting for the Guard (pending exit)
 * - Reserved arrivals soon (within the next 30 minutes)
 * - Long-parked vehicles
 */
class AttendantAlerts
{
    /** @return Collection<int, array<string, mixed>> */
    public function items(): Collection
    {
        $now = Lot::now();
        $items = collect();

        // 1. Unread notices stored for the attendant role
        $notices = Notice::query()
            ->forRole('attendant')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function (Notice $n): array {
                return [
                    'key' => 'n-'.$n->id,
                    'title' => $n->title,
                    'body' => $n->body,
                    'time' => Lot::time($n->created_at),
                    'url' => match ($n->subject_type) {
                        'session' => route('attendant.exit.index', ['q' => ParkingSession::query()->whereKey($n->subject_id)->value('ticket_number')]),
                        'reservation' => $n->kind === 'reservation_pending' ? route('attendant.reservations.index') : route('attendant.entry.reserved'),
                        default => route('attendant.home'),
                    },
                    'unread' => $n->isUnread(),
                    'type' => $n->kind,
                    'sort' => $n->created_at->getTimestamp(),
                ];
            });

        // 2. Live pending exits waiting for the guard
        $pendingExits = ParkingSession::query()
            ->where('status', ParkingSession::STATUS_PAID)
            ->with(['payment', 'slot'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (ParkingSession $s): array {
                $rc = $s->payment?->receipt_number ?? '';

                return [
                    'key' => 'pending-'.$s->id,
                    'title' => 'Paid and waiting for the Guard',
                    'body' => "{$s->ticket_number}, ".Lot::plate($s->plate_number).", slot {$s->slot_code}.".($rc ? " Receipt {$rc}." : ''),
                    'time' => Lot::time($s->payment?->paid_at ?? $s->updated_at),
                    'url' => route('attendant.exit.show', $s),
                    'unread' => true,
                    'type' => 'pending_exit',
                    'sort' => ($s->payment?->paid_at ?? $s->updated_at)?->getTimestamp() ?? 0,
                ];
            });

        // 3. Reserved arrivals in the next 30 minutes (early check-in window open)
        $earlyWindowEnd = $now->copy()->addMinutes(Lot::earlyMinutes());
        $reservedSoon = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('approval_status', ParkingReservation::APPROVAL_APPROVED)
            ->whereBetween('scheduled_at', [$now->copy()->subMinutes(Lot::graceMinutes()), $earlyWindowEnd])
            ->with(['driver', 'slot'])
            ->get()
            ->map(function (ParkingReservation $r): array {
                $driverName = $r->driver?->name ?? 'Reserved Driver';

                return [
                    'key' => 'rsv-'.$r->id,
                    'title' => 'Reserved arrival soon',
                    'body' => "{$driverName}, ".Lot::plate($r->plate_number).", slot {$r->slot_code} at ".Lot::time($r->scheduled_at).'.',
                    'time' => Lot::time($r->scheduled_at),
                    'url' => route('attendant.entry.reserved', ['code' => $r->code]),
                    'unread' => false,
                    'type' => 'reserved_arrival',
                    'sort' => $r->scheduled_at?->getTimestamp() ?? 0,
                ];
            });

        return $notices
            ->concat($pendingExits)
            ->concat($reservedSoon)
            ->unique('key')
            ->sortByDesc('sort')
            ->values();
    }

    /** Reservations waiting for the attendant to approve or reject. */
    public function pendingApprovals(): int
    {
        return ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('approval_status', ParkingReservation::APPROVAL_PENDING)
            ->count();
    }

    public function count(?Collection $items = null): int
    {
        return ($items ?? $this->items())->where('unread', true)->count();
    }

    public function markRead(): void
    {
        Notice::query()->forRole('attendant')->unread()->update(['read_at' => now()]);
    }
}
