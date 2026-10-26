<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\ParkingSession;
use App\Services\GateException;
use App\Services\GateService;
use App\Support\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gate validation: exit queue, manual search, exit detail with the three checks, Good to Go. */
class GateController extends Controller
{
    public function index(Request $request, GateService $gate): View
    {
        $tab = $request->query('tab') === 'search' ? 'search' : 'queue';
        $queue = $gate->pendingExits();

        $data = [
            'tab' => $tab,
            'queue' => $queue->each(fn (ParkingSession $s) => $s->setAttribute('minutes_left', $gate->minutesLeft($s))),
            'approvedToday' => $gate->approvedToday(),
            'longAlerts' => $gate->openLongParkingAlerts(),
            'gate' => $gate,
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 20),
            'result' => null,
            'state' => null,
            'due' => null,
            'searched' => false,
        ];

        if ($tab === 'search' && $data['q'] !== '') {
            $data['searched'] = true;
            $result = $gate->search($data['q']);
            $data['result'] = $result;

            if ($result) {
                $data['state'] = match ($result->status) {
                    ParkingSession::STATUS_PAID => 'paid',
                    ParkingSession::STATUS_COMPLETED => 'exited',
                    default => 'unpaid',
                };
                $data['due'] = $data['state'] === 'unpaid' ? $gate->amountDue($result) : null;
            }
        }

        return view('guard.gate.index', $data);
    }

    /** Polled by the queue page. The page reloads itself when the list changes. */
    public function feed(GateService $gate): JsonResponse
    {
        $queue = $gate->pendingExits();

        return response()->json([
            'ids' => $queue->pluck('id')->all(),
            'waiting' => $queue->count(),
            'approved' => $gate->approvedToday(),
            'left' => $queue->mapWithKeys(fn (ParkingSession $s) => [$s->id => $gate->minutesLeft($s)])->all(),
        ]);
    }

    public function show(Request $request, ParkingSession $session, GateService $gate): View
    {
        $session->load(['payment.attendant', 'slot', 'exitApprover']);

        if ($session->status === ParkingSession::STATUS_COMPLETED) {
            return view('guard.gate.exited', ['session' => $session, 'justApproved' => (bool) $request->query('done')]);
        }

        if ($session->status === ParkingSession::STATUS_ACTIVE) {
            return view('guard.gate.blocked', [
                'session' => $session,
                'due' => $gate->amountDue($session),
                'windowEnded' => $session->exit_window_expired_at !== null,
            ]);
        }

        return view('guard.gate.show', [
            'session' => $session,
            'windowEnd' => $gate->windowEnd($session),
            'minutesLeft' => $gate->minutesLeft($session),
            'missing' => (array) session('missing', []),
        ]);
    }

    public function approve(Request $request, ParkingSession $session, GateService $gate): RedirectResponse
    {
        $checks = array_map('boolval', (array) $request->input('checks', []));

        try {
            $gate->approve($session, $request->user(), $checks, $request);
        } catch (GateException $e) {
            // The minute job may have reopened the session just before this tap.
            if ($e->reason === 'not_paid' && $session->fresh()?->exit_window_expired_at) {
                $e = new GateException('window_ended', 'The exit window ended. The session is open again and the vehicle goes back to the booth.');
            }

            return match ($e->reason) {
                'checks' => redirect()->route('guard.gate.show', $session)
                    ->with('missing', array_values(array_diff($gate::CHECKS, array_keys(array_filter($checks)))))
                    ->withErrors(['checks' => $e->getMessage()]),
                'window_ended' => redirect()->route('guard.gate')->with('modal', [
                    'title' => 'The exit window ended',
                    'body' => $e->getMessage(),
                    'note' => 'The booth attendant checks the amount again. If the driver already paid, the earlier payment is credited.',
                    'primary' => ['label' => 'Back to queue', 'close' => true],
                    'tone' => 'stop',
                ]),
                default => redirect()->route('guard.gate.show', $session)->withErrors(['checks' => $e->getMessage()]),
            };
        }

        return redirect()->route('guard.gate.show', [$session, 'done' => 1]);
    }

    public function mismatch(Request $request, ParkingSession $session, GateService $gate): RedirectResponse
    {
        $labels = ['plate' => 'Plate number', 'details' => 'Color or vehicle type', 'ticket' => 'Ticket number'];
        $reasons = array_values(array_intersect(array_keys($labels), (array) $request->input('reasons', [])));

        if ($reasons === []) {
            return redirect()->route('guard.gate.show', $session)->with('modal', [
                'title' => 'Choose what does not match',
                'body' => 'Tick at least one: plate number, color or type, or ticket number.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        $gate->recordMismatch($session, $request->user(), $reasons, $request);
        $text = collect($reasons)->map(fn (string $r) => strtolower($labels[$r]))->join(', ', ' and ');

        return redirect()->route('guard.incidents.create', [
            'category' => 'Mismatched plate or details',
            'plate' => $session->plate_number,
            'location' => $session->slot?->code ?? $session->slot_code,
            'session' => $session->id,
            'description' => "Exit stopped for ticket {$session->ticket_number}. At the gate the {$text} did not match the record ("
                .Lot::plate($session->plate_number).', '.strtolower(trim(($session->vehicle_color ?? '').' '.($session->vehicle_type ?? ''))).').',
        ]);
    }
}
