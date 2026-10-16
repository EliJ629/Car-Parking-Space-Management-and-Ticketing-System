<?php

namespace App\Http\Controllers\Manager;

use App\Models\ParkingSlot;
use Illuminate\Contracts\View\View;

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

        $attentionSlots = $slots
            ->whereIn('status', [
                ParkingSlot::STATUS_PENDING_EXIT,
                ParkingSlot::STATUS_RESERVED,
                ParkingSlot::STATUS_OUT_OF_SERVICE,
            ])
            ->take(6);

        return $this->managerPage(
            'Dashboard',
            'dashboard',
            view('staff.manager.dashboard', [
                'statusCounts' => $statusCounts,
                'slotsByZone' => $slots->groupBy('zone'),
                'attentionSlots' => $attentionSlots,
                'totalSlots' => $slots->count(),
                'zoneCount' => $slots->pluck('zone')->unique()->count(),
                'lastUpdated' => $slots->max('updated_at'),
            ]),
        );
    }
}
