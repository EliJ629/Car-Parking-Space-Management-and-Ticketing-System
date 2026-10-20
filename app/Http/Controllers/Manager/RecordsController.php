<?php

namespace App\Http\Controllers\Manager;

use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Support\Csv;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordsController extends ManagerController
{
    /** Statuses a record can have, sessions and reservations together. */
    public const STATUSES = ['active', 'paid', 'completed', 'booked', 'checked_in', 'expired', 'canceled'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:sessions,payments,reservations'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ], [
            'date.date_format' => 'Enter the date as year-month-day.',
            'date.after' => 'Choose a date after the year 2000.',
            'date.before' => 'Choose a date before the year 2100.',
            'status.in' => 'Choose a status from the list.',
            'q.max' => 'The search can have up to 100 characters.',
        ]);
        $tab = $filters['tab'] ?? 'sessions';
        $date = $filters['date'] ?? Lot::now()->toDateString();
        [$from, $to] = [Lot::parse($date), Lot::parse($date, '23:59:59')];
        $search = trim($filters['q'] ?? '');

        $sessions = ParkingSession::query()->with(['slot', 'payment', 'attendant'])
            ->whereBetween('entered_at', [$from, $to])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")
                ->orWhereHas('slot', fn ($slots) => $slots->where('code', 'like', "%{$search}%"))))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest('entered_at')->paginate(15)->withQueryString();

        $payments = ParkingPayment::query()->with(['session.slot', 'attendant'])
            ->whereBetween('paid_at', [$from, $to])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('receipt_number', 'like', "%{$search}%")
                ->orWhereHas('session', fn ($sessions) => $sessions
                    ->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%"))))
            ->latest('paid_at')->paginate(15)->withQueryString();

        $reservations = ParkingReservation::query()->with(['slot', 'driver'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest('scheduled_at')->paginate(15)->withQueryString();

        return $this->managerPage('Parking records', 'records', view('staff.manager.records.index', [
            'tab' => $tab,
            'date' => $date,
            'search' => $search,
            'status' => $filters['status'] ?? '',
            'sessions' => $sessions,
            'payments' => $payments,
            'reservations' => $reservations,
        ]));
    }

    public function showSession(ParkingSession $session): View
    {
        $session->load(['slot', 'payment.attendant', 'attendant', 'driver']);

        return $this->managerPage("Session {$session->ticket_number}", 'records', view('staff.manager.records.session', [
            'session' => $session,
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:sessions,payments,reservations'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
        ]);
        $tab = $filters['tab'] ?? 'sessions';
        $date = $filters['date'] ?? Lot::now()->toDateString();
        [$from, $to] = [Lot::parse($date), Lot::parse($date, '23:59:59')];

        return response()->streamDownload(function () use ($tab, $from, $to): void {
            $output = fopen('php://output', 'w');

            if ($tab === 'payments') {
                fputcsv($output, ['Receipt', 'Ticket', 'Plate', 'Paid at', 'Base', 'Additional', 'Penalty', 'Discount', 'Total', 'Attendant']);
                ParkingPayment::with(['session', 'attendant'])->whereBetween('paid_at', [$from, $to])->orderBy('paid_at')->chunk(250, function ($payments) use ($output): void {
                    foreach ($payments as $payment) {
                        fputcsv($output, Csv::row([
                            $payment->receipt_number, $payment->session?->ticket_number, $payment->session?->plate_number,
                            Lot::local($payment->paid_at)->format('Y-m-d H:i:s'), $payment->base_fee, $payment->additional_fee,
                            $payment->penalty, $payment->discount, $payment->total, $payment->attendant?->name,
                        ]));
                    }
                });
            } elseif ($tab === 'reservations') {
                fputcsv($output, ['Code', 'Plate', 'Slot', 'Scheduled at', 'Status']);
                ParkingReservation::with('slot')->whereBetween('scheduled_at', [$from, $to])->orderBy('scheduled_at')->chunk(250, function ($reservations) use ($output): void {
                    foreach ($reservations as $reservation) {
                        fputcsv($output, Csv::row([
                            $reservation->code, $reservation->plate_number, $reservation->slot?->code ?? $reservation->slot_code,
                            Lot::local($reservation->scheduled_at)->format('Y-m-d H:i:s'), $reservation->status,
                        ]));
                    }
                });
            } else {
                fputcsv($output, ['Ticket', 'Plate', 'Color', 'Type', 'Slot', 'Entry', 'Exit', 'Status', 'Payment']);
                ParkingSession::with(['slot', 'payment'])->whereBetween('entered_at', [$from, $to])->orderBy('entered_at')->chunk(250, function ($sessions) use ($output): void {
                    foreach ($sessions as $session) {
                        fputcsv($output, Csv::row([
                            $session->ticket_number, $session->plate_number, $session->vehicle_color, $session->vehicle_type,
                            $session->slot?->code ?? $session->slot_code, Lot::local($session->entered_at)->format('Y-m-d H:i:s'), Lot::local($session->exited_at)?->format('Y-m-d H:i:s'),
                            $session->status, $session->payment?->total,
                        ]));
                    }
                });
            }

            fclose($output);
        }, "bayline-{$tab}-{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
