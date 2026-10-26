<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'notes' => $this->cleanNotes($request->input('notes')),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z]-\d{2}$/', 'unique:parking_slots,code'],
            'zone' => ['required', 'string', Rule::in($this->zoneOptions())],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

        $slot = DB::transaction(function () use ($validated, $request): ParkingSlot {
            $this->lockManagerSlotMutations();
            $this->assertZoneLimits($validated['zone']);

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

            return $slot;
        });

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
        $request->merge([
            'zone' => trim((string) $request->input('zone')),
            'notes' => $this->cleanNotes($request->input('notes')),
        ]);

        $validated = $request->validate([
            'zone' => ['required', 'string', Rule::in($this->zoneOptions($slot->zone))],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

        DB::transaction(function () use ($validated, $request, $slot): void {
            $this->lockManagerSlotMutations();
            $slot = ParkingSlot::query()->lockForUpdate()->findOrFail($slot->id);

            // Existing zones may predate a lower limit; only a move into another zone is checked.
            if ($validated['zone'] !== $slot->zone) {
                $this->assertZoneLimits($validated['zone'], $slot);
            }

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
        });

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} was updated.");
    }

    public function editServiceStatus(ParkingSlot $slot): View|RedirectResponse
    {
        if (! $slot->canBeTakenOutOfService()) {
            return redirect()->route('manager.slots.index')
                ->withErrors(['slot' => $slot->blockedReason() ?? "{$slot->code} must be available before it can be taken out of service."]);
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
                ->withErrors(['slot' => $slot->blockedReason() ?? "{$slot->code} must be available before it can be taken out of service."]);
        }

        $request->merge(['notes' => $this->cleanNotes($request->input('notes'))]);
        $validated = $request->validate([
            'reason' => ['required', Rule::in(self::SERVICE_REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

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
                ->withErrors(['slot' => $slot->blockedReason() ?? "{$slot->code} cannot be removed while it is occupied, reserved, or awaiting exit."]);
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
     * Keep new and edited slots inside the limits set on the Settings screen.
     *
     * @throws ValidationException
     */
    private function assertZoneLimits(string $zone, ?ParkingSlot $moving = null): void
    {
        $slotLimit = Lot::maxSlotsPerZone();
        $zoneLimit = Lot::maxActiveZones();

        $slotsInZone = ParkingSlot::query()
            ->where('zone', $zone)
            ->when($moving, fn ($query) => $query->where('id', '!=', $moving->id))
            ->count();

        if ($slotsInZone >= $slotLimit) {
            throw ValidationException::withMessages([
                'zone' => "{$zone} already has {$slotLimit} slots, the configured maximum per zone. Change the limit in Settings before adding more.",
            ]);
        }

        $zonesAfterSave = ParkingSlot::query()
            ->when($moving, fn ($query) => $query->where('id', '!=', $moving->id))
            ->distinct()
            ->pluck('zone')
            ->push($zone)
            ->unique();

        if ($zonesAfterSave->count() > $zoneLimit) {
            throw ValidationException::withMessages([
                'zone' => "The configured maximum of {$zoneLimit} active zones is reached. Use an existing zone or change the limit in Settings.",
            ]);
        }
    }

    private function lockManagerSlotMutations(): void
    {
        User::query()
            ->where('role', User::ROLE_MANAGER)
            ->where('status', User::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get(['id']);
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

    /** Trim the note and treat an empty one as none. */
    private function cleanNotes(mixed $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'code.required' => 'Enter a slot code like B-04.',
            'code.regex' => 'Use a zone letter, a dash and two digits, for example B-04.',
            'code.unique' => 'This slot code already exists.',
            'zone.required' => 'Choose a zone.',
            'zone.in' => 'Choose a zone from the list.',
            'type.required' => 'Choose a slot type.',
            'type.in' => 'Choose a slot type from the list.',
            'reason.required' => 'Choose a reason.',
            'reason.in' => 'Choose a reason from the list.',
            'notes.max' => 'Notes can have up to 1000 characters.',
        ];
    }
}
