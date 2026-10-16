<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Services\DriverData;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public const COLORS = ['White', 'Black', 'Gray', 'Silver', 'Red', 'Blue', 'Green', 'Yellow', 'Orange', 'Brown', 'Other'];

    public function index(Request $request, DriverData $data): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('created_at')->get()
            ->each(fn (Vehicle $v) => $v->setAttribute('in_use', $data->vehicleUse($v)));

        return view('driver.app.vehicles.index', ['vehicles' => $vehicles]);
    }

    public function create(Request $request): View
    {
        return view('driver.app.vehicles.form', [
            'vehicle' => new Vehicle(['contact_number' => $request->user()->phone]),
            'colors' => self::COLORS,
            'types' => Vehicle::TYPES,
            'editing' => false,
            'locked' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $existing = Vehicle::withTrashed()->where('plate_number', $data['plate_number'])->first();

        if ($existing && ! $existing->trashed()) {
            return back()->withInput()->with('modal', [
                'title' => Lot::plate($data['plate_number']).' is already registered',
                'body' => Lot::plate($data['plate_number']).' is already registered to an account. A plate can belong to one account only.',
                'note' => 'If this is your vehicle, ask the booth attendant to check it.',
                'primary' => ['label' => 'Edit plate', 'close' => true],
                'secondary' => ['label' => 'Close', 'close' => true],
            ]);
        }

        if ($existing && $existing->trashed() && $existing->user_id !== $user->id) {
            return back()->withInput()->with('modal', [
                'title' => Lot::plate($data['plate_number']).' is already registered',
                'body' => Lot::plate($data['plate_number']).' is already registered to an account. A plate can belong to one account only.',
                'note' => 'If this is your vehicle, ask the booth attendant to check it.',
                'primary' => ['label' => 'Edit plate', 'close' => true],
                'secondary' => ['label' => 'Close', 'close' => true],
            ]);
        }

        if ($existing) {
            $existing->restore();
            $existing->update($data);
            $vehicle = $existing;
        } else {
            $vehicle = $user->vehicles()->create($data);
        }

        ActivityLog::record('vehicle_added', $user, $request, $vehicle->plate_number, 'driver');

        return redirect()->route('driver.vehicles')->with('toast', ['Vehicle saved', Lot::plate($vehicle->plate_number).' was added.']);
    }

    public function edit(Request $request, Vehicle $vehicle, DriverData $data): View
    {
        $this->own($request, $vehicle);

        return view('driver.app.vehicles.form', [
            'vehicle' => $vehicle,
            'colors' => self::COLORS,
            'types' => Vehicle::TYPES,
            'editing' => true,
            'locked' => $data->vehicleUse($vehicle) !== null,
        ]);
    }

    public function update(Request $request, Vehicle $vehicle, DriverData $data): RedirectResponse
    {
        $this->own($request, $vehicle);
        $input = $this->validated($request, $vehicle);

        if ($input['plate_number'] !== $vehicle->plate_number && $data->vehicleUse($vehicle)) {
            return back()->withInput()->withErrors(['plate_number' => 'The plate cannot change while this vehicle has a reservation or a parking session.']);
        }

        $duplicate = Vehicle::withTrashed()->where('plate_number', $input['plate_number'])->where('id', '!=', $vehicle->id)->first();

        if ($duplicate) {
            return back()->withInput()->with('modal', [
                'title' => Lot::plate($input['plate_number']).' is already registered',
                'body' => Lot::plate($input['plate_number']).' is already registered to an account. A plate can belong to one account only.',
                'note' => 'If this is your vehicle, ask the booth attendant to check it.',
                'primary' => ['label' => 'Edit plate', 'close' => true],
                'secondary' => ['label' => 'Close', 'close' => true],
            ]);
        }

        $vehicle->update($input);
        ActivityLog::record('vehicle_updated', $request->user(), $request, $vehicle->plate_number, 'driver');

        return redirect()->route('driver.vehicles')->with('toast', ['Vehicle saved', Lot::plate($vehicle->plate_number).' was updated.']);
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
    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $request->merge([
            'plate_number' => Vehicle::normalizePlate($request->input('plate_number')),
            'contact_number' => Lot::normalizePhone($request->input('contact_number')),
        ]);

        return $request->validate([
            'plate_number' => ['required', 'regex:/^[A-Z0-9]{5,8}$/'],
            'color' => ['required', Rule::in(self::COLORS)],
            'vehicle_type' => ['required', Rule::in(Vehicle::TYPES)],
            'make_model' => ['nullable', 'string', 'max:60'],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'],
        ], [
            'plate_number.required' => 'Enter the plate number.',
            'plate_number.regex' => 'Enter a plate like ABC 1234.',
            'color.required' => 'Choose a color.',
            'color.in' => 'Choose a color from the list.',
            'vehicle_type.required' => 'Choose a vehicle type.',
            'vehicle_type.in' => 'Choose a vehicle type from the list.',
            'contact_number.regex' => 'Enter 11 digits, for example 0917 555 0142.',
        ]);
    }
}
