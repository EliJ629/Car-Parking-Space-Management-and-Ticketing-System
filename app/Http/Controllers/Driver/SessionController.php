<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ParkingPayment;
use App\Models\ParkingRateSet;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\DriverData;
use App\Services\FeeCalculator;
use App\Services\GateService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** My session, the slot locator, parking history and receipts. */
class SessionController extends Controller
{
    public function show(Request $request, DriverData $data, FeeCalculator $fees, GateService $gate): View
    {
        $user = $request->user();
        $session = $data->openSession($user);

        return view('driver.app.session', [
            'session' => $session,
            'estimate' => $session && $session->status === 'active' ? $fees->estimate($session) : null,
            'windowEnd' => $session && $session->status === 'paid' ? $gate->windowEnd($session) : null,
            'completed' => $session ? null : $data->recentCompletedSession($user),
            'vehicle' => $session ? Vehicle::withTrashed()->where('user_id', $user->id)->where('plate_number', $session->plate_number)->first() : null,
            'dailyMax' => ParkingRateSet::effectiveAt()?->daily_maximum,
        ]);
    }

    public function locate(Request $request, DriverData $data): View|RedirectResponse
    {
        $user = $request->user();
        $session = $data->openSession($user);

        if (! $session) {
            return redirect()->route('driver.session');
        }

        $slots = ParkingSlot::query()->orderBy('zone')->orderBy('code')->get();
        $zonePositions = \App\Models\ParkingLayoutZone::query()
            ->whereIn('zone', $slots->pluck('zone')->unique())
            ->get()
            ->keyBy('zone');

        return view('driver.app.locate', [
            'session' => $session,
            'zones' => \App\Support\SlotLayout::decorate($slots)->groupBy('zone'),
            'zonePositions' => $zonePositions,
            'vehicle' => Vehicle::withTrashed()->where('user_id', $user->id)->where('plate_number', $session->plate_number)->first(),
        ]);
    }

    public function history(Request $request, DriverData $data): View
    {
        $user = $request->user();
        $range = in_array($request->query('range'), ['7', '30', '90', 'all'], true) ? $request->query('range') : '30';
        $vehicles = $user->vehicles()->orderBy('created_at')->get();
        $vehicleId = (int) $request->query('vehicle', 0);
        $plate = $vehicleId ? $vehicles->firstWhere('id', $vehicleId)?->plate_number : null;

        $payments = $data->payments($user)
            ->when($range !== 'all', fn ($rows) => $rows->filter(fn (ParkingPayment $p) => $p->paid_at->greaterThanOrEqualTo(now()->subDays((int) $range))))
            ->when($plate, fn ($rows) => $rows->filter(fn (ParkingPayment $p) => $p->session?->plate_number === $plate))
            ->values();

        return view('driver.app.history', [
            'payments' => $payments,
            'range' => $range,
            'vehicles' => $vehicles,
            'vehicleId' => $vehicleId,
        ]);
    }

    public function receipt(Request $request, ParkingPayment $payment, DriverData $data): View
    {
        $session = $payment->session()->with('slot')->first();
        abort_unless($session && $data->sessions($request->user())->whereKey($session->id)->exists(), 404);

        $unit = (float) (ParkingRateSet::effectiveAt($payment->paid_at)?->additional_hour_fee ?? 20);
        $snapshot = (array) $payment->receipt_snapshot;

        return view('driver.app.receipt', [
            'payment' => $payment->load('attendant'),
            'session' => $session,
            'additionalHours' => $unit > 0 ? (int) round((float) $payment->additional_fee / $unit) : 0,
            'unit' => $unit,
            'cash' => isset($snapshot['cash_received']) ? (float) $snapshot['cash_received'] : null,
            'change' => isset($snapshot['change']) ? (float) $snapshot['change'] : null,
            'lotName' => $snapshot['lot_name'] ?? Lot::setting('lot_name', 'Bayline Central Lot'),
        ]);
    }
}
