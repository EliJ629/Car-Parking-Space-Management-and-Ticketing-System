<?php

namespace App\Http\Controllers\Manager;

use App\Models\Incident;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Support\Csv;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends ManagerController
{
    public function sales(Request $request): View
    {
        [$from, $to] = $this->range($request);
        [$start, $end] = $this->bounds($from, $to);
        $payments = ParkingPayment::query()->with(['session.attendant', 'attendant'])
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get();
        $local = Lot::now();
        $today = ParkingPayment::whereBetween('paid_at', [Lot::dayStart(), Lot::dayEnd()])->sum('total');
        $week = ParkingPayment::whereBetween('paid_at', [$local->copy()->startOfWeek()->setTimezone('UTC'), $local->copy()->endOfWeek()->setTimezone('UTC')])->sum('total');
        $month = ParkingPayment::whereBetween('paid_at', [$local->copy()->startOfMonth()->setTimezone('UTC'), $local->copy()->endOfMonth()->setTimezone('UTC')])->sum('total');
        $byDay = $payments->groupBy(fn (ParkingPayment $payment): string => Lot::local($payment->paid_at)->toDateString())
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
        [$start, $end] = $this->bounds($from, $to);
        $sessions = ParkingSession::query()->with('payment')
            ->where('entered_at', '<=', $end)
            ->where(fn ($query) => $query->whereNull('exited_at')->orWhere('exited_at', '>=', $start))
            ->get();
        $capacity = ParkingSlot::query()->where('status', '!=', ParkingSlot::STATUS_OUT_OF_SERVICE)->count();
        $capacityMinutes = max(1, $capacity * $start->diffInMinutes($end));
        $occupiedMinutes = $sessions->sum(function (ParkingSession $session) use ($start, $end): int {
            $from = $session->entered_at->max($start);
            $to = ($session->exited_at ?? now())->min($end);

            return max(0, (int) $from->diffInMinutes($to, false));
        });
        // Entries counted by the hour they happened at the lot, not in UTC.
        $entered = $sessions->filter(fn (ParkingSession $session): bool => $session->entered_at->betweenIncluded($start, $end));
        $hourly = array_fill(8, 13, 0);
        foreach ($entered as $session) {
            $hour = (int) Lot::local($session->entered_at)->format('G');
            if (array_key_exists($hour, $hourly)) {
                $hourly[$hour]++;
            }
        }
        $reservationQuery = ParkingReservation::query()->whereBetween('scheduled_at', [$start, $end]);
        $reservationTotal = (clone $reservationQuery)->count();
        $kept = (clone $reservationQuery)->where('status', 'checked_in')->count();
        $incidents = Incident::query()->whereBetween('reported_at', [$start, $end])->selectRaw('category, COUNT(*) AS aggregate')->groupBy('category')->pluck('aggregate', 'category');
        $peakHour = max($hourly) > 0 ? collect($hourly)->sortDesc()->keys()->first() : null;

        return $this->managerPage('Occupancy and activity report', 'reports', view('staff.manager.reports.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sessionCount' => $sessions->count(),
            'enteredCount' => $entered->count(),
            'averageOccupancy' => $capacity > 0 ? min(100, round($occupiedMinutes / $capacityMinutes * 100)) : 0,
            'averageStayMinutes' => $sessions->filter(fn (ParkingSession $session): bool => $session->exited_at !== null)
                ->average(fn (ParkingSession $session): float => $session->entered_at->diffInMinutes($session->exited_at)) ?? 0,
            'peakHour' => $peakHour === null ? null : sprintf('%02d:00', $peakHour),
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], $this->rangeMessages());
        [$from, $to] = $this->range($request);
        [$start, $end] = $this->bounds($from, $to);

        return response()->streamDownload(function () use ($report, $start, $end): void {
            $output = fopen('php://output', 'w');

            if ($report === 'sales') {
                fputcsv($output, ['Date', 'Receipt', 'Ticket', 'Plate', 'Base', 'Additional', 'Penalty', 'Discount', 'Total']);
                ParkingPayment::with('session')->whereBetween('paid_at', [$start, $end])
                    ->orderBy('paid_at')->chunk(250, function ($payments) use ($output): void {
                        foreach ($payments as $payment) {
                            fputcsv($output, Csv::row([
                                Lot::local($payment->paid_at)->toDateString(), $payment->receipt_number,
                                $payment->session?->ticket_number, $payment->session?->plate_number,
                                $payment->base_fee, $payment->additional_fee, $payment->penalty,
                                $payment->discount, $payment->total,
                            ]));
                        }
                    });
            } else {
                fputcsv($output, ['Ticket', 'Plate', 'Slot', 'Entry', 'Exit', 'Status']);
                ParkingSession::with('slot')->whereBetween('entered_at', [$start, $end])
                    ->orderBy('entered_at')->chunk(250, function ($sessions) use ($output): void {
                        foreach ($sessions as $session) {
                            fputcsv($output, Csv::row([
                                $session->ticket_number, $session->plate_number, $session->slot?->code,
                                Lot::local($session->entered_at)->format('Y-m-d H:i:s'), Lot::local($session->exited_at)?->format('Y-m-d H:i:s'),
                                $session->status,
                            ]));
                        }
                    });
            }

            fclose($output);
        }, "bayline-{$report}-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Longest range a screen or export may cover. */
    private const MAX_DAYS = 366;

    /** @return array<string, string> */
    private function rangeMessages(): array
    {
        return [
            'from.date_format' => 'Enter the start date as year-month-day.',
            'from.required' => 'Choose a start date.',
            'to.date_format' => 'Enter the end date as year-month-day.',
            'to.required' => 'Choose an end date.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }

    /**
     * Lot-local calendar days, each at midnight in the lot's time zone.
     *
     * @return array{Carbon, Carbon}
     */
    private function range(Request $request, int $defaultDays = 0): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01', 'after_or_equal:from'],
        ], $this->rangeMessages());
        $today = Lot::now()->startOfDay();
        $from = isset($data['from']) ? Carbon::parse($data['from'], Lot::TIMEZONE)->startOfDay() : null;
        $to = isset($data['to'])
            ? Carbon::parse($data['to'], Lot::TIMEZONE)->startOfDay()
            : ($from && $from->greaterThan($today) ? $from->copy() : $today);
        $from ??= $defaultDays > 0 ? $to->copy()->subDays($defaultDays - 1) : $to->copy();

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a range of '.self::MAX_DAYS.' days or fewer.']);
        }

        return [$from, $to];
    }

    /** UTC start and end that cover the local days from..to. */
    private function bounds(Carbon $from, Carbon $to): array
    {
        return [Lot::parse($from->toDateString()), Lot::parse($to->toDateString(), '23:59:59')];
    }
}
