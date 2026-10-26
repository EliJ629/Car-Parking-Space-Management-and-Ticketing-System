<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ParkingLayoutZone;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotController extends Controller
{
    /** BA-51 to BA-56: Parking slots board and manual correction */
    public function index(Request $request): View
    {
        $slots = ParkingSlot::query()
            ->with([
                'sessions' => fn ($q) => $q->whereIn('status', ParkingSession::OPEN_STATUSES)->latest('id'),
                'reservations' => fn ($q) => $q->where('status', 'booked')->latest('id'),
            ])
            ->orderBy('zone')
            ->orderBy('code')
            ->get();

        $activeZones = $slots->pluck('zone')->unique()->values();
        $floors = Lot::occupiedFloors($activeZones);
        $selectedFloor = in_array((int) $request->query('floor'), $floors, true)
            ? (int) $request->query('floor')
            : ($floors[0] ?? 1);
        $zonesOnFloor = Lot::activeZonesOnFloor($selectedFloor, $activeZones);

        $floorSlots = ($zonesOnFloor !== [] && count($floors) > 0)
            ? $slots->whereIn('zone', $zonesOnFloor)
            : $slots;

        $zonePositions = ParkingLayoutZone::query()
            ->whereIn('zone', $activeZones)
            ->get()
            ->keyBy('zone');

        $counts = [
            'available' => $slots->where('status', ParkingSlot::STATUS_AVAILABLE)->count(),
            'reserved' => $slots->where('status', ParkingSlot::STATUS_RESERVED)->count(),
            'occupied' => $slots->where('status', ParkingSlot::STATUS_OCCUPIED)->count(),
            'pending_exit' => $slots->where('status', ParkingSlot::STATUS_PENDING_EXIT)->count(),
            'out_of_service' => $slots->where('status', ParkingSlot::STATUS_OUT_OF_SERVICE)->count(),
        ];

        // Recent manual changes
        $recentChanges = ActivityLog::query()
            ->whereIn('action', ['slot_corrected', 'slot_status_changed', 'slot_service_toggled'])
            ->with('user')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('staff.attendant.slots.index', [
            'slots' => $slots,
            'floorSlots' => $floorSlots,
            'floors' => $floors,
            'selectedFloor' => $selectedFloor,
            'zonePositions' => $zonePositions,
            'counts' => $counts,
            'recentChanges' => $recentChanges,
        ]);
    }

    /** BA-53/54/55: Perform manual slot correction */
    public function correctStatus(Request $request, ParkingSlot $slot): RedirectResponse
    {
        $validated = $request->validate([
            'new_status' => ['required', 'string', 'in:available,occupied,out_of_service'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ], [
            'reason.required' => 'A reason is required.',
            'reason.min' => 'The reason must be at least 10 characters.',
        ]);

        $attendant = $request->user();
        $newStatus = $validated['new_status'];
        $reason = trim($validated['reason']);
        $oldStatus = $slot->status;

        // BR-11 & BA-55: Cannot mark Available while vehicle is occupying it
        if ($newStatus === ParkingSlot::STATUS_AVAILABLE) {
            $activeSession = $slot->currentSession();

            if ($activeSession) {
                return back()->with('error_modal', [
                    'type' => 'cannot_mark_available',
                    'slot' => $slot->code,
                    'plate' => Lot::plate($activeSession->plate_number),
                    'ticket' => $activeSession->ticket_number,
                    'session_id' => $activeSession->id,
                ]);
            }

            if ($booking = $slot->nextOpenBooking()) {
                return back()->with('error_modal', [
                    'type' => 'cannot_mark_available',
                    'slot' => $slot->code,
                    'message' => "Slot {$slot->code} has active reservation {$booking->code}. Cancel it first.",
                ]);
            }
        }

        // Apply update
        $slot->update([
            'status' => $newStatus,
            'out_of_service_reason' => $newStatus === ParkingSlot::STATUS_OUT_OF_SERVICE ? $reason : null,
        ]);

        ActivityLog::record('slot_corrected', $attendant, $request, $slot->code, 'attendant', [
            'slot' => $slot->code,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        $statusName = $slot->statusLabel();

        return redirect()->route('attendant.slots.index')->with('toast', [
            "{$slot->code} set to {$statusName}",
            'The reason was saved to the activity log.',
        ]);
    }
}
