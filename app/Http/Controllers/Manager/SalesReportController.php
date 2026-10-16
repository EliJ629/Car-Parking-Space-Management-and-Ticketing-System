<?php

namespace App\Http\Controllers\Manager;

use App\Models\Incident;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends ManagerController
{
    public function sales(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $payments = ParkingPayment::query()->with(['session.attendant', 'attendant'])
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('paid_at')
            ->get();
        $today = ParkingPayment::whereDate('paid_at', today())->sum('total');
        $week = ParkingPayment::whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total');
        $month = ParkingPayment::whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('total');
        $byDay = $payments->groupBy(fn (ParkingPayment $payment): string => $payment->paid_at->toDateString())
            ->map(fn ($day) => $day->sum(fn (ParkingPayment $payment): float => (float) $payment->total));
        $attendants = $payments->groupBy(fn (ParkingPayment $payment): string => $payment->attendant?->name ?? 'Former staff')
            ->map(fn ($rows, string $name): array => [
                'name' => $name,
                'sessions' => $rows->count(),
                'cash' => $rows->sum(fn (ParkingPayment $payment): float => (float) $payment->total),
                'discounts' => $rows->sum(fn (ParkingPayment $payment): float => (float) $payment->discount),
                'penalties' => $rows->sum(fn (ParkingPayment $payment): float => (float) $payment->penalty),
            ])->values();

        return $this->managerPage('Sales and revenue', 'sales', view('staff.manager.sales.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'todayTotal' => $today,
            'weekTotal' => $week,
            'monthTotal' => $month,
            'average' => $payments->isEmpty() ? 0 : $payments->avg(fn (ParkingPayment $payment): float => (float) $payment->total),
            'days' => $byDay,
            'payments' => $payments,
            'attendants' => $attendants,
            'totals' => [
                'base' => $payments->sum(fn (ParkingPayment $payment): float => (float) $payment->base_fee),
                'additional' => $payments->sum(fn (ParkingPayment $payment): float => (float) $payment->additional_fee),
                'penalties' => $payments->sum(fn (ParkingPayment $payment): float => (float) $payment->penalty),
                'discounts' => $payments->sum(fn (ParkingPayment $payment): float => (float) $payment->discount),
            ],
        ]));
    }

    public function report(Request $request): View
    {
        [$from, $to] = $this->range($request, 7);
        $sessions = ParkingSession::query()->with('payment')
            ->where('entered_at', '<=', $to->copy()->endOfDay())
            ->where(fn ($query) => $query->whereNull('exited_at')->orWhere('exited_at', '>=', $from->copy()->startOfDay()))
            ->get();
        $capacity = ParkingSlot::query()->where('status', '!=', ParkingSlot::STATUS_OUT_OF_SERVICE)->count();
        $capacityMinutes = max(1, $capacity * $from->diffInMinutes($to->copy()->endOfDay()));
        $occupiedMinutes = $sessions->sum(function (ParkingSession $session) use ($from, $to): int {
            $start = $session->entered_at->max($from->copy()->startOfDay());
            $end = ($session->exited_at ?? $to->copy()->endOfDay())->min($to->copy()->endOfDay());

            return max(0, (int) $start->diffInMinutes($end, false));
        });
        $hourly = array_fill(8, 13, 0);
        foreach ($sessions as $session) {
            $hour = (int) $session->entered_at->format('G');
            if (array_key_exists($hour, $hourly)) {
                $hourly[$hour]++;
            }
        }
        $reservationQuery = ParkingReservation::query()->whereBetween('scheduled_at', [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ]);
        $reservationTotal = (clone $reservationQuery)->count();
        $kept = (clone $reservationQuery)->where('status', 'checked_in')->count();
        $incidents = Incident::query()->whereBetween('reported_at', [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ])->selectRaw('category, COUNT(*) AS aggregate')->groupBy('category')->pluck('aggregate', 'category');
        $peakHour = collect($hourly)->sortDesc()->keys()->first();

        return $this->managerPage('Occupancy and activity report', 'reports', view('staff.manager.reports.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sessionCount' => $sessions->count(),
            'averageOccupancy' => $capacity > 0 ? min(100, round($occupiedMinutes / $capacityMinutes * 100)) : 0,
            'averageStayMinutes' => $sessions->filter(fn (ParkingSession $session): bool => $session->exited_at !== null)
                ->average(fn (ParkingSession $session): float => $session->entered_at->diffInMinutes($session->exited_at)) ?? 0,
            'peakHour' => $sessions->isEmpty() ? null : sprintf('%02d:00', $peakHour),
            'hourly' => $hourly,
            'reservationTotal' => $reservationTotal,
            'reservationsKept' => $kept,
            'incidents' => $incidents,
        ]));
    }

    public function salesExport(Request $request): StreamedResponse
    {
        return $this->exportRange($request, 'sales');
    }

    public function reportExport(Request $request): StreamedResponse
    {
        return $this->exportRange($request, 'occupancy');
    }

    private function exportRange(Request $request, string $report): StreamedResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = $this->range($request);

        return response()->streamDownload(function () use ($report, $from, $to): void {
            $output = fopen('php://output', 'w');

            if ($report === 'sales') {
                fputcsv($output, ['Date', 'Receipt', 'Ticket', 'Plate', 'Base', 'Additional', 'Penalty', 'Discount', 'Total']);
                ParkingPayment::with('session')->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orderBy('paid_at')->chunk(250, function ($payments) use ($output): void {
                        foreach ($payments as $payment) {
                            fputcsv($output, [
                                $payment->paid_at->toDateString(), $payment->receipt_number,
                                $payment->session?->ticket_number, $payment->session?->plate_number,
                                $payment->base_fee, $payment->additional_fee, $payment->penalty,
                                $payment->discount, $payment->total,
                            ]);
                        }
                    });
            } else {
                fputcsv($output, ['Ticket', 'Plate', 'Slot', 'Entry', 'Exit', 'Status']);
                ParkingSession::with('slot')->whereBetween('entered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orderBy('entered_at')->chunk(250, function ($sessions) use ($output): void {
                        foreach ($sessions as $session) {
                            fputcsv($output, [
                                $session->ticket_number, $session->plate_number, $session->slot?->code,
                                $session->entered_at->toDateTimeString(), $session->exited_at?->toDateTimeString(),
                                $session->status,
                            ]);
                        }
                    });
            }

            fclose($output);
        }, "bayline-{$report}-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function range(Request $request, int $defaultDays = 0): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $to = Carbon::parse($data['to'] ?? today())->startOfDay();
        $from = Carbon::parse($data['from'] ?? ($defaultDays > 0 ? today()->subDays($defaultDays - 1) : $to));

        return [$from, $to];
    }
}
