<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\ParkingSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParkingSlotController extends ManagerController
{
    private const SERVICE_REASONS = [
        'Maintenance',
        'Repair',
        'Safety',
        'Reserved for operations',
        'Other',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ParkingSlot::statuses()))],
            'zone' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $slots = ParkingSlot::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['zone'] ?? null, fn ($query, string $zone) => $query->where('zone', $zone))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->orderBy('zone')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return $this->managerPage(
            'Parking slots',
            'slots',
            view('staff.manager.slots.index', [
                'slots' => $slots,
                'zones' => ParkingSlot::query()->select('zone')->distinct()->orderBy('zone')->pluck('zone'),
                'statuses' => ParkingSlot::statuses(),
                'filters' => $filters,
            ]),
        );
    }

    public function create(): View
    {
        return $this->managerPage(
            'Add parking slot',
            'slots',
            view('staff.manager.slots.create', [
                'zones' => $this->zoneOptions(),
                'types' => [ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD],
            ]),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'zone' => trim((string) $request->input('zone')),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z]-\d{2}$/', 'unique:parking_slots,code'],
            'zone' => ['required', 'string', 'max:40'],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = ParkingSlot::create($validated + [
            'status' => ParkingSlot::STATUS_AVAILABLE,
        ]);

        ActivityLog::record(
            'slot_created',
            $request->user(),
            $request,
            $slot->code,
            'manager',
            ['slot' => $slot->only(['code', 'zone', 'type', 'status', 'notes'])],
        );

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} was added as available.");
    }

    public function edit(ParkingSlot $slot): View
    {
        return $this->managerPage(
            "Edit {$slot->code}",
            'slots',
            view('staff.manager.slots.edit', [
                'slot' => $slot,
                'zones' => $this->zoneOptions($slot->zone),
                'types' => [ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD],
            ]),
        );
    }

    public function update(Request $request, ParkingSlot $slot): RedirectResponse
    {
        $request->merge(['zone' => trim((string) $request->input('zone'))]);

        $validated = $request->validate([
            'zone' => ['required', 'string', 'max:40'],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $slot->only(['code', 'zone', 'type', 'status', 'notes']);
        $slot->update($validated);

        ActivityLog::record(
            'slot_updated',
            $request->user(),
            $request,
            $slot->code,
            'manager',
            ['before' => $before, 'after' => $slot->only(array_keys($before))],
        );

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} was updated.");
    }

    public function editServiceStatus(ParkingSlot $slot): View|RedirectResponse
    {
        if (! $slot->canBeTakenOutOfService()) {
            return redirect()->route('manager.slots.index')
                ->withErrors(['slot' => "{$slot->code} must be available before it can be taken out of service."]);
        }

        return $this->managerPage(
            "Set {$slot->code} out of service",
            'slots',
            view('staff.manager.slots.service-status', [
                'slot' => $slot,
                'reasons' => self::SERVICE_REASONS,
            ]),
        );
    }

    public function updateServiceStatus(Request $request, ParkingSlot $slot): RedirectResponse
    {
        if ($slot->status === ParkingSlot::STATUS_OUT_OF_SERVICE) {
            $before = $slot->only(['status', 'out_of_service_reason', 'notes']);
            $slot->update([
                'status' => ParkingSlot::STATUS_AVAILABLE,
                'out_of_service_reason' => null,
            ]);

            ActivityLog::record(
                'slot_returned_to_service',
                $request->user(),
                $request,
                $slot->code,
                'manager',
                ['before' => $before, 'after' => $slot->only(['status', 'out_of_service_reason', 'notes'])],
            );

            return redirect()->route('manager.slots.index')->with('status', "{$slot->code} is back in service.");
        }

        if (! $slot->canBeTakenOutOfService()) {
            return redirect()->route('manager.slots.index')
                ->withErrors(['slot' => "{$slot->code} must be available before it can be taken out of service."]);
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::in(self::SERVICE_REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $slot->only(['status', 'out_of_service_reason', 'notes']);
        $slot->update([
            'status' => ParkingSlot::STATUS_OUT_OF_SERVICE,
            'out_of_service_reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLog::record(
            'slot_taken_out_of_service',
            $request->user(),
            $request,
            $slot->code,
            'manager',
            ['before' => $before, 'after' => $slot->only(['status', 'out_of_service_reason', 'notes'])],
        );

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} is out of service.");
    }

    public function destroy(Request $request, ParkingSlot $slot): RedirectResponse
    {
        if (! $slot->canBeRemoved()) {
            return redirect()->route('manager.slots.index')
                ->withErrors(['slot' => "{$slot->code} cannot be removed while it is occupied, reserved, or awaiting exit."]);
        }

        $details = $slot->only(['code', 'zone', 'type', 'status', 'notes', 'out_of_service_reason']);

        ActivityLog::record(
            'slot_removed',
            $request->user(),
            $request,
            $slot->code,
            'manager',
            ['slot' => $details],
        );

        $slot->delete();

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} was removed.");
    }

    /**
     * @return array<int, string>
     */
    private function zoneOptions(?string $currentZone = null): array
    {
        $zones = collect(range('A', 'Z'))->map(fn (string $letter): string => "Zone {$letter}");

        if ($currentZone !== null && ! $zones->contains($currentZone)) {
            $zones->push($currentZone);
        }

        return $zones->sort()->values()->all();
    }
}
