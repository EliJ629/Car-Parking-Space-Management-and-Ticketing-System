<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\IncidentPhoto;
use App\Models\ParkingSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Lot;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Incident reports written by the Security Guard (process document 6.9 and 6.10)
 * and the status handling on the Guard screens.
 */
class IncidentService
{
    public const LONG_PARKED = 'Long-parked vehicle';

    /** A second driver notice about the same incident is refused for this many minutes. */
    public const NOTIFY_COOLDOWN_MINUTES = 15;

    public function __construct(private NoticeService $notices) {}

    /** INC-0001 style, in order. Codes written by older screens are skipped over. */
    public function nextCode(): string
    {
        $highest = 0;

        foreach (Incident::query()->where('code', 'like', 'INC-%')->pluck('code') as $code) {
            if (preg_match('/^INC-(\d+)$/', $code, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        return 'INC-'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create an incident with a free code, retrying if two are made at the same moment. When the incident has an
     * event key and one with that key exists already, the existing one is returned (wasRecentlyCreated is false).
     */
    public function create(array $attributes): Incident
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(fn () => Incident::create($attributes + ['code' => $this->nextCode()]));
            } catch (UniqueConstraintViolationException $e) {
                if (! empty($attributes['event_key']) && ($existing = Incident::query()->where('event_key', $attributes['event_key'])->first())) {
                    return $existing;
                }

                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \LogicException('unreachable');
    }

    /**
     * Slots with a vehicle in them right now, for the incident form. Only these can be chosen.
     *
     * @return \Illuminate\Support\Collection<int, array{code:string,plate:string,plate_label:string,session_id:int}>
     */
    public function occupiedSlots(): \Illuminate\Support\Collection
    {
        return ParkingSession::query()
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->with('slot')
            ->get()
            ->map(fn (ParkingSession $s) => [
                'code' => (string) ($s->slot?->code ?? $s->slot_code),
                'plate' => $s->plate_number,
                'plate_label' => Lot::plate($s->plate_number),
                'session_id' => $s->id,
            ])
            ->filter(fn (array $row) => $row['code'] !== '')
            ->sortBy('code')
            ->unique('code')
            ->values();
    }

    /**
     * A manual report by a Guard or Manager. The vehicle and slot fill in when the plate has an open session.
     * For vehicle damage, plate_number is the damaged vehicle and responsible_plate is the vehicle at fault; the
     * incident counts against the responsible vehicle only. A second report of the same damage on the same day
     * between the same two vehicles is added to the first one as a follow-up and is not a new incident.
     *
     * @param  array<string, mixed>  $data  category, plate_number, location, description, session_id, notify_driver,
     *                                      responsible_plate, other_label
     * @param  array<int, UploadedFile>  $photos
     */
    public function createManual(User $guard, array $data, array $photos = [], ?Request $request = null): Incident
    {
        $plate = Vehicle::normalizePlate($data['plate_number'] ?? '');
        $session = ! empty($data['session_id'])
            ? ParkingSession::query()->with('slot')->find($data['session_id'])
            : ($plate !== '' ? $this->openSession($plate) : null);

        if ($session && $plate === '') {
            $plate = $session->plate_number;
        }

        $category = $data['category'];
        $isDamage = $category === 'Vehicle damage';
        $responsible = $isDamage ? Vehicle::normalizePlate($data['responsible_plate'] ?? '') : '';
        $attributed = Incident::attributedPlateFor($category, $plate, $responsible);
        $eventKey = $isDamage && $responsible !== '' && $plate !== ''
            ? 'damage:'.$responsible.':'.$plate.':'.Lot::now()->format('Y-m-d')
            : null;

        $location = trim((string) ($data['location'] ?? '')) ?: ($session?->slot?->code ?? $session?->slot_code);

        $incident = $this->create([
            'category' => $category,
            'source' => 'manual',
            'status' => 'open',
            'session_id' => $session?->id,
            'slot_id' => $session?->slot_id,
            'plate_number' => $plate !== '' ? $plate : null,
            'damaged_plate' => $isDamage && $plate !== '' ? $plate : null,
            'responsible_plate' => $responsible !== '' ? $responsible : null,
            'attributed_plate' => $attributed,
            'other_label' => $category === 'Other' ? mb_substr(trim((string) ($data['other_label'] ?? '')), 0, 80) ?: null : null,
            'event_key' => $eventKey,
            'confirmation' => Incident::PENDING,
            'location' => $location,
            'description' => trim($data['description']),
            'reported_by' => $guard->id,
            'reported_at' => now(),
            'last_seen_at' => now(),
        ]);

        if (! $incident->wasRecentlyCreated) {
            IncidentNote::create(['incident_id' => $incident->id, 'user_id' => $guard->id, 'kind' => 'followup', 'body' => trim($data['description'])]);
            $this->storePhotos($incident, $photos);
            $incident->update(['last_seen_at' => now()]);
            ActivityLog::record('incident_note_added', $guard, $request, $incident->code, 'guard', ['kind' => 'followup', 'duplicate_report' => true]);

            return $incident;
        }

        $this->storePhotos($incident, $photos);

        $notified = false;
        $channel = null;
        $result = null;

        $isWrongSlot = $category === 'Wrong slot';

        // A wrong-slot report always tells the driver at once. It counts as one warning, so the driver must know about it.
        if ($plate !== '') {
            $delivery = $this->notices->deliverToVehicle(
                $plate, 'N-12', $isWrongSlot ? 'incident_wrong_slot' : ($isDamage ? 'incident_damage' : 'incident_report'),
                $isWrongSlot ? 'Your vehicle is parked in the wrong slot' : ($isDamage ? 'Your vehicle was damaged' : 'Security report about your vehicle'),
                $isWrongSlot
                    ? $this->wrongSlotNotice($incident, $plate, $session)
                    : ($isDamage
                        ? "Security report {$incident->code}: your parked vehicle ".Lot::plate($plate).' was reported damaged'.($incident->location ? " in slot {$incident->location}" : '').'. Please contact the booth.'
                        : "Security report {$incident->code} mentions your vehicle ".Lot::plate($plate).': '.strtolower($incident->title()).'. Please contact the booth.'),
                $incident, "incident-{$incident->id}", $session,
            );
            $notified = $delivery['ok'];
            $channel = $delivery['channel'];
            $result = $delivery['result'];
        } elseif ($plate === '') {
            $result = 'No plate was given, so there is no driver to notify.';
        } else {
            $result = 'The Guard chose not to notify the driver.';
        }

        $incident->update([
            'driver_notified' => $notified,
            'driver_notified_at' => $notified ? now() : null,
            'driver_notice_channel' => $channel,
            'driver_notice_result' => $result ? mb_substr($result, 0, 160) : null,
            'driver_notice_reason' => $notified ? null : ($result ? mb_substr($result, 0, 160) : null),
        ]);

        $this->notices->staffNewIncident($incident->load('reporter'));
        ActivityLog::record('incident_reported', $guard, $request, $incident->code, $guard->role === User::ROLE_MANAGER ? 'manager' : 'guard', [
            'after' => $incident->only(['code', 'category', 'source', 'plate_number', 'damaged_plate', 'responsible_plate', 'location', 'description', 'status']),
            'photos' => count($photos),
        ]);

        return $incident;
    }

    /**
     * Where a vehicle that is in the wrong slot belongs: the slot it reserved, or the kind of zone for its type.
     * Null when the records do not say.
     */
    public function rightPlaceFor(?ParkingSession $session, string $plate): ?string
    {
        if (! $session) {
            return null;
        }

        $reservation = $session->reservation;

        if ($reservation && $reservation->slot_id && $reservation->slot_id !== $session->slot_id) {
            return "your reserved slot {$reservation->slot_code}";
        }

        $type = $session->vehicle_type ?: Vehicle::query()->where('plate_number', $plate)->value('vehicle_type');
        $slot = $session->slot;

        if ($slot && $type && ! $slot->acceptsType($type)) {
            return Vehicle::zoneClassFor($type) === 'motorcycle' ? 'a motorcycle zone' : 'a car zone';
        }

        return null;
    }

    /** The notice a driver gets when a wrong-slot report is filed. */
    private function wrongSlotNotice(Incident $incident, string $plate, ?ParkingSession $session): string
    {
        $where = $session?->slot?->code ?? $session?->slot_code ?? $incident->location;
        $right = $this->rightPlaceFor($session, $plate);
        $limit = app(BanService::class)->threshold();

        return "Security report {$incident->code}: your vehicle ".Lot::plate($plate).($where ? " is parked in slot {$where}" : ' is parked in the wrong slot')
            .($where ? ', which is the wrong slot for it' : '').'. '
            .($right ? "Please move it to {$right}." : 'Please move it to the slot on your ticket or reservation, or ask the booth.')
            ." This counts as one warning once the Parking Manager confirms the report. {$limit} confirmed warnings ban a vehicle from the lot.";
    }

    /**
     * The Notify Driver action on a long-parking incident. Sends an in-app notice, or a text for a walk-in, and records
     * the time and result on the incident. A second press inside NOTIFY_COOLDOWN_MINUTES changes nothing.
     *
     * @return array{sent:bool,message:string}
     */
    public function notifyDriver(Incident $incident, User $by, ?Request $request = null): array
    {
        $outcome = DB::transaction(function () use ($incident) {
            $locked = Incident::query()->lockForUpdate()->find($incident->id);

            if ($locked->status === 'resolved') {
                return ['skip' => 'This incident is resolved. Nobody needs to be notified.'];
            }

            if ($locked->driver_notified_at && $locked->driver_notified_at->gt(now()->subMinutes(self::NOTIFY_COOLDOWN_MINUTES))) {
                return ['skip' => 'The driver was notified at '.Lot::time($locked->driver_notified_at).' ('.($locked->driver_notice_channel === 'sms' ? 'text message' : 'in the app').'). Wait '.self::NOTIFY_COOLDOWN_MINUTES.' minutes before notifying again.'];
            }

            // Mark the time first, so a second request that arrives at the same moment sees the cooldown.
            $previous = $locked->driver_notified_at;
            $locked->update(['driver_notified_at' => now()]);

            return ['incident' => $locked, 'previous' => $previous];
        });

        if (isset($outcome['skip'])) {
            return ['sent' => false, 'message' => $outcome['skip']];
        }

        /** @var Incident $locked */
        $locked = $outcome['incident'];
        $session = $locked->session_id ? ParkingSession::query()->with('slot')->find($locked->session_id) : null;
        $plate = $locked->plate_number ?: $session?->plate_number;

        $delivery = $this->notices->deliverToVehicle(
            $plate, 'N-11', 'long_parking_report', 'Long parking notice',
            'Your vehicle '.Lot::plate($plate).' has been parked in slot '.($session?->slot?->code ?? $session?->slot_code ?? $locked->location).' for a long time'
            .($session ? ', since '.Lot::stamp($session->entered_at) : '').'. Please pay at the booth and collect it.',
            $locked, 'notify-'.$locked->id.'-'.now()->format('YmdHi'), $session,
        );

        $locked->update([
            'driver_notified' => $delivery['ok'] || $locked->driver_notified,
            // A notice that failed does not start the cooldown, so the Guard can try again at once.
            'driver_notified_at' => $delivery['ok'] ? now() : $outcome['previous'],
            'driver_notice_channel' => $delivery['channel'],
            'driver_notice_result' => mb_substr($delivery['result'], 0, 160),
            'driver_notice_reason' => $delivery['ok'] ? null : mb_substr($delivery['result'], 0, 160),
        ]);

        IncidentNote::create(['incident_id' => $locked->id, 'user_id' => $by->id, 'kind' => 'system', 'body' => 'Notify Driver pressed. '.$delivery['result']]);
        ActivityLog::record('incident_driver_notified', $by, $request, $locked->code, 'guard', ['ok' => $delivery['ok'], 'channel' => $delivery['channel'], 'result' => $delivery['result']]);

        return ['sent' => $delivery['ok'], 'message' => $delivery['result']];
    }

    /**
     * What stands between a long-parking incident and its resolution. Empty means it can be resolved.
     * A long-parking incident resolves only when its parking session is paid in full and the exit is confirmed.
     *
     * @return array<int, string>
     */
    public function resolutionBlockers(Incident $incident): array
    {
        if ($incident->category !== self::LONG_PARKED) {
            return [];
        }

        $session = $incident->session_id ? ParkingSession::query()->with('payment')->find($incident->session_id) : null;

        if (! $session) {
            return ['The parking session for this incident cannot be found.'];
        }

        $blockers = [];

        if (! $session->payment) {
            $blockers[] = 'The parking fees are not paid and verified yet.';
        }

        if ($session->status !== ParkingSession::STATUS_COMPLETED || ! $session->exit_approved_by) {
            $blockers[] = $session->status === ParkingSession::STATUS_PAID
                ? 'The vehicle is paid but has not left. The Guard must confirm the exit.'
                : 'The vehicle is still in the lot. Its exit is not confirmed.';
        }

        return $blockers;
    }

    public function addNote(Incident $incident, User $user, string $body, ?Request $request = null): IncidentNote
    {
        $note = IncidentNote::create(['incident_id' => $incident->id, 'user_id' => $user->id, 'kind' => 'note', 'body' => trim($body)]);
        ActivityLog::record('incident_note_added', $user, $request, $incident->code, 'guard', ['kind' => 'note']);

        return $note;
    }

    /**
     * Move an incident forward: open, under review, resolved. A resolved incident stays resolved.
     *
     * @throws \InvalidArgumentException with a message fit for the screen
     */
    public function changeStatus(Incident $incident, User $user, string $status, ?string $note, ?Request $request = null, string $area = 'guard'): void
    {
        $rank = ['open' => 0, 'under_review' => 1, 'resolved' => 2];

        if (! isset($rank[$status])) {
            throw new \InvalidArgumentException('Choose a status.');
        }

        if ($incident->status === 'resolved') {
            throw new \InvalidArgumentException('A resolved incident stays resolved. It cannot be reopened.');
        }

        if ($rank[$status] < $rank[$incident->status]) {
            throw new \InvalidArgumentException('An incident can only move forward: open, under review, resolved.');
        }

        if ($status === 'resolved' && ($blockers = $this->resolutionBlockers($incident)) !== []) {
            throw new \InvalidArgumentException('A long-parking incident resolves by itself when the fees are paid and the exit is confirmed. '.implode(' ', $blockers));
        }

        if ($status === 'resolved' && trim((string) $note) === '') {
            throw new \InvalidArgumentException('Add a resolution note to resolve this incident.');
        }

        $before = $incident->only(['status', 'resolution_note']);

        if ($status !== $incident->status) {
            $incident->update($status === 'resolved'
                ? ['status' => $status, 'resolution_note' => trim($note), 'resolved_by' => $user->id, 'resolved_at' => now()]
                : ['status' => $status]);
        }

        IncidentNote::create([
            'incident_id' => $incident->id,
            'user_id' => $user->id,
            'kind' => 'status',
            'body' => 'Set to '.Incident::statusLabel($status).'.'.(trim((string) $note) !== '' ? ' Note: '.trim($note) : ''),
        ]);

        ActivityLog::record('incident_status_updated', $user, $request, $incident->code, $area, [
            'before' => $before, 'after' => $incident->only(['status', 'resolution_note']),
        ]);
    }

    /** Save up to 3 uploaded photos on the private disk. */
    public function storePhotos(Incident $incident, array $photos): void
    {
        $existing = $incident->photos()->count();

        foreach (array_slice(array_values(array_filter($photos)), 0, max(0, 3 - $existing)) as $file) {
            $path = $file->store("incident-photos/{$incident->id}", 'local');

            IncidentPhoto::create([
                'incident_id' => $incident->id,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 120),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
    }

    public function photoResponse(IncidentPhoto $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, $photo->original_name, ['Content-Type' => $photo->mime]);
    }

    public function openSession(string $plate): ?ParkingSession
    {
        return ParkingSession::query()
            ->with('slot')
            ->where('plate_number', Vehicle::normalizePlate($plate))
            ->whereIn('status', ParkingSession::OPEN_STATUSES)
            ->latest('entered_at')
            ->first();
    }

    /** Why a plate cannot be reported, or null when the vehicle is parked now. */
    public function plateProblem(string $plate): ?string
    {
        if ($this->openSession($plate)) {
            return null;
        }

        $ever = ParkingSession::query()->where('plate_number', $plate)->latest('entered_at')->first();

        return $ever
            ? Lot::plate($plate).' is no longer parked. It left the lot at '.Lot::stamp($ever->exited_at ?? $ever->entered_at).'. Only vehicles parked now can be reported.'
            : 'No vehicle with plate '.Lot::plate($plate).' is parked in the lot. Check the plate on the vehicle.';
    }

    /**
     * The rules that depend on the category: which of plate and slot are required, that a plate belongs to a vehicle
     * parked now, that the slot is one with a vehicle in it and is the one this vehicle is in, the typed name for
     * "Other", and for damage the plate of the responsible vehicle.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array
    {
        $need = Incident::requirements($data['category']);
        $plate = (string) ($data['plate_number'] ?? '');
        $place = $this->slotCode((string) ($data['location'] ?? ''));
        $text = trim((string) ($data['place_text'] ?? ''));
        $occupied = $this->occupiedSlots()->pluck('code')->all();

        // Where the report may name any place in the lot ("Zone A lane"), a value that is not a slot code is that place.
        if ($place !== '' && ! preg_match('/^[A-Z]-\d{2}$/', $place) && in_array($need['place'], ['location', 'optional'], true)) {
            $text = $text !== '' ? $text : $place;
            $place = '';
        }

        $errors = [];
        $session = null;

        if (($need['label'] ?? null) === 'required' && trim((string) ($data['other_label'] ?? '')) === '') {
            $errors['other_label'] = 'Say what kind of incident this is, for example "Blocked exit lane".';
        }

        if ($plate === '' && ! empty($data['session_id'])) {
            $session = ParkingSession::query()->with('slot')->whereIn('status', ParkingSession::OPEN_STATUSES)->find($data['session_id']);
            $plate = $session?->plate_number ?? '';
        }

        if ($plate === '') {
            if ($need['plate'] === 'required') {
                $errors['plate_number'] = $data['category'] === 'Vehicle damage'
                    ? 'Enter the plate number of the damaged vehicle.'
                    : 'Enter the plate number of the vehicle. This kind of report needs it.';
            }
        } elseif (! $session) {
            $session = $this->openSession($plate);

            if (! $session) {
                $errors['plate_number'] = $this->plateProblem($plate);
            }
        }

        $parkedSlot = $session ? (string) ($session->slot?->code ?? $session->slot_code) : '';

        if ($place !== '' && ! in_array($place, $occupied, true)) {
            $errors['location'] = "There is no vehicle in slot {$place} right now. Choose a slot from the list.";
        }

        if ($need['place'] === 'auto') {
            $place = $parkedSlot;
        } elseif ($need['place'] === 'slot') {
            if ($place === '' && ! isset($errors['location'])) {
                $errors['location'] = 'Choose the slot with the vehicle. This kind of report needs it.';
            } elseif ($place !== '' && $parkedSlot !== '' && $parkedSlot !== $place && ! isset($errors['plate_number']) && ! isset($errors['location'])) {
                $errors['location'] = Lot::plate($plate)." is parked in {$parkedSlot}, not {$place}.";
            }
        } elseif ($need['place'] === 'location') {
            if ($place === '' && $text === '') {
                $errors['location'] = 'Choose a slot, or say where it is in "Other place", for example Zone A lane.';
            }

            $place = $place !== '' ? $place : $text;
        } else {
            $place = $place !== '' ? $place : $text;
        }

        $responsible = '';

        if (($need['responsible'] ?? null) === 'required') {
            $responsible = (string) ($data['responsible_plate'] ?? '');

            if (! empty($data['responsible_unknown'])) {
                $responsible = '';
            } elseif ($responsible === '') {
                $errors['responsible_plate'] = 'Enter the plate of the vehicle that caused the damage, or tick "Responsible vehicle unknown".';
            } elseif ($plate !== '' && $responsible === $plate) {
                $errors['responsible_plate'] = 'The responsible vehicle cannot be the damaged vehicle. Check both plates.';
            } elseif (! $this->knownVehicle($responsible)) {
                $errors['responsible_plate'] = 'No vehicle with plate '.Lot::plate($responsible).' has used this lot. Check the plate, or tick "Responsible vehicle unknown".';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_merge($data, [
            'plate_number' => $plate !== '' ? $plate : null,
            'responsible_plate' => $responsible !== '' ? $responsible : null,
            'location' => $place !== '' ? $place : null,
            'session_id' => $session?->id,
        ]);
    }

    /** Has this vehicle ever used the lot or been saved by a driver? */
    public function knownVehicle(string $plate): bool
    {
        return ParkingSession::query()->where('plate_number', $plate)->exists() || Vehicle::query()->where('plate_number', $plate)->exists();
    }

    /** "b3", "b 03" and "B-03" are the same slot. */
    private function slotCode(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^([A-Za-z])\s*-?\s*0*(\d{1,2})$/', $value, $m)) {
            return sprintf('%s-%02d', strtoupper($m[1]), (int) $m[2]);
        }

        return $value;
    }
}
