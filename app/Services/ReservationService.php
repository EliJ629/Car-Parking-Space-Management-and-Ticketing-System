<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reservations (process document 6.3): list the slots free at a time, create,
 * cancel, hold today's slots and expire what was not checked in.
 */
class ReservationService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Where the ID photos for PWD slots are kept. A private disk, never a public URL. */
    public const ID_DISK = 'local';

    public const ID_DIR = 'reservation-ids';

    public function __construct(private NoticeService $notices) {}

    /** Minutes either side of a booking in which the same slot or vehicle cannot be booked again. */
    public function overlapMinutes(): int
    {
        return Lot::earlyMinutes() + Lot::graceMinutes();
    }

    /**
     * Every slot with its state at a moment. Out of service slots and slots held by a
     * booking around that time are not free. For today, a slot in use right now is not free either.
     *
     * @return Collection<int, ParkingSlot>
     */
    public function boardAt(Carbon $at): Collection
    {
        $slots = ParkingSlot::query()->orderBy('zone')->orderBy('code')->get();
        $window = $this->overlapMinutes();

        $held = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [$at->copy()->subMinutes($window - 1), $at->copy()->addMinutes($window - 1)])
            ->pluck('slot_id')
            ->filter()
            ->all();

        $today = Lot::local($at)->isSameDay(Lot::now());

        return $slots->each(function (ParkingSlot $slot) use ($held, $today): void {
            $slot->setAttribute('free_at_time', true);
            $state = 'available';

            if ($slot->status === ParkingSlot::STATUS_OUT_OF_SERVICE) {
                $state = 'out_of_service';
            } elseif (in_array($slot->id, $held, true)) {
                $state = 'reserved';
            } elseif ($today && $slot->status === ParkingSlot::STATUS_OCCUPIED) {
                $state = 'occupied';
            } elseif ($today && $slot->status === ParkingSlot::STATUS_PENDING_EXIT) {
                $state = 'pending_exit';
            }

            $slot->setAttribute('state', $state);
            $slot->setAttribute('free_at_time', $state === 'available');
        });
    }

    /**
     * What keeps this driver from booking again: an open reservation, a checked-in reservation, or a car that
     * is still parked (ticket not yet exited). Null when the driver is free to reserve.
     *
     * @return array{kind:string,reservation:?ParkingReservation,session:?ParkingSession,slot:string,message:string}|null
     */
    public function blockingFor(User $driver): ?array
    {
        $reservation = ParkingReservation::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [ParkingReservation::STATUS_BOOKED, ParkingReservation::STATUS_CHECKED_IN])
            ->with('slot')
            ->orderBy('scheduled_at')
            ->first();

        if ($reservation) {
            $slot = $reservation->slot?->code ?? $reservation->slot_code;

            if ($reservation->status === ParkingReservation::STATUS_CHECKED_IN) {
                return ['kind' => 'checked_in', 'reservation' => $reservation, 'session' => null, 'slot' => $slot,
                    'message' => "You are checked in at slot {$slot} ({$reservation->code}). You can reserve again after you exit."];
            }

            return ['kind' => 'booked', 'reservation' => $reservation, 'session' => null, 'slot' => $slot,
                'message' => "{$reservation->code} holds {$slot} for you ".Lot::whenText($reservation->scheduled_at).'. You can reserve again after you exit, or cancel it first.'];
        }

        $session = app(DriverData::class)->openSession($driver);

        if ($session) {
            $slot = $session->slot?->code ?? $session->slot_code;

            return ['kind' => 'parked', 'reservation' => null, 'session' => $session, 'slot' => (string) $slot,
                'message' => "Your car is parked in slot {$slot} (ticket {$session->ticket_number}). You can reserve again after you exit."];
        }

        return null;
    }

    /** Create the reservation or throw a ReservationException that says why not. */
    public function create(User $driver, Vehicle $vehicle, ParkingSlot $slot, Carbon $at, ?Request $request = null, ?UploadedFile $idPhoto = null): ParkingReservation
    {
        $now = now();
        $needsId = $slot->type === ParkingSlot::TYPE_PWD;

        if ($needsId && ! $idPhoto) {
            throw new ReservationException('id_required', 'A PWD slot needs a photo of your PWD ID. Upload it to continue.');
        }

        $localAt = Lot::local($at);
        $latestDay = Lot::now()->addDays(Lot::advanceDays())->endOfDay();

        if ($at->lessThan($now->copy()->startOfMinute())) {
            throw new ReservationException('past', 'Choose a time that is not in the past.');
        }

        if ($localAt->greaterThan($latestDay)) {
            throw new ReservationException('too_far', 'You can reserve up to '.Lot::advanceDays().' days ahead. The latest date is '.$latestDay->format('D, M j').'.');
        }

        if ($vehicle->user_id !== $driver->id) {
            throw new ReservationException('no_vehicle', 'Choose one of your saved vehicles.');
        }

        $reservation = DB::transaction(function () use ($driver, $vehicle, $slot, $at, $needsId) {
            // Lock the driver row so two quick taps cannot both pass the one-reservation check.
            User::query()->lockForUpdate()->find($driver->id);

            if ($blocking = $this->blockingFor($driver)) {
                throw new ReservationException('driver_busy', $blocking['message'], $blocking['reservation'] ?? $blocking['session']);
            }

            $window = $this->overlapMinutes();
            $range = [$at->copy()->subMinutes($window - 1), $at->copy()->addMinutes($window - 1)];

            $same = ParkingReservation::query()
                ->where('status', ParkingReservation::STATUS_BOOKED)
                ->where(fn ($query) => $query->where('vehicle_id', $vehicle->id)->orWhere('plate_number', $vehicle->plate_number))
                ->whereBetween('scheduled_at', $range)
                ->first();

            if ($same) {
                throw new ReservationException('vehicle_busy',
                    "{$same->code} holds ".($same->slot?->code ?? $same->slot_code).' for you '.Lot::whenText($same->scheduled_at).'. One driver can have one reservation at a time.',
                    $same);
            }

            $fresh = ParkingSlot::query()->lockForUpdate()->find($slot->id);

            $taken = $fresh === null
                || $fresh->status === ParkingSlot::STATUS_OUT_OF_SERVICE
                || ParkingReservation::query()->where('slot_id', $fresh->id)->where('status', ParkingReservation::STATUS_BOOKED)->whereBetween('scheduled_at', $range)->exists()
                || (Lot::local($at)->isSameDay(Lot::now()) && in_array($fresh->status, [ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_PENDING_EXIT], true));

            if ($taken) {
                throw new ReservationException('slot_taken', "Slot {$slot->code} was just taken. Choose another slot.");
            }

            $reservation = ParkingReservation::create([
                'code' => $this->newCode(),
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'slot_id' => $fresh->id,
                'slot_code' => $fresh->code,
                'plate_number' => $vehicle->plate_number,
                'scheduled_at' => $at,
                'status' => ParkingReservation::STATUS_BOOKED,
                // The slot is held right away, but the QR code only works after the booth attendant approves.
                'approval_status' => ParkingReservation::APPROVAL_PENDING,
                'requires_id' => $needsId,
            ]);

            $this->holdSlotIfToday($reservation, $fresh);

            return $reservation;
        });

        if ($needsId && $idPhoto) {
            $path = $idPhoto->storeAs(self::ID_DIR, $reservation->code.'-'.bin2hex(random_bytes(6)).'.'.$this->photoExtension($idPhoto), self::ID_DISK);
            $reservation->update(['id_photo_path' => $path]);
        }

        $reservation->load(['slot', 'driver']);
        $this->notices->reservationSubmitted($reservation);
        $this->notices->staffReservationPending($reservation);
        ActivityLog::record('reservation_created', $driver, $request, $reservation->code, 'driver', [
            'slot' => $reservation->slot_code, 'plate' => $reservation->plate_number, 'scheduled_at' => $reservation->scheduled_at->toIso8601String(),
            'needs_id' => $needsId,
        ]);

        return $reservation;
    }

    /** The booth attendant accepts the request. The driver gets the QR code. Returns false when it was not waiting. */
    public function approve(ParkingReservation $reservation, User $attendant, ?Request $request = null): bool
    {
        $done = DB::transaction(function () use ($reservation, $attendant) {
            $fresh = ParkingReservation::query()->lockForUpdate()->find($reservation->id);

            if (! $fresh || ! $fresh->isAwaitingApproval()) {
                return false;
            }

            $fresh->update([
                'approval_status' => ParkingReservation::APPROVAL_APPROVED,
                'approved_by' => $attendant->id,
                'approved_at' => now(),
            ]);

            return true;
        });

        if ($done) {
            $reservation->refresh()->load(['slot', 'driver']);
            $this->notices->reservationConfirmed($reservation);
            ActivityLog::record('reservation_approved', $attendant, $request, $reservation->code, 'attendant', ['slot' => $reservation->slot_code]);
        }

        return $done;
    }

    /** The booth attendant turns the request down. The slot is released and the driver is told to pick another. */
    public function reject(ParkingReservation $reservation, User $attendant, string $reason, ?Request $request = null): bool
    {
        $done = DB::transaction(function () use ($reservation, $attendant, $reason) {
            $fresh = ParkingReservation::query()->lockForUpdate()->find($reservation->id);

            if (! $fresh || ! $fresh->isAwaitingApproval()) {
                return false;
            }

            $fresh->update([
                'status' => ParkingReservation::STATUS_REJECTED,
                'approval_status' => ParkingReservation::APPROVAL_REJECTED,
                'approved_by' => $attendant->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $this->releaseSlot($fresh);

            return true;
        });

        if ($done) {
            $reservation->refresh()->load(['slot', 'driver']);
            $this->discardIdPhoto($reservation);
            $this->notices->reservationRejected($reservation);
            ActivityLog::record('reservation_rejected', $attendant, $request, $reservation->code, 'attendant', ['slot' => $reservation->slot_code, 'reason' => $reason]);
        }

        return $done;
    }

    /** The ID photo is only needed to decide. Once the request is turned down it is deleted. */
    private function discardIdPhoto(ParkingReservation $reservation): void
    {
        if ($reservation->id_photo_path) {
            Storage::disk(self::ID_DISK)->delete($reservation->id_photo_path);
            $reservation->update(['id_photo_path' => null]);
        }
    }

    private function photoExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }

    /** Cancel a booking that is still Reserved. Returns false, changing nothing, when it is not. */
    public function cancel(ParkingReservation $reservation, User $actor, ?Request $request = null): bool
    {
        $done = DB::transaction(function () use ($reservation) {
            $fresh = ParkingReservation::query()->lockForUpdate()->find($reservation->id);

            if (! $fresh || $fresh->status !== ParkingReservation::STATUS_BOOKED) {
                return false;
            }

            $fresh->update(['status' => ParkingReservation::STATUS_CANCELED, 'canceled_at' => now()]);
            $this->releaseSlot($fresh);

            return true;
        });

        if ($done) {
            $reservation->refresh()->load(['slot', 'driver']);
            $this->discardIdPhoto($reservation);
            $this->notices->reservationCanceled($reservation);
            ActivityLog::record('reservation_canceled', $actor, $request, $reservation->code, 'driver', ['slot' => $reservation->slot_code]);
        }

        return $done;
    }

    /** Scheduled job, every minute. Expire bookings whose time plus the grace period has passed. */
    public function expireDue(): int
    {
        $limit = now()->subMinutes(Lot::graceMinutes());
        $ids = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('scheduled_at', '<', $limit)
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            $expired = DB::transaction(function () use ($id) {
                $reservation = ParkingReservation::query()->lockForUpdate()->find($id);

                // A booking that was checked in a moment earlier is skipped.
                if (! $reservation || $reservation->status !== ParkingReservation::STATUS_BOOKED) {
                    return null;
                }

                $reservation->update(['status' => ParkingReservation::STATUS_EXPIRED, 'expired_at' => now()]);
                $this->releaseSlot($reservation);

                return $reservation;
            });

            if ($expired) {
                $expired->load(['slot', 'driver']);
                $this->discardIdPhoto($expired);
                $this->notices->reservationExpired($expired);
                $this->notices->staffReservationExpired($expired);
                ActivityLog::record('reservation_expired', null, null, $expired->code, 'system', [
                    'slot' => $expired->slot_code, 'scheduled_at' => $expired->scheduled_at->toIso8601String(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /** A slot shows Reserved from the start of the reservation day (assumption AS-8). */
    public function holdTodaysSlots(): int
    {
        $count = 0;

        ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [Lot::dayStart(), Lot::dayEnd()])
            ->with('slot')
            ->get()
            ->each(function (ParkingReservation $reservation) use (&$count): void {
                if ($reservation->slot && $reservation->slot->status === ParkingSlot::STATUS_AVAILABLE) {
                    $reservation->slot->update(['status' => ParkingSlot::STATUS_RESERVED]);
                    $count++;
                }
            });

        return $count;
    }

    /** A new walk-in session cannot take a slot that is held, so Reserved is shown on the board from the day start. */
    private function holdSlotIfToday(ParkingReservation $reservation, ParkingSlot $slot): void
    {
        if (Lot::local($reservation->scheduled_at)->isSameDay(Lot::now()) && $slot->status === ParkingSlot::STATUS_AVAILABLE) {
            $slot->update(['status' => ParkingSlot::STATUS_RESERVED]);
        }
    }

    /** Release the slot to Available when no other booking for today still holds it. */
    public function releaseSlot(ParkingReservation $reservation): void
    {
        $slot = $reservation->slot_id ? ParkingSlot::query()->lockForUpdate()->find($reservation->slot_id) : null;

        if (! $slot || $slot->status !== ParkingSlot::STATUS_RESERVED) {
            return;
        }

        $stillHeld = ParkingReservation::query()
            ->where('slot_id', $slot->id)
            ->where('id', '!=', $reservation->id)
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [Lot::dayStart(), Lot::dayEnd()])
            ->exists();

        if (! $stillHeld) {
            $slot->update(['status' => ParkingSlot::STATUS_AVAILABLE]);
        }
    }

    /** Open session on a plate, if any. */
    public function openSessionForPlate(string $plate): ?ParkingSession
    {
        return ParkingSession::query()
            ->where('plate_number', Vehicle::normalizePlate($plate))
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->latest('entered_at')
            ->first();
    }

    private function newCode(): string
    {
        do {
            $code = 'RSV-';

            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (ParkingReservation::query()->where('code', $code)->exists());

        return $code;
    }
}
