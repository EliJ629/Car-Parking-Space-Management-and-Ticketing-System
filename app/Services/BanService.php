<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BannedPlate;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;
use App\Support\Lot;
use App\Support\PlateNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The three-incident ban.
 *
 * Which incidents count: an incident counts toward the ban of its attributed plate when
 *   - its category is one of Incident::BAN_CATEGORIES,
 *   - a Manager confirmed it (or the system did, for an automatic long-parking alert), and
 *   - it was confirmed after the last time a ban on that plate was lifted.
 * Open or under-review status does not matter; confirmation does. A dismissed or pending report never counts.
 * Reports of the same event are one incident (Incident::event_key), so a duplicate report adds a note instead.
 *
 * Who is responsible: for vehicle damage it is the responsible vehicle, never the damaged one.
 *
 * Safety against double counting: confirming locks the incident row and only acts when it is still pending,
 * so a repeated click, a retry or two Managers confirming at the same moment count it once.
 */
class BanService
{
    public function __construct(private NoticeService $notices) {}

    public function threshold(): int
    {
        return min(10, max(2, (int) Lot::setting('ban_incident_threshold', 3)));
    }

    /**
     * Every plate that belongs to the same vehicle as this one: the plate itself, the vehicle's current plate and every plate it
     * carried before (a temporary plate that was replaced by the permanent plate). Violations and bans follow the vehicle, so
     * changing the plate never clears them. One query; a plate that was never replaced is just itself.
     *
     * @return array{all: list<string>, current: string}
     */
    public function lineage(?string $plate): array
    {
        $key = PlateNumber::normalize($plate);

        if ($key === '') {
            return ['all' => [], 'current' => ''];
        }

        $rows = DB::table('vehicle_previous_plates as p')
            ->join('vehicles as v', 'v.id', '=', 'p.vehicle_id')
            ->where(fn ($q) => $q->where('p.plate_number', $key)->orWhere('v.plate_number', $key))
            ->get(['p.plate_number as previous', 'v.plate_number as current']);

        if ($rows->isEmpty()) {
            return ['all' => [$key], 'current' => $key];
        }

        $current = (string) ($rows->first()->current ?: $key);
        $all = array_values(array_unique(array_filter(array_merge([$key, $current], $rows->pluck('previous')->all()))));

        return ['all' => $all, 'current' => $current];
    }

    /** The active ban on a plate or on any earlier plate of the same vehicle, or null. */
    public function activeBan(?string $plate): ?BannedPlate
    {
        $plates = $this->lineage($plate)['all'];

        return $plates === [] ? null : BannedPlate::query()->active()->whereIn('plate_number', $plates)->first();
    }

    public function isBanned(?string $plate): bool
    {
        return $this->activeBan($plate) !== null;
    }

    /** @throws VehicleBanned */
    public function assertAllowed(?string $plate): void
    {
        if ($this->isBanned($plate)) {
            throw new VehicleBanned;
        }
    }

    /** The confirmed incidents that count for a plate right now. */
    public function counted(?string $plate): Collection
    {
        $plates = $this->lineage($plate)['all'];

        if ($plates === []) {
            return collect();
        }

        // Incidents on the vehicle's earlier plates count too. A lifted ban on any of its plates restarts the count.
        $since = BannedPlate::query()->whereIn('plate_number', $plates)->max('lifted_at');

        return Incident::query()
            ->whereIn('attributed_plate', $plates)
            ->whereIn('category', Incident::BAN_CATEGORIES)
            ->where('confirmation', Incident::CONFIRMED)
            ->when($since, fn ($q) => $q->where('confirmed_at', '>', $since))
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();
    }

    public function count(?string $plate): int
    {
        return $this->counted($plate)->count();
    }

    /**
     * A Manager confirms a report. Returns what happened so the screen can say it.
     *
     * @return array{changed:bool,counts:bool,count:int,banned:bool,plate:?string}
     */
    public function confirm(Incident $incident, ?User $by, ?string $note = null, ?Request $request = null): array
    {
        $result = DB::transaction(function () use ($incident, $by, $note) {
            $locked = Incident::query()->lockForUpdate()->find($incident->id);

            if (! $locked || $locked->confirmation !== Incident::PENDING) {
                return ['changed' => false, 'counts' => false, 'count' => 0, 'banned' => false, 'plate' => $locked?->attributed_plate, 'incident' => $locked];
            }

            $locked->update([
                'confirmation' => Incident::CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $by?->id,
                'confirmation_note' => $note !== null ? mb_substr(trim($note), 0, 255) : null,
            ]);

            $plate = $locked->attributed_plate;
            $counts = $plate && in_array($locked->category, Incident::BAN_CATEGORIES, true);
            $count = $counts ? $this->count($plate) : 0;
            $banned = false;

            if ($counts && $count >= $this->threshold()) {
                $banned = $this->banInside($plate, $count, $locked, $by);
            }

            return ['changed' => true, 'counts' => $counts, 'count' => $count, 'banned' => $banned, 'plate' => $plate, 'incident' => $locked];
        });

        $locked = $result['incident'];
        unset($result['incident']);

        if (! $result['changed'] || ! $locked) {
            return $result;
        }

        IncidentNote::create([
            'incident_id' => $locked->id,
            'user_id' => $by?->id,
            'kind' => 'system',
            'body' => $by ? "Confirmed by {$by->name}.".($note ? ' '.trim($note) : '') : 'Confirmed by the system from the parking record.',
        ]);

        ActivityLog::record('incident_confirmed', $by, $request, $locked->code, $by ? 'manager' : 'system', [
            'plate' => $result['plate'], 'counts_toward_ban' => $result['counts'], 'count' => $result['count'], 'banned' => $result['banned'],
        ]);

        if ($result['counts'] && $result['plate']) {
            $this->tellResponsibleVehicle($locked->fresh(), $result['plate'], $result['count'], $result['banned']);
        }

        return $result;
    }

    /** A Manager decides a report is not valid. It stays on file and never counts. */
    public function dismiss(Incident $incident, User $by, string $note, ?Request $request = null): bool
    {
        $changed = DB::transaction(function () use ($incident, $by, $note) {
            $locked = Incident::query()->lockForUpdate()->find($incident->id);

            if (! $locked || $locked->confirmation !== Incident::PENDING) {
                return false;
            }

            $locked->update([
                'confirmation' => Incident::DISMISSED,
                'confirmed_at' => now(),
                'confirmed_by' => $by->id,
                'confirmation_note' => mb_substr(trim($note), 0, 255),
            ]);

            return true;
        });

        if ($changed) {
            IncidentNote::create(['incident_id' => $incident->id, 'user_id' => $by->id, 'kind' => 'system', 'body' => "Dismissed by {$by->name}. It does not count toward a ban. ".trim($note)]);
            ActivityLog::record('incident_dismissed', $by, $request, $incident->code, 'manager', ['note' => $note]);
        }

        return $changed;
    }

    /** The Manager lifts a ban. The incidents stay on file but only newer confirmed ones count again. */
    public function lift(BannedPlate $ban, User $by, string $note, ?Request $request = null): bool
    {
        $changed = DB::transaction(function () use ($ban, $by, $note) {
            $locked = BannedPlate::query()->lockForUpdate()->find($ban->id);

            if (! $locked || ! $locked->isActive()) {
                return false;
            }

            $locked->update(['lifted_at' => now(), 'lifted_by' => $by->id, 'lift_note' => mb_substr(trim($note), 0, 255)]);

            return true;
        });

        if ($changed) {
            ActivityLog::record('ban_lifted', $by, $request, $ban->plate_number, 'manager', ['note' => $note]);
        }

        return $changed;
    }

    /** Create or switch on the ban for a plate. Runs inside the confirm transaction. */
    private function banInside(string $plate, int $count, Incident $trigger, ?User $by): bool
    {
        // The ban goes on the vehicle's current plate, even when the incident was reported under an earlier temporary plate.
        $plate = $this->lineage($plate)['current'] ?: $plate;
        $owner = $this->notices->driverForPlate($plate);
        $reason = "{$count} confirmed incidents. The last one is {$trigger->code}.";

        $ban = BannedPlate::query()->lockForUpdate()->where('plate_number', $plate)->first();

        if ($ban && $ban->isActive()) {
            $ban->update(['incident_count' => $count]);

            return false;
        }

        // The vehicle is already banned under one of its earlier plates: that ban applies to the new plate too.
        if ($this->activeBan($plate)) {
            return false;
        }

        $attributes = [
            'reason' => $reason,
            'incident_count' => $count,
            // Only an account that saved this exact plate as its own vehicle is linked. That link is the evidence.
            'user_id' => $owner?->id,
            'banned_at' => now(),
            'banned_by' => $by?->id,
            'lifted_at' => null,
            'lifted_by' => null,
            'lift_note' => null,
        ];

        try {
            $ban
                ? $ban->update($attributes)
                : BannedPlate::create(['plate_number' => $plate] + $attributes);
        } catch (UniqueConstraintViolationException) {
            // Another request banned the same plate at the same moment. The ban exists, which is the goal.
            return false;
        }

        ActivityLog::record('vehicle_banned', $by, null, $plate, $by ? 'manager' : 'system', [
            'incident' => $trigger->code, 'count' => $count, 'account' => $owner?->id,
        ]);

        return true;
    }

    /** Warning 1 of 3, warning 2 of 3, then the ban notice. Goes to the responsible vehicle, never the damaged one. */
    private function tellResponsibleVehicle(Incident $incident, string $plate, int $count, bool $banned): void
    {
        $shown = Lot::plate($plate);
        $limit = $this->threshold();
        $what = strtolower(Incident::guardLabel($incident->category));

        [$title, $body, $kind] = $banned || $count >= $limit
            ? ['Vehicle banned from parking', "Vehicle {$shown} has {$count} confirmed incidents ({$what}, report {$incident->code}) and can no longer reserve or park in the lot. Please contact the Parking Manager.", 'vehicle_banned']
            : ["Parking warning {$count} of {$limit}", "Report {$incident->code} ({$what}) was confirmed for vehicle {$shown}. This is warning {$count} of {$limit}. At {$limit} confirmed incidents the vehicle is banned from parking.", 'incident_warning'];

        $delivery = $this->notices->deliverToVehicle($plate, $kind === 'vehicle_banned' ? 'N-17' : 'N-16', $kind, $title, $body, $incident, "{$kind}-{$incident->id}");

        IncidentNote::create([
            'incident_id' => $incident->id,
            'user_id' => null,
            'kind' => 'system',
            'body' => ($kind === 'vehicle_banned' ? 'Ban notice. ' : "Warning {$count} of {$limit}. ").$delivery['result'],
        ]);
    }
}
