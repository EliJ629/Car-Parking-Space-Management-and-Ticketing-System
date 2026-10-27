<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Runs the three scheduled jobs (reservation expiry and exit window every
 * minute, long-parking detection every 5 minutes). The Laravel scheduler calls
 * the commands on time. Screens also call this, at most once per interval, so
 * the lot stays correct even when the scheduler is not running.
 */
class Sweeper
{
    public function __construct(
        private ReservationService $reservations,
        private GateService $gate,
        private LongParkingService $longParking,
        private IncidentReminders $incidentReminders,
    ) {}

    public function run(bool $force = false): void
    {
        if ($force || Cache::add('bayline:sweep:minute', 1, 30)) {
            $this->reservations->expireDue();
            $this->reservations->holdTodaysSlots();
            $this->gate->expireWindows();
        }

        if ($force || Cache::add('bayline:sweep:long-parking', 1, 300)) {
            $this->longParking->detect();
        }

        if ($force || Cache::add('bayline:sweep:incident-reminders', 1, 600)) {
            $this->incidentReminders->send();
        }
    }
}
