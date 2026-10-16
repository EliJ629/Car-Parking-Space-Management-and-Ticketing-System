<?php

namespace App\Http\Controllers\Manager;

use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordsController extends ManagerController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:sessions,payments,reservations'],
            'date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:24'],
        ]);
        $tab = $filters['tab'] ?? 'sessions';
        $date = Carbon::parse($filters['date'] ?? today())->toDateString();
        $search = trim($filters['q'] ?? '');

        $sessions = ParkingSession::query()->with(['slot', 'payment', 'attendant'])
            ->whereDate('entered_at', $date)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")
                ->orWhereHas('slot', fn ($slots) => $slots->where('code', 'like', "%{$search}%"))))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest('entered_at')->paginate(15)->withQueryString();

        $payments = ParkingPayment::query()->with(['session.slot', 'attendant'])
            ->whereDate('paid_at', $date)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('receipt_number', 'like', "%{$search}%")
                ->orWhereHas('session', fn ($sessions) => $sessions
                    ->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%"))))
            ->latest('paid_at')->paginate(15)->withQueryString();

        $reservations = ParkingReservation::query()->with(['slot', 'driver'])
            ->whereDate('scheduled_at', $date)
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
            'date' => ['nullable', 'date'],
        ]);
        $tab = $filters['tab'] ?? 'sessions';
        $date = Carbon::parse($filters['date'] ?? today())->toDateString();

        return response()->streamDownload(function () use ($tab, $date): void {
            $output = fopen('php://output', 'w');

            if ($tab === 'payments') {
                fputcsv($output, ['Receipt', 'Ticket', 'Plate', 'Paid at', 'Base', 'Additional', 'Penalty', 'Discount', 'Total', 'Attendant']);
                ParkingPayment::with(['session', 'attendant'])->whereDate('paid_at', $date)->orderBy('paid_at')->chunk(250, function ($payments) use ($output): void {
                    foreach ($payments as $payment) {
                        fputcsv($output, [
                            $payment->receipt_number, $payment->session?->ticket_number, $payment->session?->plate_number,
                            $payment->paid_at->toDateTimeString(), $payment->base_fee, $payment->additional_fee,
                            $payment->penalty, $payment->discount, $payment->total, $payment->attendant?->name,
                        ]);
                    }
                });
            } elseif ($tab === 'reservations') {
                fputcsv($output, ['Code', 'Plate', 'Slot', 'Scheduled at', 'Status']);
                ParkingReservation::with('slot')->whereDate('scheduled_at', $date)->orderBy('scheduled_at')->chunk(250, function ($reservations) use ($output): void {
                    foreach ($reservations as $reservation) {
                        fputcsv($output, [
                            $reservation->code, $reservation->plate_number, $reservation->slot?->code ?? $reservation->slot_code,
                            $reservation->scheduled_at->toDateTimeString(), $reservation->status,
                        ]);
                    }
                });
            } else {
                fputcsv($output, ['Ticket', 'Plate', 'Color', 'Type', 'Slot', 'Entry', 'Exit', 'Status', 'Payment']);
                ParkingSession::with(['slot', 'payment'])->whereDate('entered_at', $date)->orderBy('entered_at')->chunk(250, function ($sessions) use ($output): void {
                    foreach ($sessions as $session) {
                        fputcsv($output, [
                            $session->ticket_number, $session->plate_number, $session->vehicle_color, $session->vehicle_type,
                            $session->slot?->code ?? $session->slot_code, $session->entered_at->toDateTimeString(), $session->exited_at?->toDateTimeString(),
                            $session->status, $session->payment?->total,
                        ]);
                    }
                });
            }

            fclose($output);
        }, "bayline-{$tab}-{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
