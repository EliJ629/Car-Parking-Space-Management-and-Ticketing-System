<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehiclePreviousPlate;
use App\Services\DriverData;
use App\Support\Lot;
use App\Support\PlateEntry;
use App\Support\Rules;
use App\Support\VehicleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request, DriverData $data): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('created_at')->get()
            ->each(fn (Vehicle $v) => $v->setAttribute('in_use', $data->vehicleUse($v)));

        return view('driver.app.vehicles.index', ['vehicles' => $vehicles]);
    }

    public function create(Request $request): View
    {
        $vehicle = new Vehicle(['contact_number' => $request->user()->phone]);

        return view('driver.app.vehicles.form', $this->formData($vehicle) + ['editing' => false, 'locked' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $existing = $data['plate_number'] === null ? null : Vehicle::withTrashed()->where('plate_number', $data['plate_number'])->first();
        $takenBefore = $data['plate_number'] !== null && $existing === null && VehiclePreviousPlate::query()->where('plate_number', $data['plate_number'])->exists();

        if (($existing && ! $existing->trashed()) || $takenBefore || ($existing && $existing->trashed() && $existing->user_id !== $user->id)) {
            return $this->plateTaken($data['plate_number']);
        }

        if ($existing) {
            $existing->restore();
            $existing->update($data);
            $vehicle = $existing;
        } else {
            $vehicle = $user->vehicles()->create($data);
        }

        ActivityLog::record('vehicle_added', $user, $request, $vehicle->hasPlate() ? $vehicle->plate_number : 'no plate yet', 'driver');

        return redirect()->route('driver.vehicles')->with('toast', [
            'Vehicle saved',
            $vehicle->hasPlate()
                ? Lot::plate($vehicle->plate_number).($vehicle->hasTemporaryPlate() ? ' (temporary plate)' : '').' was added.'
                : 'Your vehicle was added. Add its plate number here once the LTO has issued it.',
        ]);
    }

    public function edit(Request $request, Vehicle $vehicle, DriverData $data): View
    {
        $this->own($request, $vehicle);

        return view('driver.app.vehicles.form', $this->formData($vehicle) + [
            'editing' => true,
            'locked' => $vehicle->plateCanBeSet() && $data->vehicleUse($vehicle) !== null,
        ]);
    }

    public function update(Request $request, Vehicle $vehicle, DriverData $data): RedirectResponse
    {
        $this->own($request, $vehicle);
        $input = $this->validated($request, $vehicle, $entry);

        $before = $vehicle->plate_number;
        $changed = $entry->changing && $input['plate_number'] !== $before;

        if ($changed && $before !== null && $data->vehicleUse($vehicle)) {
            return back()->withInput()->withErrors(['plate_number' => 'The plate cannot change while this vehicle has a reservation or a parking session.']);
        }

        if ($changed) {
            $duplicate = Vehicle::withTrashed()->where('plate_number', $input['plate_number'])->where('id', '!=', $vehicle->id)->exists()
                || VehiclePreviousPlate::query()->where('plate_number', $input['plate_number'])->exists();

            if ($duplicate) {
                return $this->plateTaken($input['plate_number']);
            }
        }

        DB::transaction(function () use ($vehicle, $input, $changed, $before, $request) {
            // A temporary plate that is replaced stays on record. Violations on it count for the same vehicle (BanService).
            if ($changed && $before !== null) {
                VehiclePreviousPlate::create([
                    'vehicle_id' => $vehicle->id,
                    'user_id' => $request->user()->id,
                    'plate_number' => $before,
                    'was_temporary' => (bool) $vehicle->plate_is_temporary,
                    'replaced_at' => now(),
                ]);
            }

            $vehicle->update($input);
        });

        if ($changed && $before !== null) {
            ActivityLog::record('vehicle_plate_replaced', $request->user(), $request, $before.' -> '.$vehicle->plate_number, 'driver');
        } elseif ($changed) {
            ActivityLog::record('vehicle_plate_set', $request->user(), $request, $vehicle->plate_number, 'driver');
        } else {
            ActivityLog::record('vehicle_updated', $request->user(), $request, $vehicle->hasPlate() ? $vehicle->plate_number : 'no plate yet', 'driver');
        }

        return redirect()->route('driver.vehicles')->with('toast', [
            'Vehicle saved',
            $vehicle->hasPlate()
                ? Lot::plate($vehicle->plate_number).($vehicle->hasTemporaryPlate() ? ' (temporary plate)' : '').' was '.($changed ? 'saved.' : 'updated.')
                : 'Your vehicle was updated.',
        ]);
    }

    private function plateTaken(?string $plate): RedirectResponse
    {
        return back()->withInput()->with('modal', [
            'title' => Lot::plate($plate).' is already registered',
            'body' => Lot::plate($plate).' is already registered to an account. A plate can belong to one account only.',
            'note' => 'If this is your vehicle, ask the booth attendant to check it.',
            'primary' => ['label' => 'Edit plate', 'close' => true],
            'secondary' => ['label' => 'Close', 'close' => true],
        ]);
    }

    public function destroy(Request $request, Vehicle $vehicle, DriverData $data): RedirectResponse
    {
        $this->own($request, $vehicle);
        $use = $data->vehicleUse($vehicle);

        if ($use) {
            $record = $use['record'];
            $what = $use['type'] === 'reservation'
                ? 'reservation '.$record->code.' ('.($record->slot?->code ?? $record->slot_code).', '.Lot::whenText($record->scheduled_at).')'
                : 'parking ticket '.$record->ticket_number;

            return redirect()->route('driver.vehicles')->with('modal', [
                'title' => Lot::plate($vehicle->plate_number).' cannot be removed',
                'body' => "This vehicle is used by {$what}.",
                'note' => 'Cancel the reservation or wait until the parking session ends, then remove the vehicle.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        $vehicle->delete();
        ActivityLog::record('vehicle_removed', $request->user(), $request, $vehicle->plate_number, 'driver');

        return redirect()->route('driver.vehicles')->with('toast', ['Vehicle removed', Lot::plate($vehicle->plate_number).' was removed. Your parking history keeps its plate number.']);
    }

    private function own(Request $request, Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function formData(Vehicle $vehicle): array
    {
        return [
            'vehicle' => $vehicle,
            'vehicleTypes' => Vehicle::DRIVER_TYPES,
            'catalog' => VehicleCatalog::forForm(),
            'colors' => VehicleCatalog::colors(),
            'form' => VehicleCatalog::formValues($vehicle),
            'hasPlate' => $vehicle->hasPlate(),
            'permanent' => $vehicle->hasPermanentPlate(),
            'temporary' => $vehicle->hasTemporaryPlate(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vehicle $vehicle = null, ?PlateEntry &$entry = null): array
    {
        $request->merge(['contact_number' => Lot::normalizePhone($request->input('contact_number'))]);

        $entry = PlateEntry::resolve($request, $vehicle);

        $rules = [
            'plate_number' => $entry->rules,
            'contact_number' => Rules::mobile(false),
        ];
        $messages = [
            'plate_number.required' => 'Enter the plate number, or tick "still in registration".',
            'plate_number.regex' => Rules::PLATE_MESSAGE,
            'contact_number.regex' => 'Enter 11 digits, for example 0917 555 0142.',
        ];

        // A new vehicle, and a plate that is being set or replaced, need the "all details are correct" confirmation.
        if ($vehicle === null || $entry->changing) {
            $rules['details_confirmed'] = ['accepted'];
            $messages['details_confirmed.accepted'] = 'Check the details and confirm that they are all correct.';
        }

        $data = VehicleCatalog::validate($request, $rules, $messages);
        unset($data['details_confirmed']);

        $data['plate_number'] = $entry->plate;
        $data['plate_is_temporary'] = $entry->temporary;

        return $data;
    }
}
