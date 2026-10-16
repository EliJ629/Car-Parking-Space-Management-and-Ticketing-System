<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\ParkingDiscount;
use App\Models\ParkingRateSet;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RatePolicyController extends ManagerController
{
    public function index(): View
    {
        return $this->managerPage('Rates and policies', 'rates', view('staff.manager.rates.index', [
            'currentRate' => ParkingRateSet::effectiveAt(),
            'upcomingRates' => ParkingRateSet::query()->where('effective_at', '>', now())->orderBy('effective_at')->get(),
            'discounts' => ParkingDiscount::query()->orderBy('name')->get(),
            'history' => ActivityLog::query()->with('user')
                ->where('action', 'rate_set_created')
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'settings' => SystemSetting::values(),
        ]));
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_hour_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'additional_hour_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'daily_maximum' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'lost_ticket_penalty' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'overstay_penalty' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'effective_at' => ['required', 'date', 'after_or_equal:now'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $rate = DB::transaction(function () use ($data, $request): ParkingRateSet {
            $rate = ParkingRateSet::create($data + ['created_by' => $request->user()->id]);

            ActivityLog::record(
                'rate_set_created',
                $request->user(),
                $request,
                $data['reason'],
                'manager',
                ['after' => $rate->only([
                    'first_hour_fee',
                    'additional_hour_fee',
                    'daily_maximum',
                    'lost_ticket_penalty',
                    'overstay_penalty',
                    'effective_at',
                    'reason',
                ])],
            );

            return $rate;
        });

        return redirect()->route('manager.rates.index')->with('status', "Rate set #{$rate->id} was scheduled.");
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:parking_discounts,name'],
            'percent' => ['required', 'integer', 'between:1,100'],
            'proof_label' => ['required', 'string', 'max:80'],
            'requires_manager_pin' => ['sometimes', 'boolean'],
        ]);
        $discount = ParkingDiscount::create($data + [
            'requires_manager_pin' => $request->boolean('requires_manager_pin'),
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record('discount_created', $request->user(), $request, $discount->name, 'manager', [
            'after' => $discount->only(['name', 'percent', 'proof_label', 'requires_manager_pin']),
        ]);

        return redirect()->route('manager.rates.index')->with('status', "{$discount->name} discount was added.");
    }

    public function updateDiscount(Request $request, ParkingDiscount $discount): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('parking_discounts', 'name')->ignore($discount->id)],
            'percent' => ['required', 'integer', 'between:1,100'],
            'proof_label' => ['required', 'string', 'max:80'],
            'requires_manager_pin' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $before = $discount->only(['name', 'percent', 'proof_label', 'requires_manager_pin', 'active']);
        $discount->update($data + [
            'requires_manager_pin' => $request->boolean('requires_manager_pin'),
            'active' => $request->boolean('active', true),
        ]);

        ActivityLog::record('discount_updated', $request->user(), $request, $discount->name, 'manager', [
            'before' => $before,
            'after' => $discount->only(array_keys($before)),
        ]);

        return redirect()->route('manager.rates.index')->with('status', "{$discount->name} discount was updated.");
    }

    public function destroyDiscount(Request $request, ParkingDiscount $discount): RedirectResponse
    {
        $before = $discount->only(['name', 'percent', 'proof_label', 'requires_manager_pin', 'active']);
        $discount->delete();

        ActivityLog::record('discount_deleted', $request->user(), $request, $before['name'], 'manager', [
            'before' => $before,
        ]);

        return redirect()->route('manager.rates.index')->with('status', "{$before['name']} discount was removed.");
    }
}
