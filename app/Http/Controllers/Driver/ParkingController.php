<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingRateSet;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\DriverData;
use App\Services\GateService;
use App\Services\ReservationException;
use App\Services\ReservationService;
use App\Support\Lot;
use App\Support\PwdPhoto;
use App\Support\SlotLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Find parking (live board) and the three step reservation. */
class ParkingController extends Controller
{
    public function find(Request $request, DriverData $data, GateService $gate): View
    {
        $lot = $data->lot();
        $map = $data->liveMap($request->integer('floor'));
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
            'floors' => $map['floors'],
            'selectedFloor' => $map['selectedFloor'],
            'zones' => $map['zones'],
            'zonePositions' => $map['zonePositions'],
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

        $time = (string) $request->query('time', '');
        $slotId = (int) $request->query('slot', 0);

        // The driver picks only a time of day. The date is worked out here: the next time the clock reads that time,
        // which is always inside the next 24 hours. Later steps carry the date that was worked out.
        $date = (string) $request->query('date', '');

        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $date = $this->dateForTime($time);
        }

        $base = [
            'step' => $step,
            'date' => $date,
            'time' => $time,
            'slotId' => $slotId,
            'floor' => null,
            'windowHours' => ReservationService::WINDOW_HOURS,
            'today' => $today,
            'tomorrow' => $today->copy()->addDay(),
            'nowTime' => Lot::now()->format('H:i'),
            'windowEnd' => Lot::now()->startOfMinute()->addHours(ReservationService::WINDOW_HOURS),
        ];

        if ($blocking = $reservations->blockingFor($user)) {
            return view('driver.app.reserve', array_merge($base, ['step' => 1, 'vehicles' => $vehicles, 'blocking' => $blocking]));
        }

        if ($step === 1) {
            return view('driver.app.reserve', $base + ['vehicles' => $vehicles, 'blocking' => null]);
        }

        $problem = $this->checkWhen($date, $time);

        if ($problem) {
            return redirect()->route('driver.reserve', array_filter(['step' => 1, 'time' => $time, 'slot' => $slotId ?: null]))
                ->withErrors(['when' => $problem['message']])
                ->with('modal', $problem['modal'] ?? null);
        }

        $at = Lot::parse($date, $time);
        // The booking time on a reserved square is the one around the chosen time (set by boardAt); only the parked time is added here.
        $board = SlotLayout::decorate($reservations->boardAt($at), false);
        $activeZones = $board->pluck('zone')->unique()->values();
        $floors = Lot::occupiedFloors($activeZones);
        $selectedFloor = $this->selectedReservationFloor($request, $floors);
        $zonesOnFloor = Lot::activeZonesOnFloor($selectedFloor, $activeZones);
        $board = $board->filter(fn (ParkingSlot $slot): bool => in_array($slot->zone, $zonesOnFloor, true))->values();
        $base['floor'] = $selectedFloor;
        // No vehicle is chosen until the driver picks one: the vehicle decides which zones can be used.
        $vehicleId = (int) $request->query('vehicle', 0);

        if ($step === 2) {
            $zonePositions = ParkingLayoutZone::query()
                ->whereIn('zone', $board->pluck('zone')->unique())
                ->get()
                ->keyBy('zone');

            return view('driver.app.reserve', $base + [
                'at' => $at,
                'zones' => $board->groupBy('zone'),
                'zonePositions' => $zonePositions,
                'vehicles' => $vehicles,
                'vehicleId' => $vehicleId,
                'noVehicle' => $vehicles->isEmpty(),
                'floors' => $floors,
                'selectedFloor' => $selectedFloor,
            ]);
        }

        $slot = $board->firstWhere('id', $slotId);
        $vehicle = $vehicles->firstWhere('id', $vehicleId);

        if ($vehicles->isEmpty() || ! $vehicle) {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'slot' => $slotId])
                ->with('modal', $vehicles->isEmpty() ? $this->addVehicleModal() : null)
                ->withErrors(['vehicle' => 'Choose a vehicle.']);
        }

        if (! $vehicle->hasPlate()) {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'slot' => $slotId])
                ->withErrors(['vehicle' => 'This vehicle has no plate number yet. Add it under My vehicles once the LTO has issued it, then reserve.']);
        }

        if (! $slot || $slot->state !== 'available') {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'vehicle' => $vehicleId])
                ->withErrors(['slot' => 'Choose a free slot.']);
        }

        if ($zoneProblem = $slot->wrongZoneMessage($vehicle->vehicle_type)) {
            return redirect()->route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'vehicle' => $vehicleId])
                ->withErrors(['slot' => $zoneProblem]);
        }

        return view('driver.app.reserve', $base + [
            'at' => $at,
            'slot' => $slot,
            'vehicle' => $vehicle,
            'vehicleId' => $vehicleId,
            'vehicles' => $vehicles,
            'opens' => $at->copy()->subMinutes(Lot::earlyMinutes()),
            'holdEnds' => $at->copy()->addMinutes(Lot::graceMinutes()),
            'photoLimitBytes' => PwdPhoto::limitBytes(),
            'photoLimitLabel' => PwdPhoto::limitLabel(),
            'photoTooLarge' => $request->query('photo') === 'too_large',
            'floors' => $floors,
            'selectedFloor' => $selectedFloor,
            'policies' => [
                'Reservation is free. You pay the parking fee in cash at the booth when you leave.',
                $slot->type === ParkingSlot::TYPE_PWD
                    ? 'The booth attendant checks your PWD ID and approves your request first. Your QR code appears only after approval.'
                    : 'Your reservation is confirmed right away. Your QR code appears as soon as you reserve.',
                'You can cancel any time before you check in.',
            ],
        ]);
    }

    public function store(Request $request, ReservationService $reservations): RedirectResponse
    {
        $user = $request->user();
        // A reservation is one day and one arrival time. Anything that asks for a range (an end date, several days,
        // a list of dates) is refused here, whatever the form sent, so the rule does not depend on the screen.
        $oneDay = 'A reservation is for one day only. Book each day on its own.';
        $data = $request->validate(array_merge(array_fill_keys(self::RANGE_FIELDS, ['prohibited']), [
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'time' => ['required', 'string', 'date_format:H:i'],
            'slot' => ['required', 'integer'],
            'vehicle' => ['required', 'integer'],
            'floor' => ['sometimes', 'integer', 'min:1', 'max:'.Lot::maxFloor()],
            'understand' => ['accepted'],
        ]), array_merge(array_fill_keys(array_map(fn (string $f) => "{$f}.prohibited", self::RANGE_FIELDS), $oneDay), [
            'date.date_format' => 'Choose a valid date.',
            'time.date_format' => 'Choose a valid arrival time.',
            'understand.accepted' => 'Tick the box to confirm that you will arrive on time. The slot is released when the grace period ends.',
        ]));

        $problem = $this->checkWhen($data['date'], $data['time']);
        $back = ['date' => $data['date'], 'time' => $data['time'], 'slot' => $data['slot'], 'vehicle' => $data['vehicle']];
        if (isset($data['floor'])) {
            $back['floor'] = $data['floor'];
        }
        $slot = ParkingSlot::query()->find($data['slot']);
        $idPhoto = null;

        // The vehicle comes first: its type decides which zones it may use.
        $chosen = Vehicle::query()->where('user_id', $user->id)->find($data['vehicle']);

        if ($slot && $chosen && ($zoneProblem = $slot->wrongZoneMessage($chosen->vehicle_type))) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->withErrors(['slot' => $zoneProblem]);
        }

        // A PWD slot needs a photo of the PWD ID, so the booth attendant can check it before approving.
        if ($slot?->type === ParkingSlot::TYPE_PWD) {
            $request->validate([
                'id_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.PwdPhoto::limitKb()],
            ], [
                'id_photo.required' => 'Upload a clear photo of your PWD ID. A PWD slot cannot be reserved without it.',
                'id_photo.image' => 'The ID must be a photo (JPG, PNG or WEBP).',
                'id_photo.mimes' => 'The ID must be a JPG, PNG or WEBP photo.',
                'id_photo.max' => PwdPhoto::tooLargeMessage(),
                // PHP refuses a file over its own upload limit before Laravel sees it, and reports it as "uploaded".
                'id_photo.uploaded' => PwdPhoto::tooLargeMessage(),
            ]);
            $idPhoto = $request->file('id_photo');
        }

        if ($problem) {
            return redirect()->route('driver.reserve', ['step' => 1] + $back)->withErrors(['when' => $problem['message']])->with('modal', $problem['modal'] ?? null);
        }

        $vehicle = Vehicle::query()->where('user_id', $user->id)->find($data['vehicle']);

        if (! $vehicle) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->with('modal', $this->addVehicleModal());
        }

        if (! $slot) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->withErrors(['slot' => 'Choose a free slot.']);
        }

        if ($request->filled('floor') && Lot::floorForZone($slot->zone) !== (int) $data['floor']) {
            return redirect()->route('driver.reserve', ['step' => 2] + $back)->withErrors(['slot' => 'Choose a slot on the selected floor.']);
        }

        try {
            $reservation = $reservations->create($user, $vehicle, $slot, Lot::parse($data['date'], $data['time']), $request, $idPhoto);
        } catch (ReservationException $e) {
            return $this->failed($e, $back, $slot);
        }

        return redirect()->route('driver.bookings.show', [$reservation, 'new' => 1]);
    }

    /** The date of the next time the clock reads $time (today if it has not passed yet, otherwise tomorrow). */
    private function dateForTime(string $time): string
    {
        $today = Lot::now()->startOfDay();

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $today->format('Y-m-d');
        }

        return Lot::parse($today->format('Y-m-d'), $time)->lessThan(now()->startOfMinute())
            ? $today->copy()->addDay()->format('Y-m-d')
            : $today->format('Y-m-d');
    }

    /** Fields a form could send to ask for more than one day. They are refused on the server. */
    private const RANGE_FIELDS = ['end_date', 'end_time', 'date_to', 'to_date', 'until', 'check_out', 'days', 'nights', 'dates', 'duration_days', 'repeat', 'recurring'];

    /** @return array{message:string,modal?:array<string,mixed>}|null */
    private function checkWhen(string $date, string $time): ?array
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['message' => 'Choose an arrival time.'];
        }

        // 2026-02-31 and 25:99 look right but are not real; the date and time must read back exactly as typed.
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $clock = \DateTimeImmutable::createFromFormat('!H:i', $time);

        if (! $day || $day->format('Y-m-d') !== $date) {
            return ['message' => 'Choose a valid date.'];
        }

        if (! $clock || $clock->format('H:i') !== $time) {
            return ['message' => 'Choose a valid arrival time.'];
        }

        $at = Lot::parse($date, $time);

        if ($at->lessThan(now()->startOfMinute())) {
            return ['message' => 'That time has already passed. Choose a time from now on.'];
        }

        $latest = Lot::now()->startOfMinute()->addHours(ReservationService::WINDOW_HOURS);

        if ($at->greaterThan($latest)) {
            return [
                'message' => 'You can reserve only within the next '.ReservationService::WINDOW_HOURS.' hours.',
                'modal' => [
                    'title' => 'That time is too far ahead',
                    'body' => 'A reservation can be made for any time from now until '.Lot::when($latest).'.',
                    'primary' => ['label' => 'Choose another time', 'close' => true],
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
            'wrong_zone' => redirect()->route('driver.reserve', $stepTwo)->withErrors(['slot' => $e->getMessage()]),
            'no_plate' => redirect()->route('driver.reserve', $stepTwo)->withErrors(['vehicle' => $e->getMessage()]),
            'suspended' => redirect()->route('driver.reserve', $stepOne)->with('modal', [
                'title' => 'Reservations are paused',
                'body' => $e->getMessage(),
                'primary' => ['label' => 'OK', 'close' => true],
                'tone' => 'stop',
            ]),
            'id_required' => redirect()->route('driver.reserve', ['step' => 3] + $back)->withErrors(['id_photo' => $e->getMessage()]),
            'vehicle_banned' => redirect()->route('driver.reserve', $stepOne)->with('modal', [
                'title' => 'This vehicle cannot be reserved',
                'body' => $e->getMessage(),
                'primary' => ['label' => 'OK', 'close' => true],
                'tone' => 'stop',
            ]),
            'driver_busy' => redirect()->route('driver.reserve', $stepOne)->with('modal', $this->busyModal($e)),
            'vehicle_busy' => redirect()->route('driver.reserve', $stepOne)->with('modal', [
                'title' => 'You already have a reservation',
                'body' => $e->getMessage(),
                'primary' => ['label' => 'View reservation', 'url' => $e->context ? route('driver.bookings.show', $e->context) : route('driver.bookings')],
                'secondary' => ['label' => 'Choose another time', 'close' => true],
            ]),
            default => redirect()->route('driver.reserve', $stepOne)->withErrors(['when' => $e->getMessage()]),
        };
    }

    /** The pop-up for a driver who still has a reservation or a parked car. */
    private function busyModal(ReservationException $e): array
    {
        $parked = $e->context instanceof ParkingSession;
        $checkedIn = $e->context instanceof ParkingReservation && $e->context->status === ParkingReservation::STATUS_CHECKED_IN;

        return [
            'title' => $parked ? 'Your car is still parked' : ($checkedIn ? 'You are already checked in' : 'You already have a reservation'),
            'body' => $e->getMessage(),
            'primary' => $parked
                ? ['label' => 'View my ticket', 'url' => route('driver.session')]
                : ['label' => 'View reservation', 'url' => $e->context ? route('driver.bookings.show', $e->context) : route('driver.bookings')],
            'secondary' => ['label' => 'Close', 'close' => true],
        ];
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

    /**
     * @param  array<int, int>  $floors
     */
    private function selectedReservationFloor(Request $request, array $floors): int
    {
        $default = $floors[0] ?? 1;
        $requested = $request->integer('floor');

        return in_array($requested, $floors, true) ? $requested : $default;
    }
}
