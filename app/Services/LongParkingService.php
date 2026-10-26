<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Support\Lot;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic long-parking detection (process document 6.8). Repeat detections of
 * one session update the first incident and never make a second one (A23).
 */
class LongParkingService
{
    public function __construct(private IncidentService $incidents, private NoticeService $notices, private BanService $bans) {}

    /**
     * Scheduled job, every 5 minutes, and again whenever the Manager saves a new threshold. Returns how many new
     * incidents were created. It reads the threshold from Settings each time, so a lower limit catches the sessions
     * that now exceed it and a higher limit never removes an incident that was already recorded.
     *
     * One incident per parking session: the event key long:{session id} is unique in the database, so two runs at the
     * same moment, or a retry, cannot make a second one. A failure on one session is logged and does not stop the rest.
     */
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
                try {
                    if ($this->detectOne($session, $hours)) {
                        $created++;
                    }
                } catch (Throwable $e) {
                    Log::error('Long-parking detection failed for a session.', ['session' => $session->id, 'ticket' => $session->ticket_number, 'error' => $e->getMessage()]);
                    report($e);
                }
            });

        return $created;
    }

    private function detectOne(ParkingSession $session, int $hours): bool
    {
        $existing = Incident::query()
            ->where('session_id', $session->id)
            ->where('category', IncidentService::LONG_PARKED)
            ->oldest('id')
            ->first();

        if ($existing) {
            $existing->update(['detection_count' => $existing->detection_count + 1, 'last_seen_at' => now()]);

            return false;
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
            'attributed_plate' => $session->plate_number,
            'event_key' => 'long:'.$session->id,
            'confirmation' => Incident::PENDING,
            'location' => $slot,
            'description' => "Ticket {$session->ticket_number} entered ".Lot::stamp($session->entered_at)
                .'. Parked '.Lot::duration($minutes)." now, over the {$hours} h limit.",
            'reported_by' => null,
            'reported_at' => now(),
            'detection_count' => 1,
            'last_seen_at' => now(),
        ]);

        if (! $incident->wasRecentlyCreated) {
            return false;
        }

        $driver = $this->notices->driverForSession($session);
        $sent = $driver ? $this->notices->longParkedAuto($session, $incident) !== null : false;

        $incident->update([
            'driver_notified' => $sent,
            'driver_notified_at' => $sent ? now() : null,
            'driver_notice_channel' => $sent ? 'app' : null,
            'driver_notice_result' => $sent ? 'Sent to the driver in the app.' : null,
            'driver_notice_reason' => $sent ? null : ($driver
                ? 'The driver was already notified or the notice is switched off.'
                : 'Walk-in without an account. Use Notify Driver to send a text to the mobile number on the ticket.'),
        ]);

        $this->notices->staffLongParking($session, $incident);
        ActivityLog::record('long_parking_alert', null, null, $incident->code, 'system', [
            'ticket' => $session->ticket_number, 'plate' => $session->plate_number, 'minutes' => $minutes,
        ]);

        // The system's own clock is the evidence, so an automatic alert is confirmed at once and counts toward a ban.
        $this->bans->confirm($incident, null);

        return true;
    }
}
