<?php

namespace App\Http\Controllers\Manager;

use App\Models\Incident;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DashboardController extends ManagerController
{
    public function index(): View
    {
        $statusTotals = ParkingSlot::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = collect(ParkingSlot::statuses())
            ->mapWithKeys(fn (string $label, string $status): array => [
                $status => (int) ($statusTotals[$status] ?? 0),
            ]);

        $activeZones = ParkingSlot::query()->select('zone')->distinct()->orderBy('zone')->pluck('zone');
        $attentionSlots = ParkingSlot::query()
            ->whereIn('status', [
                ParkingSlot::STATUS_PENDING_EXIT,
                ParkingSlot::STATUS_RESERVED,
                ParkingSlot::STATUS_OUT_OF_SERVICE,
            ])
            ->orderBy('code')
            ->limit(6)
            ->get();
        $salesToday = (float) ParkingPayment::query()->whereBetween('paid_at', [Lot::dayStart(), Lot::dayEnd()])->sum('total');
        $openIncidents = Incident::query()->whereIn('status', ['open', 'under_review'])->count();
        $analytics = $this->dashboardAnalytics();

        return $this->managerPage(
            'Dashboard',
            'dashboard',
            view('staff.manager.dashboard', [
                'statusCounts' => $statusCounts,
                'attentionSlots' => $attentionSlots,
                'totalSlots' => ParkingSlot::query()->count(),
                'zoneCount' => $activeZones->count(),
                'floorCount' => count(Lot::occupiedFloors($activeZones)),
                'lastUpdated' => ParkingSlot::query()->max('updated_at'),
                'salesToday' => $salesToday,
                'openIncidents' => $openIncidents,
                'analytics' => $analytics,
            ]),
        );
    }

    /**
     * Return the report snapshot used on the dashboard for the last seven lot-local days.
     *
     * @return array<string, mixed>
     */
    private function dashboardAnalytics(): array
    {
        $today = Lot::now()->startOfDay();
        $from = $today->copy()->subDays(6);
        [$start, $end] = [Lot::parse($from->toDateString()), Lot::parse($today->toDateString(), '23:59:59')];
        $sessions = ParkingSession::query()
            ->where('entered_at', '<=', $end)
            ->where(fn ($query) => $query->whereNull('exited_at')->orWhere('exited_at', '>=', $start))
            ->get(['entered_at', 'exited_at']);
        $capacity = ParkingSlot::query()->where('status', '!=', ParkingSlot::STATUS_OUT_OF_SERVICE)->count();
        $capacityMinutes = max(1, $capacity * $start->diffInMinutes($end));
        $occupiedMinutes = $sessions->sum(function (ParkingSession $session) use ($start, $end): int {
            $sessionStart = $session->entered_at->max($start);
            $sessionEnd = ($session->exited_at ?? Lot::now())->min($end);

            return max(0, (int) $sessionStart->diffInMinutes($sessionEnd, false));
        });
        $entered = $sessions->filter(fn (ParkingSession $session): bool => $session->entered_at->betweenIncluded($start, $end));
        $hourly = array_fill(8, 13, 0);

        foreach ($entered as $session) {
            $hour = (int) Lot::local($session->entered_at)->format('G');

            if (array_key_exists($hour, $hourly)) {
                $hourly[$hour]++;
            }
        }

        $reservationQuery = ParkingReservation::query()->whereBetween('scheduled_at', [$start, $end]);
        $reservationTotal = (clone $reservationQuery)->count();
        $revenue = ParkingPayment::query()->whereBetween('paid_at', [$start, $end])->sum('total');
        $averageStay = $sessions
            ->filter(fn (ParkingSession $session): bool => $session->exited_at !== null)
            ->average(fn (ParkingSession $session): float => $session->entered_at->diffInMinutes($session->exited_at));

        return [
            'from' => $from,
            'to' => $today,
            'revenue' => (float) $revenue,
            'sessionCount' => $sessions->count(),
            'averageOccupancy' => $capacity > 0 ? min(100, round($occupiedMinutes / $capacityMinutes * 100)) : 0,
            'averageStayMinutes' => $averageStay ?? 0,
            'peakHour' => max($hourly) > 0 ? sprintf('%02d:00', collect($hourly)->sortDesc()->keys()->first()) : null,
            'hourly' => $hourly,
            'reservationTotal' => $reservationTotal,
            'reservationsKept' => (clone $reservationQuery)->where('status', ParkingReservation::STATUS_CHECKED_IN)->count(),
            'incidents' => Incident::query()->whereBetween('reported_at', [$start, $end])->count(),
        ];
    }

    public function map(Request $request): View
    {
        $activeZones = ParkingSlot::query()->select('zone')->distinct()->orderBy('zone')->pluck('zone');
        $floors = Lot::occupiedFloors($activeZones);
        $selectedFloor = $this->selectedMapFloor($request, $floors);
        $zonesOnFloor = Lot::activeZonesOnFloor($selectedFloor, $activeZones);

        $slots = $zonesOnFloor === []
            ? collect()
            : ParkingSlot::query()
                ->whereIn('zone', $zonesOnFloor)
                ->orderBy('zone')
                ->orderBy('code')
                ->get();

        $zonePositions = $slots->isEmpty()
            ? collect()
            : ParkingLayoutZone::query()
                ->whereIn('zone', $slots->pluck('zone')->unique())
                ->get()
                ->keyBy('zone');

        return $this->managerPage(
            'Live parking map',
            'map',
            view('staff.manager.map', [
                'floors' => $floors,
                'selectedFloor' => $selectedFloor,
                'slotsByZone' => $slots->groupBy('zone'),
                'zonePositions' => $zonePositions,
                'zoneCount' => $slots->pluck('zone')->unique()->count(),
                'slotCount' => $slots->count(),
                'maxZonesPerFloor' => Lot::maxZonesPerFloor(),
            ]),
        );
    }

    public function authorizeLayoutEdit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The manager password is incorrect.',
            ]);
        }

        $request->session()->put('manager_layout_occupied_override', true);

        return response()->json(['authorized' => true]);
    }

    public function saveLayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'floor' => ['sometimes', 'integer', 'min:1', 'max:'.Lot::maxFloor()],
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.x' => ['required', 'numeric', 'between:0,1'],
            'positions.*.y' => ['required', 'numeric', 'between:0,1'],
            'zone_positions' => ['sometimes', 'array'],
            'zone_positions.*.zone' => ['required', 'string', 'max:100'],
            'zone_positions.*.x' => ['required', 'numeric', 'between:0,1'],
            'zone_positions.*.y' => ['required', 'numeric', 'between:0,1'],
        ]);

        $positions = collect($validated['positions'])->keyBy('id');
        $zonePositions = collect($validated['zone_positions'] ?? [])->keyBy('zone');

        DB::transaction(function () use ($positions, $zonePositions, $request): void {
            $slots = ParkingSlot::query()
                ->whereIn('id', $positions->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($slots->count() !== $positions->count()) {
                throw ValidationException::withMessages([
                    'positions' => 'The parking layout contains an invalid slot.',
                ]);
            }

            if ($slots->contains('status', ParkingSlot::STATUS_OCCUPIED)
                && ! $request->session()->get('manager_layout_occupied_override', false)) {
                throw ValidationException::withMessages([
                    'positions' => 'Confirm the manager password before editing a layout with occupied slots.',
                ]);
            }

            foreach ($positions as $id => $position) {
                $slots[$id]->update([
                    'layout_x' => $position['x'],
                    'layout_y' => $position['y'],
                ]);
            }

            $zones = ParkingSlot::query()
                ->whereIn('zone', $zonePositions->keys())
                ->distinct()
                ->pluck('zone');

            if ($zones->count() !== $zonePositions->count()) {
                throw ValidationException::withMessages([
                    'zone_positions' => 'The parking layout contains an invalid zone.',
                ]);
            }

            foreach ($zonePositions as $zone => $position) {
                ParkingLayoutZone::query()->updateOrCreate(
                    ['zone' => $zone],
                    ['label_x' => $position['x'], 'label_y' => $position['y']],
                );
            }
        });

        $request->session()->forget('manager_layout_occupied_override');

        $returnFloor = (int) ($validated['floor'] ?? 0);
        if ($returnFloor < 1) {
            $returnFloor = Lot::floorForZone(
                ParkingSlot::query()->whereIn('id', $positions->keys())->value('zone'),
            ) ?? 1;
        }

        return redirect()->route('manager.map', ['floor' => $returnFloor])->with('status', 'Parking layout was saved.');
    }

    /**
     * @param  array<int, int>  $floors
     */
    private function selectedMapFloor(Request $request, array $floors): int
    {
        $default = $floors[0] ?? 1;
        $requested = $request->integer('floor');

        return in_array($requested, $floors, true) ? $requested : $default;
    }
}
