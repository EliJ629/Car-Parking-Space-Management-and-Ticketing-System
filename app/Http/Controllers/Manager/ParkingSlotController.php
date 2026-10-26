<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\ParkingLayoutZone;
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
            'floor' => ['nullable', 'integer', 'min:1', 'max:'.Lot::maxFloor()],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $slots = ParkingSlot::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['zone'] ?? null, fn ($query, string $zone) => $query->where('zone', $zone))
            ->when($filters['floor'] ?? null, fn ($query, int $floor) => $query->whereIn('zone', Lot::zonesForFloor($floor)))
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

        $zones = ParkingSlot::query()->select('zone')->distinct()->orderBy('zone')->pluck('zone');

        return $this->managerPage(
            'Parking slots',
            'slots',
            view('staff.manager.slots.index', [
                'slots' => $slots,
                'zones' => $zones,
                'floors' => Lot::availableFloors($zones),
                'statuses' => ParkingSlot::statuses(),
                'filters' => $filters,
                'longParked' => \App\Models\ParkingSession::query()
                    ->where('status', \App\Models\ParkingSession::STATUS_ACTIVE)
                    ->where('entered_at', '<=', now()->subHours(\App\Support\Lot::longParkingHours()))
                    ->whereIn('slot_id', $slots->pluck('id'))
                    ->get()
                    ->keyBy('slot_id'),
            ]),
        );
    }

    public function create(): View
    {
        return $this->managerPage(
            'Add parking slot',
            'slots',
            view('staff.manager.slots.create', $this->slotFormData()),
        );
    }

    public function zoneCategories(): View
    {
        $zones = ParkingSlot::query()
            ->select('zone')
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->map(function (string $zone): array {
                $category = ParkingLayoutZone::query()->where('zone', $zone)->value('vehicle_type');

                return [
                    'zone' => $zone,
                    'vehicle_type' => $category ?? ParkingLayoutZone::VEHICLE_CAR,
                    'slots' => ParkingSlot::query()->where('zone', $zone)->count(),
                ];
            });

        return $this->managerPage('Zone categories', 'slots', view('staff.manager.slots.categories', [
            'zones' => $zones,
            'vehicleTypes' => ParkingLayoutZone::vehicleTypes(),
        ]));
    }

    public function updateZoneCategories(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*' => ['required', Rule::in(ParkingLayoutZone::vehicleTypes())],
        ], $this->messages());

        DB::transaction(function () use ($validated): void {
            $zones = ParkingSlot::query()
                ->whereIn('zone', array_keys($validated['categories']))
                ->distinct()
                ->pluck('zone');

            if ($zones->count() !== count($validated['categories'])) {
                throw ValidationException::withMessages([
                    'categories' => 'Choose categories only for existing zones.',
                ]);
            }

            foreach ($validated['categories'] as $zone => $vehicleType) {
                ParkingLayoutZone::query()->updateOrCreate(
                    ['zone' => $zone],
                    ['vehicle_type' => $vehicleType],
                );
            }
        });

        return redirect()->route('manager.slots.index')->with('status', 'Zone categories were updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeSlotInput($request);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z]-\d{2}$/', 'unique:parking_slots,code'],
            'slot_number' => ['required', 'integer', 'between:1,16'],
            'floor' => ['required', 'integer', Rule::in($this->availableFloors())],
            'zone' => ['required', 'string', Rule::in($this->zoneOptions($request->integer('floor')))],
            'vehicle_type' => ['nullable', Rule::in(ParkingLayoutZone::vehicleTypes())],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

        $slot = DB::transaction(function () use ($validated, $request): ParkingSlot {
            $this->lockManagerSlotMutations();
            $this->assertFloorAndZoneLimits($validated['floor'], $validated['zone']);
            $vehicleType = $validated['vehicle_type']
                ?? ParkingLayoutZone::query()->where('zone', $validated['zone'])->value('vehicle_type')
                ?? ParkingLayoutZone::VEHICLE_CAR;
            $this->syncZoneVehicleType($validated['zone'], $vehicleType);

            $slot = ParkingSlot::create([
                'code' => $validated['code'],
                'zone' => $validated['zone'],
                'type' => $validated['type'],
                // A slot takes the vehicle class of its zone (Car zone or Motorcycle zone).
                'vehicle_class' => ParkingSlot::classForZoneType($vehicleType),
                'notes' => $validated['notes'] ?? null,
                'status' => ParkingSlot::STATUS_AVAILABLE,
            ]);

            ActivityLog::record(
                'slot_created',
                $request->user(),
                $request,
                $slot->code,
                'manager',
                ['slot' => $slot->only(['code', 'zone', 'type', 'vehicle_class', 'status', 'notes'])],
            );

            return $slot;
        });

        return redirect()->route('manager.slots.index')->with('status', "{$slot->code} was added as available.");
    }

    public function edit(ParkingSlot $slot): View
    {
        if (in_array($slot->status, [ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_RESERVED], true)) {
            abort(403, 'Occupied and reserved parking slots cannot be edited.');
        }

        return $this->managerPage(
            "Edit {$slot->code}",
            'slots',
            view('staff.manager.slots.edit', $this->slotFormData($slot)),
        );
    }

    public function update(Request $request, ParkingSlot $slot): RedirectResponse
    {
        if (in_array($slot->status, [ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_RESERVED], true)) {
            return redirect()->route('manager.slots.index')
                ->withErrors(['slot' => 'Occupied and reserved parking slots cannot be edited.']);
        }

        $this->normalizeSlotInput($request, $slot);

        $validated = $request->validate([
            'floor' => ['required', 'integer', Rule::in($this->availableFloors($slot))],
            'zone' => ['required', 'string', Rule::in($this->zoneOptions($request->integer('floor'), $slot->zone))],
            'vehicle_type' => ['nullable', Rule::in(ParkingLayoutZone::vehicleTypes())],
            'type' => ['required', Rule::in([ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

        DB::transaction(function () use ($validated, $request, $slot): void {
            $this->lockManagerSlotMutations();
            $slot = ParkingSlot::query()->lockForUpdate()->findOrFail($slot->id);

            if (in_array($slot->status, [ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_RESERVED], true)) {
                throw ValidationException::withMessages([
                    'slot' => 'Occupied and reserved parking slots cannot be edited.',
                ]);
            }

            $this->assertFloorMatchesZone($validated['floor'], $validated['zone'], $slot);

            // Existing zones may predate a lower limit; only a move into another zone is checked.
            if ($validated['zone'] !== $slot->zone) {
                $this->assertFloorAndZoneLimits($validated['floor'], $validated['zone'], $slot);
            }
            $vehicleType = $validated['vehicle_type']
                ?? ParkingLayoutZone::query()->where('zone', $validated['zone'])->value('vehicle_type')
                ?? ParkingLayoutZone::VEHICLE_CAR;
            $this->syncZoneVehicleType($validated['zone'], $vehicleType, $validated['zone'] === $slot->zone);

            $before = $slot->only(['code', 'zone', 'type', 'vehicle_class', 'status', 'notes']);
            $slot->update([
                'zone' => $validated['zone'],
                'type' => $validated['type'],
                'vehicle_class' => ParkingSlot::classForZoneType(ParkingLayoutZone::query()->where('zone', $validated['zone'])->value('vehicle_type')),
                'notes' => $validated['notes'] ?? null,
            ]);

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
     * @throws ValidationException
     */
    private function assertFloorMatchesZone(int $floor, string $zone, ?ParkingSlot $moving = null): void
    {
        $zoneFloor = Lot::floorForZone($zone);

        if ($zoneFloor === null) {
            throw ValidationException::withMessages([
                'zone' => 'Choose a zone that belongs to an existing floor.',
            ]);
        }

        if ($zoneFloor !== $floor) {
            throw ValidationException::withMessages([
                'zone' => 'That zone does not belong to the selected floor.',
            ]);
        }

        if (! in_array($floor, $this->availableFloors($moving), true)) {
            throw ValidationException::withMessages([
                'floor' => 'That floor is not available yet. A new floor opens after the current floor has 4 zones.',
            ]);
        }
    }

    /**
     * Keep new and edited slots inside the floor, zone, and slot limits.
     *
     * @throws ValidationException
     */
    private function assertFloorAndZoneLimits(int $floor, string $zone, ?ParkingSlot $moving = null): void
    {
        $this->assertFloorMatchesZone($floor, $zone, $moving);

        $slotLimit = Lot::maxSlotsPerZone();
        $zoneLimit = Lot::maxActiveZones();
        $zonesPerFloor = Lot::maxZonesPerFloor();

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

        $zonesOnFloor = $zonesAfterSave
            ->filter(fn (string $activeZone): bool => Lot::floorForZone($activeZone) === $floor)
            ->unique();

        if ($zonesOnFloor->count() > $zonesPerFloor) {
            throw ValidationException::withMessages([
                'zone' => 'A floor can only contain 4 zones. Choose an existing zone on this floor or use the next floor.',
            ]);
        }
    }

    /**
     * A zone is for one kind of vehicle. Giving a slot another class changes the whole zone, which is only allowed while
     * no slot in it is in use or booked. A slot moved into a zone of the other class is refused.
     *
     * @throws ValidationException
     */
    private function lockManagerSlotMutations(): void
    {
        User::query()
            ->where('role', User::ROLE_MANAGER)
            ->where('status', User::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get(['id']);
    }

    private function normalizeSlotInput(Request $request, ?ParkingSlot $slot = null): void
    {
        $zone = trim((string) $request->input('zone'));
        $legacyCode = strtoupper(trim((string) $request->input('code')));
        $slotNumber = $request->input('slot_number');
        if ($slotNumber === null && preg_match('/^[A-Z]-(\d{2})$/', $legacyCode, $matches)) {
            $slotNumber = (int) $matches[1];
        }
        $zoneLetter = preg_match('/([A-Z])$/i', $zone, $matches) === 1 ? strtoupper($matches[1]) : null;
        $floor = $request->filled('floor')
            ? $request->integer('floor')
            : (Lot::floorForZone($zone) ?? Lot::floorForZone($slot?->zone) ?? 1);

        $request->merge([
            'code' => $zoneLetter !== null && is_numeric($slotNumber)
                ? $zoneLetter.'-'.str_pad((string) $slotNumber, 2, '0', STR_PAD_LEFT)
                : $legacyCode,
            'slot_number' => $slotNumber,
            'zone' => $zone,
            'floor' => $floor,
            'vehicle_type' => $request->input('vehicle_type')
                ?? ParkingLayoutZone::query()->where('zone', $zone)->value('vehicle_type')
                ?? ParkingLayoutZone::VEHICLE_CAR,
            'notes' => $this->cleanNotes($request->input('notes')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function slotFormData(?ParkingSlot $slot = null): array
    {
        $activeZones = ParkingSlot::query()->select('zone')->distinct()->orderBy('zone')->pluck('zone');
        if ($slot?->zone && ! $activeZones->contains($slot->zone)) {
            $activeZones->push($slot->zone);
        }

        $floors = $this->availableFloors($slot);
        $selectedFloor = old('floor') !== null
            ? (int) old('floor')
            : ($slot === null ? 1 : (Lot::floorForZone($slot->zone) ?? 1));

        if (! in_array($selectedFloor, $floors, true)) {
            $floors[] = $selectedFloor;
            sort($floors);
        }

        $floorZoneMap = [];
        foreach ($floors as $floor) {
            $zones = Lot::zonesForFloor($floor);
            $activeOnFloor = Lot::activeZonesOnFloor($floor, $activeZones);
            $floorZoneMap[$floor] = [
                'zones' => $zones,
                'active' => $activeOnFloor,
                'count' => count($activeOnFloor),
            ];
        }

        return [
            'slot' => $slot,
            'floors' => $floors,
            'selectedFloor' => $selectedFloor,
            'floorZoneMap' => $floorZoneMap,
            'zones' => $this->zoneOptions($selectedFloor, $slot?->zone),
            'activeZonesOnFloor' => Lot::activeZonesOnFloor($selectedFloor, $activeZones),
            'zoneVehicleTypes' => ParkingLayoutZone::query()
                ->whereIn('zone', $activeZones)
                ->pluck('vehicle_type', 'zone'),
            'vehicleTypes' => ParkingLayoutZone::vehicleTypes(),
            'types' => [ParkingSlot::TYPE_STANDARD, ParkingSlot::TYPE_PWD],
            'maxZonesPerFloor' => Lot::maxZonesPerFloor(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function availableFloors(?ParkingSlot $slot = null): array
    {
        $activeZones = ParkingSlot::query()->select('zone')->distinct()->pluck('zone');
        if ($slot?->zone && ! $activeZones->contains($slot->zone)) {
            $activeZones->push($slot->zone);
        }

        $floors = Lot::availableFloors($activeZones);
        $currentFloor = Lot::floorForZone($slot?->zone);

        if ($currentFloor !== null && ! in_array($currentFloor, $floors, true)) {
            $floors[] = $currentFloor;
            sort($floors);
        }

        return $floors;
    }

    /**
     * @return array<int, string>
     */
    private function zoneOptions(int $floor, ?string $currentZone = null): array
    {
        $zones = collect(Lot::zonesForFloor($floor));

        if ($currentZone !== null && ! $zones->contains($currentZone)) {
            $currentFloor = Lot::floorForZone($currentZone);
            if ($currentFloor === null || $currentFloor === $floor) {
                $zones->push($currentZone);
            }
        }

        return $zones->values()->all();
    }

    /** Trim the note and treat an empty one as none. */
    private function cleanNotes(mixed $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }

    private function syncZoneVehicleType(string $zone, string $vehicleType, bool $allowChange = false): void
    {
        $zoneRecord = ParkingLayoutZone::query()->lockForUpdate()->firstOrNew(['zone' => $zone]);

        if ($zoneRecord->exists
            && $zoneRecord->vehicle_type !== null
            && $zoneRecord->vehicle_type !== $vehicleType) {
            if (! $allowChange) {
                throw ValidationException::withMessages([
                    'vehicle_type' => "Zone {$zone} is classified as {$zoneRecord->vehicle_type}.",
                ]);
            }

            // A zone changes its vehicle type only while none of its slots is in use or booked.
            $busy = ParkingSlot::query()->where('zone', $zone)->get()
                ->first(fn (ParkingSlot $s) => ! in_array($s->status, [ParkingSlot::STATUS_AVAILABLE, ParkingSlot::STATUS_OUT_OF_SERVICE], true) || $s->nextOpenBooking() !== null);

            if ($busy) {
                throw ValidationException::withMessages([
                    'vehicle_type' => "Zone {$zone} cannot change its vehicle type while {$busy->code} is in use or booked. Wait until the zone is empty, or cancel the booking.",
                ]);
            }
        }

        $zoneRecord->vehicle_type = $vehicleType;
        $zoneRecord->save();

        // Every slot in the zone carries the zone's vehicle class.
        ParkingSlot::query()->where('zone', $zone)->update(['vehicle_class' => ParkingSlot::classForZoneType($vehicleType)]);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'code.required' => 'Enter a slot code like B-04.',
            'code.regex' => 'Use a zone letter, a dash and two digits, for example B-04.',
            'code.unique' => 'This slot code already exists.',
            'slot_number.required' => 'Choose a slot number.',
            'slot_number.integer' => 'Choose a valid slot number.',
            'slot_number.between' => 'Choose a slot number from 01 to 16.',
            'floor.required' => 'Choose a floor.',
            'floor.in' => 'That floor is not available yet. A new floor opens after the current floor has 4 zones.',
            'zone.required' => 'Choose a zone.',
            'zone.in' => 'Choose a zone that belongs to the selected floor. A floor can only contain 4 zones.',
            'vehicle_type.required' => 'Choose a vehicle type for this zone.',
            'vehicle_type.in' => 'Choose Car or Motorcycle.',
            'type.required' => 'Choose a slot type.',
            'type.in' => 'Choose a slot type from the list.',
            'reason.required' => 'Choose a reason.',
            'reason.in' => 'Choose a reason from the list.',
            'notes.max' => 'Notes can have up to 1000 characters.',
        ];
    }
}
