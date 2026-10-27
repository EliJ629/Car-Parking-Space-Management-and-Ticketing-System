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

    /** The wording the manager's brief asks for. The early minutes come from the manager's settings. */
    public function message(ParkingReservation $reservation): string
    {
        $at = Lot::local($reservation->scheduled_at);
        $name = trim((string) ($reservation->driver?->name ?? '')) ?: 'driver';

        return sprintf(
            'Hi %s, you have a reservation scheduled for %s. Please arrive %d minutes before your reserved time to prevent the reservation from expiring and to avoid receiving a warning. Note: Missing three consecutive reservations will result in the loss of the reservation feature for one week.',
            $name,
            $at->format('M j, g:i A'),
            Lot::earlyMinutes(),
        );
    }
}
