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
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Incident reports written by the Security Guard (process document 6.9 and 6.10)
 * and the status handling on the Guard screens.
 */
class IncidentService
{
    public const LONG_PARKED = 'Long-parked vehicle';

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

    /** Create an incident with a free code, retrying if two are made at the same moment. */
    public function create(array $attributes): Incident
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(fn () => Incident::create($attributes + ['code' => $this->nextCode()]));
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \LogicException('unreachable');
    }

    /**
     * A manual report by a Guard. The vehicle and slot fill in when the plate has an open session.
     *
     * @param  array<string, mixed>  $data  category, plate_number, location, description, session_id, notify_driver
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

        $location = trim((string) ($data['location'] ?? '')) ?: ($session?->slot?->code ?? $session?->slot_code);

        $incident = $this->create([
            'category' => $data['category'],
            'source' => 'manual',
            'status' => 'open',
            'session_id' => $session?->id,
            'slot_id' => $session?->slot_id,
            'plate_number' => $plate !== '' ? $plate : null,
            'location' => $location,
            'description' => trim($data['description']),
            'reported_by' => $guard->id,
            'reported_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->storePhotos($incident, $photos);

        $notified = false;
        $driver = null;

        if ($plate !== '') {
            $driver = $session ? $this->notices->driverForSession($session) : $this->notices->driverForPlate($plate);
        }

        if ($driver && ($data['notify_driver'] ?? true)) {
            $notified = $this->notices->incidentAboutVehicle($driver, $incident) !== null;
        }

        $incident->update([
            'driver_notified' => $notified,
            'driver_notice_reason' => $notified
                ? null
                : ($plate === '' ? 'No plate was given, so there is no driver account to notify.'
                    : (! $session && ! $driver ? Lot::plate($plate).' has no parking session, so there is no driver account to notify.'
                        : 'This vehicle is not on a driver account, so nobody was notified.')),
        ]);

        $this->notices->staffNewIncident($incident->load('reporter'));
        ActivityLog::record('incident_reported', $guard, $request, $incident->code, 'guard', [
            'after' => $incident->only(['code', 'category', 'source', 'plate_number', 'location', 'description', 'status']),
            'photos' => count($photos),
        ]);

        return $incident;
    }

    /** The manual long-parking report. Returns the existing incident as a follow-up when the session already has one. */
    public function reportLongParked(User $guard, ParkingSession $session, string $description, array $photos, bool $notifyDriver, ?Request $request = null): array
    {
        $existing = Incident::query()->where('session_id', $session->id)->where('category', self::LONG_PARKED)->oldest('id')->first();

        if ($existing) {
            IncidentNote::create(['incident_id' => $existing->id, 'user_id' => $guard->id, 'kind' => 'followup', 'body' => trim($description)]);
            $this->storePhotos($existing, $photos);
            $existing->update(['last_seen_at' => now()]);
            ActivityLog::record('incident_note_added', $guard, $request, $existing->code, 'guard', ['kind' => 'followup']);

            return ['incident' => $existing, 'followup' => true];
        }

        $incident = $this->createManual($guard, [
            'category' => self::LONG_PARKED,
            'plate_number' => $session->plate_number,
            'session_id' => $session->id,
            'description' => $description,
            'notify_driver' => false,
        ], $photos, $request);

        if ($notifyDriver && ($driver = $this->notices->driverForSession($session))) {
            $sent = $this->notices->longParkedReported($session->loadMissing('slot'), $incident) !== null;
            $incident->update(['driver_notified' => $sent, 'driver_notice_reason' => $sent ? null : 'The driver was already notified about this session.']);
        } else {
            $incident->update(['driver_notified' => false, 'driver_notice_reason' => $notifyDriver ? 'This vehicle is not on a driver account, so nobody was notified.' : 'The Guard chose not to notify the driver.']);
        }

        ActivityLog::record('long_parking_reported', $guard, $request, $incident->code, 'guard', ['ticket' => $session->ticket_number]);

        return ['incident' => $incident->fresh(), 'followup' => false];
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

    public function photoResponse(IncidentPhoto $photo): \Symfony\Component\HttpFoundation\StreamedResponse
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
}
