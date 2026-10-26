<?php

namespace App\Services;

use App\Models\ParkingRateSet;
use App\Models\ParkingSession;
use App\Support\Lot;
use Carbon\CarbonInterface;

/**
 * The fee rules of the process document, section 7.2.
 *
 * Duration in whole minutes, split into full 24 hour blocks plus a remainder,
 * started hours, first hour plus additional hours, the daily maximum on each
 * block, then penalties, then any amount already paid. A discount is applied
 * by the booth attendant, so a driver's estimate shows no discount.
 */
class FeeCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function estimate(ParkingSession $session, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $rate = ParkingRateSet::effectiveAt(now()->setTimestamp($at->getTimestamp())) ?? $this->defaultRate();

        $first = (float) $rate->first_hour_fee;
        $extra = (float) $rate->additional_hour_fee;
        $max = (float) $rate->daily_maximum;

        $minutes = Lot::minutesBetween($session->entered_at, $at);
        $blocks = intdiv($minutes, 1440);
        $remainder = $minutes % 1440;

        $hourly = fn (int $hours): float => $hours > 0 ? $first + $extra * ($hours - 1) : 0.0;

        $segments = array_fill(0, $blocks, 24);
        $remainderHours = $remainder === 0 && $blocks > 0 ? 0 : max(1, (int) ceil($remainder / 60));

        if ($remainderHours > 0) {
            $segments[] = $remainderHours;
        }

        $uncapped = 0.0;
        $capped = 0.0;
        $baseLines = 0;
        $extraHours = 0;

        foreach ($segments as $hours) {
            $uncapped += $hourly($hours);
            $capped += min($hourly($hours), $max);
            $baseLines++;
            $extraHours += $hours - 1;
        }

        $base = $first * $baseLines;
        $additional = $uncapped - $base;
        $capAdjustment = $capped - $uncapped;

        $lostPenalty = $session->lost_ticket ? (float) $rate->lost_ticket_penalty : 0.0;
        $overstayPenalty = $session->exit_window_expired_at ? (float) $rate->overstay_penalty : 0.0;

        // After a permission ended, what was paid before counts toward the new total. The ledger holds every earlier payment.
        $alreadyPaid = $session->exit_window_expired_at && $session->payment
            ? \App\Models\ExitAuthorization::paidFor($session)
            : 0.0;

        $discount = 0.0;
        $total = max(0.0, round($capped - $discount + $lostPenalty + $overstayPenalty - $alreadyPaid, 2));

        return [
            'minutes' => $minutes,
            'hours_started' => array_sum($segments),
            'first_hour' => round($base, 2),
            'additional_hours' => $extraHours,
            'additional_unit' => $extra,
            'additional' => round($additional, 2),
            'cap_adjustment' => round($capAdjustment, 2),
            'parking_charge' => round($capped, 2),
            'discount' => $discount,
            'lost_ticket_penalty' => $lostPenalty,
            'overstay_penalty' => $overstayPenalty,
            'penalties' => $lostPenalty + $overstayPenalty,
            'already_paid' => $alreadyPaid,
            'total' => $total,
            'daily_maximum' => $max,
            'capped' => $capAdjustment < 0,
        ];
    }

    /** The shipped defaults, used only when the manager has not saved a rate yet. */
    private function defaultRate(): ParkingRateSet
    {
        return new ParkingRateSet([
            'first_hour_fee' => 30,
            'additional_hour_fee' => 20,
            'daily_maximum' => 300,
            'lost_ticket_penalty' => 100,
            'overstay_penalty' => 150,
        ]);
    }
}
