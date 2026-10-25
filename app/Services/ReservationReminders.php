<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ParkingReservation;
use App\Support\Lot;
use Illuminate\Support\Facades\Log;

/**
 * Texts the driver as the reservation time comes close. Each approved reservation gets one reminder,
 * TEXTBEE_REMINDER_MINUTES before its time (30 by default). A reservation approved later than that
 * is reminded as soon as it is approved, as long as its time has not passed.
 */
class ReservationReminders
{
    public function __construct(private TextBee $textBee) {}

    public function enabled(): bool
    {
        return (bool) config('textbee.enabled') && $this->textBee->configured();
    }

    /** @return array{sent:int,failed:int,skipped:int} */
    public function send(): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        if (! $this->enabled()) {
            return $result;
        }

        $now = now();
        $due = ParkingReservation::query()
            ->with(['driver', 'slot'])
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('approval_status', ParkingReservation::APPROVAL_APPROVED)
            ->whereNull('reminder_sent_at')
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $now->copy()->addMinutes((int) config('textbee.reminder_minutes')))
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        foreach ($due as $reservation) {
            $phone = $reservation->driver?->phone;

            if (TextBee::international($phone) === null) {
                // No usable number. Mark it so it is not tried again every minute.
                $reservation->forceFill(['reminder_sent_at' => $now])->save();
                $result['skipped']++;

                continue;
            }

            try {
                $batch = $this->textBee->send((string) $phone, $this->message($reservation));
            } catch (TextBeeFailed $e) {
                // Left unmarked, so the next run tries again until the reservation time has passed.
                Log::warning('SMS reminder failed', ['reservation' => $reservation->code, 'reason' => $e->getMessage()]);
                $result['failed']++;

                continue;
            }

            $reservation->forceFill(['reminder_sent_at' => now()])->save();
            ActivityLog::record('reservation_reminder_sent', $reservation->driver, null, $reservation->code, 'driver', ['batch' => $batch]);
            $result['sent']++;
        }

        return $result;
    }

    /** Plain letters only, so the message stays in one 160 character text. */
    public function message(ParkingReservation $reservation): string
    {
        $at = Lot::local($reservation->scheduled_at);
        $minutes = max(1, (int) ceil(now()->diffInSeconds($reservation->scheduled_at, true) / 60));
        $slot = $reservation->slot?->code ?? $reservation->slot_code;

        return sprintf(
            'Bayline: bay %s is reserved for you at %s (in about %d min). Show your QR code at the booth. Ref %s. The bay is released %d min after your time.',
            $slot,
            $at->format('g:i A'),
            $minutes,
            $reservation->code,
            Lot::graceMinutes(),
        );
    }
}
