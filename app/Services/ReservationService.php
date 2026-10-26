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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reservations (process document 6.3): list the slots free at a time, create,
 * cancel, hold today's slots and expire what was not checked in.
 */
class ReservationService
{
    /** A reservation can be made for any time from now until this many hours ahead. */
    public const WINDOW_HOURS = 24;

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

        $around = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->whereBetween('scheduled_at', [$at->copy()->subMinutes($window - 1), $at->copy()->addMinutes($window - 1)])
            ->get(['slot_id', 'approval_status', 'scheduled_at']);

        // A PWD request that the booth attendant has not approved yet is not a reservation: it keeps the slot from being
        // booked twice, but it is shown as awaiting approval, never as Reserved.
        $held = $around->where('approval_status', '!=', ParkingReservation::APPROVAL_PENDING)->pluck('slot_id')->filter()->all();
        $awaiting = $around->where('approval_status', ParkingReservation::APPROVAL_PENDING)->pluck('slot_id')->filter()->all();

        $today = Lot::local($at)->isSameDay(Lot::now());

        $times = $around->whereNotNull('slot_id')->sortBy('scheduled_at')->groupBy('slot_id')->map(fn ($rows) => $rows->first()->scheduled_at);

        return $slots->each(function (ParkingSlot $slot) use ($held, $awaiting, $today, $times): void {
            $slot->setAttribute('free_at_time', true);
            $state = 'available';

            if ($slot->status === ParkingSlot::STATUS_OUT_OF_SERVICE) {
                $state = 'out_of_service';
            } elseif (in_array($slot->id, $held, true)) {
                $state = 'reserved';
            } elseif (in_array($slot->id, $awaiting, true)) {
                $state = 'awaiting_approval';
            } elseif ($today && $slot->status === ParkingSlot::STATUS_OCCUPIED) {
                $state = 'occupied';
            } elseif ($today && $slot->status === ParkingSlot::STATUS_PENDING_EXIT) {
                $state = 'pending_exit';
            }

            $slot->setAttribute('state', $state);
            $slot->setAttribute('free_at_time', $state === 'available');
            $slot->setAttribute('booked_label', in_array($state, ['reserved', 'awaiting_approval'], true) && $times->has($slot->id) ? \App\Support\SlotLayout::tileTime($times->get($slot->id)) : null);
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
        if ($suspension = app(NoShowService::class)->activeSuspension($driver)) {
            return ['kind' => 'suspended', 'reservation' => null, 'session' => null, 'slot' => '', 'message' => app(NoShowService::class)->message($suspension),
                'until' => $suspension->ends_at];
        }

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

        // Three missed reservations pause reserving (1 week, then longer). Walking in and parking are not affected.
        if ($suspension = app(NoShowService::class)->activeSuspension($driver)) {
            throw new ReservationException('suspended', app(NoShowService::class)->message($suspension));
        }

        // Motorcycles park in motorcycle zones. Cars, SUVs, vans, pickups and tricycles park in car zones.
        if ($message = $slot->wrongZoneMessage($vehicle->vehicle_type)) {
            throw new ReservationException('wrong_zone', $message);
        }

        if ($needsId && ! $idPhoto) {
            throw new ReservationException('id_required', 'A PWD slot needs a photo of your PWD ID. Upload it to continue.');
        }

        $latest = $now->copy()->startOfMinute()->addHours(self::WINDOW_HOURS);

        if ($at->lessThan($now->copy()->startOfMinute())) {
            throw new ReservationException('past', 'Choose a time that is not in the past.');
        }

        if ($at->greaterThan($latest)) {
            throw new ReservationException('too_far', 'You can reserve only within the next '.self::WINDOW_HOURS.' hours, up to '.Lot::when($latest).'.');
        }

        if ($vehicle->user_id !== $driver->id) {
            throw new ReservationException('no_vehicle', 'Choose one of your saved vehicles.');
        }

        if (! $vehicle->hasPlate()) {
            throw new ReservationException('no_plate', 'This vehicle has no plate number yet. Add it under My vehicles once the LTO has issued it, then reserve.');
        }

        if (app(BanService::class)->isBanned($vehicle->plate_number)) {
            throw new ReservationException('vehicle_banned', VehicleBanned::MESSAGE);
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
                // Only a PWD slot waits for the booth attendant, who checks the ID photo. Any other slot is
                // confirmed at once, so there is nothing for the attendant to approve.
                'approval_status' => $needsId ? ParkingReservation::APPROVAL_PENDING : ParkingReservation::APPROVAL_APPROVED,
                'approved_at' => $needsId ? null : now(),
                'requires_id' => $needsId,
            ]);

            $this->holdSlotIfDue($reservation, $fresh);

            return $reservation;
        });

        if ($needsId && $idPhoto) {
            $path = $idPhoto->storeAs(self::ID_DIR, $reservation->code.'-'.bin2hex(random_bytes(6)).'.'.$this->photoExtension($idPhoto), self::ID_DISK);
            $reservation->update(['id_photo_path' => $path]);
        }

        $reservation->load(['slot', 'driver']);
        if ($needsId) {
            $this->notices->reservationSubmitted($reservation);
            $this->notices->staffReservationPending($reservation);
        } else {
            $this->notices->reservationConfirmed($reservation);
        }
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

            // Only a PWD reservation is ever approved by hand. Others are confirmed when they are made.
            if (! $fresh || ! $fresh->requires_id || ! $fresh->isAwaitingApproval()) {
                return false;
            }

            $fresh->update([
                'approval_status' => ParkingReservation::APPROVAL_APPROVED,
                'approved_by' => $attendant->id,
                'approved_at' => now(),
            ]);

            // Only now, when it is approved, can the slot show as Reserved (and only when the hold window has started).
            if ($fresh->slot_id && ($slot = ParkingSlot::query()->lockForUpdate()->find($fresh->slot_id))) {
                $this->holdSlotIfDue($fresh, $slot);
            }

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
                app(NoShowService::class)->recordNoShow($expired);
                ActivityLog::record('reservation_expired', null, null, $expired->code, 'system', [
                    'slot' => $expired->slot_code, 'scheduled_at' => $expired->scheduled_at->toIso8601String(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * A slot shows Reserved only while an approved booking is about to arrive or is within its grace time (the hold
     * window). A booking made for later today or tomorrow leaves the live board alone, so a slot is never shown as
     * taken hours before anyone arrives. Run by the scheduler and by the screens' sweep.
     */
    public function holdTodaysSlots(): int
    {
        $count = 0;

        ParkingReservation::query()
            ->holdingNow()
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

    /** Show Reserved on the live board when this approved booking's hold window has started. */
    private function holdSlotIfDue(ParkingReservation $reservation, ParkingSlot $slot): void
    {
        if ($reservation->approval_status !== ParkingReservation::APPROVAL_APPROVED || $slot->status !== ParkingSlot::STATUS_AVAILABLE) {
            return;
        }

        if (ParkingReservation::query()->whereKey($reservation->id)->holdingNow()->exists()) {
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
            ->holdingNow()
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
