<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates the driver notifications (N-01 to N-13) and the staff alerts (S-01 to S-06).
 *
 * Each method writes one row to bayline_notices. The switches on the manager's
 * Settings screen decide whether a type is created. A walk-in without an
 * account gets nothing.
 */
class NoticeService
{
    /** Which Settings switch controls which kind of notice. Kinds not listed are always created. */
    private const SWITCHES = [
        'reservation_confirmed' => 'notify_reservation_confirmed',
        'reservation_expired' => 'notify_reservation_expired',
        'long_parking' => 'notify_long_parking',
        'long_parking_report' => 'notify_long_parking',
        'payment_received' => 'notify_payment_exit_approved',
        'exit_approved' => 'notify_payment_exit_approved',
        'parking_completed' => 'notify_payment_exit_approved',
    ];

    /** The driver account that owns a plate, found through a saved vehicle. */
    public function driverForPlate(?string $plate): ?User
    {
        if (! $plate) {
            return null;
        }

        $vehicle = Vehicle::query()->where('plate_number', Vehicle::normalizePlate($plate))->with('user')->first();

        return $vehicle?->user?->isDriver() ? $vehicle->user : null;
    }

    public function driverForSession(ParkingSession $session): ?User
    {
        return $session->driver_id
            ? User::query()->where('role', User::ROLE_DRIVER)->find($session->driver_id)
            : $this->driverForPlate($session->plate_number);
    }

    // ------------------------------------------------------------------
    // Driver notices
    // ------------------------------------------------------------------

    public function reservationConfirmed(ParkingReservation $r): ?Notice
    {
        $opens = $r->scheduled_at->copy()->subMinutes(Lot::earlyMinutes());
        $ends = $r->scheduled_at->copy()->addMinutes(Lot::graceMinutes());

        return $this->toDriver($r->driver, 'N-01', 'reservation_confirmed', 'Reservation confirmed',
            "Reservation {$r->code} is confirmed for slot {$this->slotCode($r)} ".Lot::whenText($r->scheduled_at).'. '
            .'Show the QR code to the booth attendant. You can check in from '.Lot::time($opens).' until '.Lot::time($ends).'.',
            $r, ['code' => $r->code]);
    }

    public function reservationExpired(ParkingReservation $r): ?Notice
    {
        $ends = $r->scheduled_at->copy()->addMinutes(Lot::graceMinutes());

        return $this->toDriver($r->driver, 'N-03', 'reservation_expired', 'Reservation expired',
            "Reservation {$r->code} expired at ".Lot::time($ends).' because the vehicle did not check in within '
            .Lot::graceMinutes().' minutes. The slot was released and the code no longer works.',
            $r);
    }

    public function reservationCanceled(ParkingReservation $r): ?Notice
    {
        return $this->toDriver($r->driver, 'N-04', 'reservation_canceled', 'Reservation canceled',
            "Reservation {$r->code} was canceled. Slot {$this->slotCode($r)} is released.", $r);
    }

    public function parkingStarted(ParkingSession $s): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-05', 'parking_started', 'Parking started',
            "Parking started. Your slot is {$this->slotCode($s)}. Ticket {$s->ticket_number}. Entry ".Lot::time($s->entered_at).'.',
            $s);
    }

    public function paymentReceived(ParkingSession $s, ParkingPayment $p): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-06', 'payment_received', 'Payment received',
            'Payment of '.Lot::peso($p->total)." received for ticket {$s->ticket_number}. Receipt {$p->receipt_number} is in your parking history. "
            .'Drive to the exit gate within '.Lot::exitWindowMinutes().' minutes.',
            $s);
    }

    public function outstandingBalance(ParkingSession $s, float $amount): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-07', 'outstanding_balance', 'Outstanding balance',
            "Ticket {$s->ticket_number} has an unpaid balance of ".Lot::peso($amount).'. Pay at the booth before you leave.',
            $s, [], "balance-{$s->id}-".now()->format('YmdHi'));
    }

    public function exitWindowEnded(ParkingSession $s): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-08', 'exit_window_ended', 'Exit window ended',
            'The '.Lot::exitWindowMinutes()." minute exit window for ticket {$s->ticket_number} has ended. "
            .'The session is open again. See the booth attendant for the updated amount.',
            $s);
    }

    public function parkingCompleted(ParkingSession $s): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-09', 'parking_completed', 'Parking completed',
            "Parking complete. Ticket {$s->ticket_number} closed at ".Lot::time($s->exited_at).'. Thank you for parking at Bayline.',
            $s);
    }

    public function longParkedAuto(ParkingSession $s, Incident $incident): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-10', 'long_parking', 'Long parking notice',
            'Your vehicle '.Lot::plate($s->plate_number)." in slot {$this->slotCode($s)} has been parked for more than "
            .Lot::longParkingHours().' hours, since '.Lot::stamp($s->entered_at).'. Please collect it or contact the booth.',
            $s, ['incident' => $incident->code, 'automatic' => true], "long-parking-{$s->id}");
    }

    public function longParkedReported(ParkingSession $s, Incident $incident): ?Notice
    {
        return $this->toDriver($this->driverForSession($s), 'N-11', 'long_parking_report', 'Long parking notice',
            'A security guard reported your vehicle '.Lot::plate($s->plate_number)." as long-parked in slot {$this->slotCode($s)}. "
            .'Please collect it or contact the booth.',
            $s, ['incident' => $incident->code, 'automatic' => false], "long-parking-{$s->id}");
    }

    public function incidentAboutVehicle(User $driver, Incident $incident): ?Notice
    {
        return $this->toDriver($driver, 'N-12', 'incident_report', 'Security report about your vehicle',
            "Security report {$incident->code} mentions your vehicle ".Lot::plate($incident->plate_number).': '
            .strtolower(Incident::guardLabel($incident->category)).'. Please contact the booth.',
            $incident, ['incident' => $incident->code]);
    }

    // ------------------------------------------------------------------
    // Staff alerts
    // ------------------------------------------------------------------

    /** S-01. The Guard panel reads open long-parking incidents live, so only the Manager row is stored. */
    public function staffLongParking(ParkingSession $s, Incident $incident): void
    {
        $this->toRoles([User::ROLE_MANAGER], 'S-01', 'long_parking', 'Long-parked vehicle',
            Lot::plate($s->plate_number)." in {$this->slotCode($s)} passed ".Lot::longParkingHours()." h. {$incident->code} is open.",
            $incident, [], "s01-{$incident->id}");
    }

    /** S-02. Call this when the booth records a payment. The Guard queue itself reads paid sessions live. */
    public function staffExitRequest(ParkingSession $s): void
    {
        $this->toRoles([User::ROLE_MANAGER], 'S-02', 'exit_request', 'Exit pending at the gate',
            Lot::plate($s->plate_number).' paid, waiting for the Guard.', $s, [], "s02-{$s->id}");
    }

    public function staffReservationExpired(ParkingReservation $r): void
    {
        $this->toRoles([User::ROLE_MANAGER, User::ROLE_ATTENDANT], 'S-03', 'reservation_expired', 'Reservation expired',
            "{$r->code} released slot {$this->slotCode($r)}.", $r, [], "s03-{$r->id}");
    }

    public function staffNewIncident(Incident $incident): void
    {
        $by = $incident->reporter?->name ?? 'the system';

        $this->toRoles([User::ROLE_MANAGER], 'S-04', 'new_incident', 'New incident',
            "{$incident->code} ".Incident::guardLabel($incident->category)." reported by {$by}.", $incident, [], "s04-{$incident->id}");
    }

    public function staffExitWindowEnded(ParkingSession $s): void
    {
        $this->toRoles([User::ROLE_ATTENDANT, User::ROLE_GUARD], 'S-05', 'exit_window_ended', 'Exit window ended',
            "{$s->ticket_number} is open again and needs a new payment check.", $s, [], "s05-{$s->id}-".now()->timestamp);
    }

    // ------------------------------------------------------------------
    // Plumbing
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function toDriver(?User $driver, string $code, string $kind, string $title, string $body, ?Model $subject = null, array $data = [], ?string $groupKey = null): ?Notice
    {
        if (! $driver || ! $this->enabled($kind)) {
            return null;
        }

        if ($groupKey && Notice::query()->where('user_id', $driver->id)->where('group_key', $groupKey)->exists()) {
            return null;
        }

        return Notice::create([
            'user_id' => $driver->id,
            'audience_role' => null,
            'code' => $code,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'subject_type' => $subject ? $this->subjectType($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'group_key' => $groupKey,
        ]);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $data
     */
    public function toRoles(array $roles, string $code, string $kind, string $title, string $body, ?Model $subject = null, array $data = [], ?string $groupKey = null): void
    {
        foreach ($roles as $role) {
            $key = $groupKey ? "{$groupKey}-{$role}" : null;

            if ($key && Notice::query()->whereNull('user_id')->where('audience_role', $role)->where('group_key', $key)->exists()) {
                continue;
            }

            Notice::create([
                'user_id' => null,
                'audience_role' => $role,
                'code' => $code,
                'kind' => $kind,
                'title' => $title,
                'body' => $body,
                'subject_type' => $subject ? $this->subjectType($subject) : null,
                'subject_id' => $subject?->getKey(),
                'data' => $data ?: null,
                'group_key' => $key,
            ]);
        }
    }

    private function enabled(string $kind): bool
    {
        $switch = self::SWITCHES[$kind] ?? null;

        return $switch === null || (bool) Lot::setting($switch, true);
    }

    private function subjectType(Model $subject): string
    {
        return match (true) {
            $subject instanceof ParkingReservation => 'reservation',
            $subject instanceof ParkingSession => 'session',
            $subject instanceof Incident => 'incident',
            default => strtolower(class_basename($subject)),
        };
    }

    private function slotCode(ParkingReservation|ParkingSession $record): string
    {
        return $record->slot?->code ?? $record->slot_code ?? '-';
    }
}
