<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Reads what one driver owns: vehicles, sessions, bookings and notices. */
class DriverData
{
    /** Plates of every vehicle the driver ever saved, removed ones included, so history keeps working. */
    public function plates(User $driver): array
    {
        return Vehicle::withTrashed()->where('user_id', $driver->id)->pluck('plate_number')->all();
    }

    /** Sessions that belong to the driver by account or by plate. */
    public function sessions(User $driver): Builder
    {
        $plates = $this->plates($driver);

        return ParkingSession::query()->where(function (Builder $query) use ($driver, $plates) {
            $query->where('driver_id', $driver->id);

            if ($plates !== []) {
                $query->orWhereIn('plate_number', $plates);
            }
        });
    }

    public function openSession(User $driver): ?ParkingSession
    {
        return $this->sessions($driver)
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->with(['slot', 'payment'])
            ->latest('entered_at')
            ->first();
    }

    public function recentCompletedSession(User $driver, int $withinMinutes = 120): ?ParkingSession
    {
        return $this->sessions($driver)
            ->where('status', ParkingSession::STATUS_COMPLETED)
            ->where('exited_at', '>=', now()->subMinutes($withinMinutes))
            ->with(['slot', 'payment.attendant', 'exitApprover'])
            ->latest('exited_at')
            ->first();
    }

    /** The next booking that is still Reserved. */
    public function upcomingReservation(User $driver): ?ParkingReservation
    {
        return ParkingReservation::query()
            ->where('driver_id', $driver->id)
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->with(['slot', 'vehicle'])
            ->orderBy('scheduled_at')
            ->first();
    }

    /** @return Collection<int, ParkingPayment> */
    public function payments(User $driver): Collection
    {
        $ids = $this->sessions($driver)->where('status', ParkingSession::STATUS_COMPLETED)->pluck('id');

        return ParkingPayment::query()->whereIn('session_id', $ids)->with('session.slot')->latest('paid_at')->get();
    }

    public function unread(User $driver): int
    {
        return Notice::query()->forDriver($driver)->unread()->count();
    }

    /** @return array{available:int,total:int,updated:string} */
    public function lot(): array
    {
        $counts = ParkingSlot::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'available' => (int) ($counts[ParkingSlot::STATUS_AVAILABLE] ?? 0),
            'total' => (int) $counts->sum(),
            'updated' => Lot::time(now()),
        ];
    }

    /** A vehicle is in use while a booking or an open session carries its plate. */
    public function vehicleUse(Vehicle $vehicle): ?array
    {
        $reservation = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where(fn ($q) => $q->where('vehicle_id', $vehicle->id)->orWhere('plate_number', $vehicle->plate_number))
            ->with('slot')
            ->first();

        if ($reservation) {
            return ['type' => 'reservation', 'record' => $reservation];
        }

        $session = ParkingSession::query()
            ->where('plate_number', $vehicle->plate_number)
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->first();

        return $session ? ['type' => 'session', 'record' => $session] : null;
    }
}
