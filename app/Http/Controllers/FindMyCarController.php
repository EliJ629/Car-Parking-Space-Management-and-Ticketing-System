<?php

namespace App\Http\Controllers;

use App\Models\ExitAuthorization;
use App\Models\ParkingSession;
use App\Services\FeeCalculator;
use App\Support\Lot;
use App\Support\PlateNumber;
use App\Support\TicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Find my car": a walk-in types the number printed on the ticket and sees where the car is. No sign in.
 * It answers with the bay, the vehicle kind, a masked plate and the fee so far. It never returns the full plate,
 * a phone number, a name, or anything about other bays, and it is rate limited per visitor.
 */
class FindMyCarController extends Controller
{
    public function __invoke(Request $request, FeeCalculator $fees): JsonResponse
    {
        $input = (string) $request->query('ticket', '');

        // "17", "pk17" and "PK-0017" all mean PK-0017. This field only ever takes a ticket, so a bare number is fine.
        $ticket = TicketNumber::normalize($input, true);

        if ($ticket === null) {
            return response()->json(['state' => 'invalid', 'message' => TicketNumber::MESSAGE], 422);
        }

        $session = ParkingSession::query()->with(['slot', 'payment'])->where('ticket_number', $ticket)->latest('entered_at')->first();

        if (! $session) {
            return response()->json(['state' => 'unknown', 'ticket' => $ticket]);
        }

        if ($session->status === ParkingSession::STATUS_COMPLETED) {
            return response()->json(['state' => 'left', 'ticket' => $ticket, 'left_at' => $this->when($session->exited_at)]);
        }

        $paid = $session->status === ParkingSession::STATUS_PAID;
        $minutes = Lot::minutesBetween($session->entered_at, now());

        return response()->json([
            'state' => $paid ? 'paid' : 'parked',
            'ticket' => $ticket,
            'slot' => $session->slot?->code ?? $session->slot_code,
            'zone' => $session->slot?->zone,
            'vehicle' => trim(($session->vehicle_color ?? '').' '.($session->vehicle_type ?? '')),
            'plate' => PlateNumber::mask($session->plate_number),
            'entered' => $this->when($session->entered_at),
            'parked_for' => Lot::duration($minutes),
            'fee' => $paid
                ? 'Paid '.Lot::peso(ExitAuthorization::paidFor($session)).($session->payment ? ', receipt '.$session->payment->receipt_number : '')
                : Lot::peso($fees->estimate($session, now())['total']).' so far',
        ])->header('Cache-Control', 'no-store');
    }

    private function when(?\Illuminate\Support\Carbon $time): ?string
    {
        $local = Lot::local($time);

        return $local ? ($local->isToday() ? 'Today, ' : $local->format('M j').', ').$local->format('g:i A') : null;
    }
}
