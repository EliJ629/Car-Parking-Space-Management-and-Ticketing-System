<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the Guard and the Manager when an incident is still not resolved 24 hours after it was reported, and again every
 * further 24 hours until it is resolved. Each day gets one notice per role: the group key carries the day number, so a
 * repeat run inside the same day changes nothing.
 */
class IncidentReminders
{
    public const EVERY_HOURS = 24;

    public function __construct(private NoticeService $notices) {}

    /** @return int how many incidents were checked and are overdue */
    public function send(): int
    {
        $overdue = Incident::query()
            ->where('status', '!=', 'resolved')
            ->where('reported_at', '<=', now()->subHours(self::EVERY_HOURS))
            ->orderBy('id')
            ->get();

        foreach ($overdue as $incident) {
            try {
                $days = max(1, (int) floor($incident->reported_at->diffInHours(now()) / self::EVERY_HOURS));
                $this->notices->staffIncidentUnresolved($incident, $days);
            } catch (Throwable $e) {
                Log::error('Incident reminder failed.', ['incident' => $incident->code, 'error' => $e->getMessage()]);
                report($e);
            }
        }

        return $overdue->count();
    }
}
