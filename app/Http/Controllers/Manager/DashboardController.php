<?php

namespace App\Http\Controllers\Manager;

use App\Models\Incident;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingPayment;
use App\Models\ParkingSlot;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $slots = ParkingSlot::query()
            ->orderBy('zone')
            ->orderBy('code')
            ->get();
        $zonePositions = ParkingLayoutZone::query()
            ->whereIn('zone', $slots->pluck('zone')->unique())
            ->get()
            ->keyBy('zone');

        $attentionSlots = $slots
            ->whereIn('status', [
                ParkingSlot::STATUS_PENDING_EXIT,
                ParkingSlot::STATUS_RESERVED,
                ParkingSlot::STATUS_OUT_OF_SERVICE,
            ])
            ->take(6);
        $salesToday = (float) ParkingPayment::query()->whereBetween('paid_at', [Lot::dayStart(), Lot::dayEnd()])->sum('total');
        $openIncidents = Incident::query()->whereIn('status', ['open', 'under_review'])->count();

        return $this->managerPage(
            'Dashboard',
            'dashboard',
            view('staff.manager.dashboard', [
                'statusCounts' => $statusCounts,
                'slotsByZone' => $slots->groupBy('zone'),
                'zonePositions' => $zonePositions,
                'attentionSlots' => $attentionSlots,
                'totalSlots' => $slots->count(),
                'zoneCount' => $slots->pluck('zone')->unique()->count(),
                'lastUpdated' => $slots->max('updated_at'),
                'salesToday' => $salesToday,
                'openIncidents' => $openIncidents,
            ]),
        );
    }

    public function saveLayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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

        DB::transaction(function () use ($positions, $zonePositions): void {
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

        return redirect()->route('manager.dashboard')->with('status', 'Parking layout was saved.');
    }
}
