<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Support\Lot;

/**
 * Automatic long-parking detection (process document 6.8). Repeat detections of
 * one session update the first incident and never make a second one (A23).
 */
class LongParkingService
{
    public function __construct(private IncidentService $incidents, private NoticeService $notices) {}

    /** Scheduled job, every 5 minutes. Returns how many new incidents were created. */
    public function detect(): int
    {
        $hours = Lot::longParkingHours();
        $created = 0;

        ParkingSession::query()
            ->where('status', ParkingSession::STATUS_ACTIVE)
            ->where('entered_at', '<=', now()->subHours($hours))
            ->with('slot')
            ->get()
            ->each(function (ParkingSession $session) use ($hours, &$created): void {
                $existing = Incident::query()
                    ->where('session_id', $session->id)
                    ->where('category', IncidentService::LONG_PARKED)
                    ->oldest('id')
                    ->first();

                if ($existing) {
                    $existing->update(['detection_count' => $existing->detection_count + 1, 'last_seen_at' => now()]);

                    return;
                }

                $minutes = Lot::minutesBetween($session->entered_at);
                $slot = $session->slot?->code ?? $session->slot_code;

                $incident = $this->incidents->create([
                    'category' => IncidentService::LONG_PARKED,
                    'source' => 'auto',
                    'status' => 'open',
                    'session_id' => $session->id,
                    'slot_id' => $session->slot_id,
                    'plate_number' => $session->plate_number,
                    'location' => $slot,
                    'description' => "Ticket {$session->ticket_number} entered ".Lot::stamp($session->entered_at)
                        .'. Parked '.Lot::duration($minutes)." now, over the {$hours} h limit.",
                    'reported_by' => null,
                    'reported_at' => now(),
                    'detection_count' => 1,
                    'last_seen_at' => now(),
                ]);

                $driver = $this->notices->driverForSession($session);
                $sent = $driver ? $this->notices->longParkedAuto($session, $incident) !== null : false;

                $incident->update([
                    'driver_notified' => $sent,
                    'driver_notice_reason' => $sent ? null : ($driver
                        ? 'The driver was already notified or the notice is switched off.'
                        : 'Walk-in without an account. The phone number stays on the session.'),
                ]);

                $this->notices->staffLongParking($session, $incident);
                ActivityLog::record('long_parking_alert', null, null, $incident->code, 'system', [
                    'ticket' => $session->ticket_number, 'plate' => $session->plate_number, 'minutes' => $minutes,
                ]);
                $created++;
            });

        return $created;
    }
}
