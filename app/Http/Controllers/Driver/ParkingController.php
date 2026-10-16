<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ParkingRateSet;
use App\Models\ParkingReservation;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\DriverData;
use App\Services\GateService;
use App\Services\ReservationException;
use App\Services\ReservationService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Find parking (live board) and the three step reservation. */
class ParkingController extends Controller
{
    public function find(Request $request, DriverData $data, GateService $gate): View
    {
        $lot = $data->lot();
        $slots = ParkingSlot::query()->orderBy('zone')->orderBy('code')->get();
        $rate = ParkingRateSet::effectiveAt();

        $release = null;

        if ($lot['available'] === 0) {
            $session = $gate->pendingExits()->first();

            if ($session) {
                $release = ['slot' => $session->slot?->code ?? $session->slot_code, 'time' => Lot::time($gate->windowEnd($session))];
            }
        }

        return view('driver.app.find', [
            'lot' => $lot,
            'zones' => $slots->groupBy('zone'),
            'rate' => $rate,
            'release' => $release,
            'rateText' => $rate
                ? Lot::peso($rate->first_hour_fee).' first hour, '.Lot::peso($rate->additional_hour_fee).' each added hour'
                : 'Set by the Parking Manager',
        ]);
    }

    public function reserve(Request $request, ReservationService $reservations): View|RedirectResponse
    {
        $user = $request->user();
        $vehicles = $user->vehicles()->orderBy('created_at')->get();
        $step = max(1, min(3, (int) $request->query('step', 1)));
        $today = Lot::now()->startOfDay();

        $date = $request->query('other_date') ?: $request->query('date') ?: $today->format('Y-m-d');
        $time = (string) $request->query('time', '');
        $slotId = (int) $request->query('slot', 0);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || strtotime((string) $date) === false) {
            $date = $today->format('Y-m-d');
        }

        $base = [
            'step' => $step,
            'days' => collect(range(0, Lot::advanceDays()))->map(fn (int $i) => $today->copy()->addDays($i)),
            'date' => $date,
            'time' => $time,
            'slotId' => $slotId,
            'advanceDays' => Lot::advanceDays(),
            'latest' => $today->copy()->addDays(Lot::advanceDays()),
            'today' => $today,
            'nowTime' => Lot::now()->format('H:i'),
        ];

        if ($step === 1) {
            return view('driver.app.reserve', $base + ['vehicles' => $vehicles]);
        }

        $problem = $this->checkWhen($date, $time);

        if ($problem) {
            return redirect()->route('driver.reserve', array_filter(['step' => 1, 'date' => $date, 'time' => $time, 'slot' => $slotId ?: null]))
                ->withErrors(['when' => $problem['message']])
                ->with('modal', $problem['modal'] ?? null);
        }

        $at = Lot::parse($date, $time);
        $board = $reservations->boardAt($at);
        $vehicleId = (int) $request->query('vehicle', $vehicles->first()?->id ?? 0);

        if ($step === 2) {
            return view('driver.app.reserve', $base + [
                'at' => $at,
                'zones' => $board->groupBy('zone'),
                'vehicles' => $vehicles,
                'vehicleId' => $vehicleId,
                'noVehicle' => $vehicles->isEmpty(),
            ]);
        }

        $slot = $board->firstWhere('id', $slotId);
        $vehicle = $vehicles->firstWhere('id', $vehicleId);

        if ($vehicles->isEmpty() || ! $vehicle) {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'slot' => $slotId])
                ->with('modal', $vehicles->isEmpty() ? $this->addVehicleModal() : null)
                ->withErrors(['vehicle' => 'Choose a vehicle.']);
        }

        if (! $slot || $slot->state !== 'available') {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'vehicle' => $vehicleId])
                ->withErrors(['slot' => 'Choose a free slot.']);
        }

        return view('driver.app.reserve', $base + [
            'at' => $at,
            'slot' => $slot,
            'vehicle' => $vehicle,
            'vehicleId' => $vehicleId,
            'vehicles' => $vehicles,
            'policies' => [
                'Reservation is free. You pay the parking fee in cash at the booth when you leave.',
                'Your slot is held for '.Lot::graceMinutes().' minutes after your arrival time. After that the reservation expires.',
                'Check in opens '.Lot::earlyMinutes().' minutes before your arrival time.',
                'You can cancel any time before you check in.',
            ],
        ]);
    }

    public function store(Request $request, ReservationService $reservations): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'slot' => ['required', 'integer'],
            'vehicle' => ['required', 'integer'],
        ]);

        $problem = $this->checkWhen($data['date'], $data['time']);
        $back = ['date' => $data['date'], 'time' => $data['time'], 'slot' => $data['slot'], 'vehicle' => $data['vehicle']];

        if ($problem) {
            return redirect()->route('driver.reserve', ['step' => 1] + $back)->withErrors(['when' => $problem['message']])->with('modal', $problem['modal'] ?? null);
        }

        $vehicle = Vehicle::query()->where('user_id', $user->id)->find($data['vehicle']);
        $slot = ParkingSlot::query()->find($data['slot']);

        if (! $vehicle) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->with('modal', $this->addVehicleModal());
        }

        if (! $slot) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->withErrors(['slot' => 'Choose a free slot.']);
        }

        try {
            $reservation = $reservations->create($user, $vehicle, $slot, Lot::parse($data['date'], $data['time']), $request);
        } catch (ReservationException $e) {
            return $this->failed($e, $back, $slot);
        }

        return redirect()->route('driver.bookings.show', [$reservation, 'new' => 1]);
    }

    /** @return array{message:string,modal?:array<string,mixed>}|null */
    private function checkWhen(string $date, string $time): ?array
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['message' => 'Choose an arrival time.'];
        }

        $at = Lot::parse($date, $time);

        if ($at->lessThan(now()->startOfMinute())) {
            return ['message' => 'That time has already passed. Choose a time from now on.'];
        }

        $latest = Lot::now()->addDays(Lot::advanceDays());

        if (Lot::local($at)->startOfDay()->greaterThan($latest->copy()->startOfDay())) {
            return [
                'message' => 'This date is more than '.Lot::advanceDays().' days ahead.',
                'modal' => [
                    'title' => 'That date is too far ahead',
                    'body' => 'You can reserve up to '.Lot::advanceDays().' days ahead. The latest date is '.$latest->format('D, M j').'.',
                    'primary' => ['label' => 'Pick another date', 'close' => true],
                ],
            ];
        }

        return null;
    }

    private function failed(ReservationException $e, array $back, ParkingSlot $slot): RedirectResponse
    {
        $stepOne = ['step' => 1] + $back;
        $stepTwo = ['step' => 2] + $back;

        return match ($e->reason) {
            'slot_taken' => redirect()->route('driver.reserve', $stepTwo)->with('modal', [
                'title' => "Slot {$slot->code} was just taken",
                'body' => 'Another driver reserved it a moment ago. Choose another slot for '.Lot::date(Lot::parse($back['date'], $back['time'])).' at '.Lot::time(Lot::parse($back['date'], $back['time'])).'.',
                'primary' => ['label' => 'Choose another slot', 'close' => true],
            ]),
            'vehicle_busy' => redirect()->route('driver.reserve', $stepOne)->with('modal', [
                'title' => 'You already have a reservation',
                'body' => $e->getMessage(),
                'primary' => ['label' => 'View reservation', 'url' => $e->context ? route('driver.bookings.show', $e->context) : route('driver.bookings')],
                'secondary' => ['label' => 'Choose another time', 'close' => true],
            ]),
            default => redirect()->route('driver.reserve', $stepOne)->withErrors(['when' => $e->getMessage()]),
        };
    }

    private function addVehicleModal(): array
    {
        return [
            'title' => 'Add a vehicle first',
            'body' => 'You need a saved vehicle to reserve a slot. Add one, then come back.',
            'primary' => ['label' => 'Add a vehicle', 'url' => route('driver.vehicles.create')],
            'secondary' => ['label' => 'Not now', 'close' => true],
        ];
    }
}
