<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\ParkingDiscount;
use App\Models\ParkingRateSet;
use App\Models\SystemSetting;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        Rules::tidy($request, 'reason');
        $money = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'];
        $data = $request->validate([
            'first_hour_fee' => $money,
            'additional_hour_fee' => $money,
            'daily_maximum' => [...$money, 'gte:first_hour_fee'],
            'lost_ticket_penalty' => $money,
            'overstay_penalty' => $money,
            'effective_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'first_hour_fee.required' => 'Enter the first hour fee.',
            'additional_hour_fee.required' => 'Enter the fee for each added hour.',
            'daily_maximum.required' => 'Enter the daily maximum.',
            'daily_maximum.gte' => 'The daily maximum must be at least the first hour fee.',
            'lost_ticket_penalty.required' => 'Enter the lost ticket penalty.',
            'overstay_penalty.required' => 'Enter the overstay penalty.',
            '*.numeric' => 'Enter an amount like 30.00.',
            '*.decimal' => 'Use at most two decimal places.',
            '*.min' => 'The amount cannot be below 0.',
            '*.max' => 'The amount is too large.',
            'effective_at.required' => 'Choose when the new rates start.',
            'effective_at.date' => 'Choose a valid date and time.',
            'reason.required' => 'Write the reason for this change.',
            'reason.min' => 'Write the reason for this change.',
        ]);

        // The manager types lot time (Asia/Manila). Rates are kept in UTC like every other time.
        $effective = Carbon::parse($data['effective_at'], Lot::TIMEZONE)->setTimezone('UTC');

        if ($effective->lessThan(now()->startOfMinute())) {
            return back()->withInput()->withErrors(['effective_at' => 'Choose a start time that is not in the past.']);
        }

        if ($effective->greaterThan(now()->addYears(2))) {
            return back()->withInput()->withErrors(['effective_at' => 'Choose a start time within the next 2 years.']);
        }

        $data['effective_at'] = $effective;

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
        Rules::tidy($request, 'name', 'proof_label');
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'unique:parking_discounts,name'],
            'percent' => ['required', 'integer', 'between:1,100'],
            'proof_label' => ['required', 'string', 'min:2', 'max:80'],
            'requires_manager_pin' => ['sometimes', 'boolean'],
        ], $this->discountMessages());
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
        Rules::tidy($request, 'name', 'proof_label');
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('parking_discounts', 'name')->ignore($discount->id)],
            'percent' => ['required', 'integer', 'between:1,100'],
            'proof_label' => ['required', 'string', 'min:2', 'max:80'],
            'requires_manager_pin' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ], $this->discountMessages());
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

    /** @return array<string, string> */
    private function discountMessages(): array
    {
        return [
            'name.required' => 'Enter the discount name.',
            'name.min' => 'Enter the discount name.',
            'name.unique' => 'A discount with this name already exists.',
            'percent.required' => 'Enter the discount percent.',
            'percent.integer' => 'Enter a whole number from 1 to 100.',
            'percent.between' => 'Enter a percent from 1 to 100.',
            'proof_label.required' => 'Enter the proof the attendant must see.',
            'proof_label.min' => 'Enter the proof the attendant must see.',
        ];
    }
}
