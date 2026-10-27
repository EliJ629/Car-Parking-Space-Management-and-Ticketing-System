<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\IncidentService;
use App\Support\Lot;
use App\Support\PlateNumber;
use App\Support\Rules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Incident list, new incident, detail, notify driver, notes and status. Long-parking incidents are made by the system. */
class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), Incident::STATUSES, true) ? $request->query('status') : null;
        $base = Incident::query()->with(['reporter', 'slot'])->withCount('photos');

        $counts = Incident::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return view('guard.incidents.index', [
            'status' => $status,
            'incidents' => $base->when($status, fn ($q) => $q->where('status', $status))->orderByDesc('reported_at')->orderByDesc('id')->paginate(10)->withQueryString(),
            'counts' => $counts,
            'total' => (int) $counts->sum(),
        ]);
    }

    public function create(Request $request, IncidentService $incidents): View
    {
        $category = (string) $request->query('category', '');

        return view('guard.incidents.create', [
            'categories' => Incident::MANUAL_CATEGORIES,
            'requirements' => Incident::REQUIREMENTS,
            'occupied' => $incidents->occupiedSlots(),
            'taken' => $incidents->takenSlots(),
            'prefill' => [
                'category' => in_array($category, Incident::MANUAL_CATEGORIES, true) ? $category : '',
                'plate_number' => (string) $request->query('plate', ''),
                'location' => (string) $request->query('location', ''),
                'description' => (string) $request->query('description', ''),
                'session_id' => (int) $request->query('session', 0) ?: null,
            ],
        ]);
    }

    public function store(Request $request, IncidentService $incidents): RedirectResponse
    {
        $this->normalize($request);

        $data = $request->validate([
            // Long-parking incidents are made by the system. A Guard cannot report one by hand.
            'category' => ['required', Rule::in(Incident::MANUAL_CATEGORIES)],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'plate_number' => Rules::plate(false),
            'responsible_plate' => Rules::plate(false),
            'responsible_unknown' => ['nullable', 'boolean'],
            'other_label' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'place_text' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'integer', 'exists:parking_sessions,id'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], $this->messages());

        $data = $incidents->prepare($data);

        $incident = $incidents->createManual($request->user(), $data, $request->file('photos', []), $request);

        if (! $incident->wasRecentlyCreated) {
            return redirect()->route('guard.incidents.show', $incident)->with('modal', [
                'title' => 'Already reported',
                'body' => "This damage was already reported today as {$incident->code}. Your report was added to it as a follow-up. No second incident was made.",
                'primary' => ['label' => 'View incident', 'close' => true],
            ]);
        }

        $redirect = redirect()->route('guard.incidents.show', $incident)->with('modal', [
            'title' => 'Incident submitted',
            'body' => "{$incident->code} was sent to the Parking Manager.",
            'note' => $incident->driver_notified ? 'The driver was notified.' : ($incident->driver_notice_reason ?: 'No driver was notified.'),
            'primary' => ['label' => 'View incident', 'close' => true],
            'secondary' => ['label' => 'Report another', 'url' => route('guard.incidents.create')],
        ]);

        // The pop-up on the next page also lets the Guard enter a resolution at once, when the matter is already dealt with.
        return $redirect->with('offer_resolution', true);
    }

    /** Live check for the form: is this plate parked in the lot right now, and in which slot? */
    public function checkPlate(Request $request, IncidentService $incidents): JsonResponse
    {
        $plate = Vehicle::normalizePlate((string) $request->query('plate', ''));

        if ($plate === '') {
            return response()->json(['state' => 'empty']);
        }

        if (! PlateNumber::isValid($plate)) {
            return response()->json(['state' => 'invalid', 'message' => PlateNumber::problem($plate)]);
        }

        // The vehicle that caused damage may already have left, so it only has to be known to the lot.
        if ($request->query('role') === 'responsible') {
            return response()->json($incidents->knownVehicle($plate)
                ? ['state' => 'known', 'message' => Lot::plate($plate).' is a known vehicle.']
                : ['state' => 'unknown', 'message' => 'No vehicle with plate '.Lot::plate($plate).' has used this lot. Check the plate, or tick "Responsible vehicle unknown".']);
        }

        $session = $incidents->openSession($plate);

        if ($session) {
            return response()->json([
                'state' => 'parked', 'plate' => Lot::plate($plate), 'slot' => $session->slot?->code ?? $session->slot_code,
                'message' => Lot::plate($plate).' is parked in '.($session->slot?->code ?? $session->slot_code).'.',
            ]);
        }

        return response()->json(['state' => 'gone', 'message' => $incidents->plateProblem($plate)]);
    }

    public function show(Incident $incident, IncidentService $incidents): View
    {
        $incident->load(['reporter', 'resolver', 'slot', 'session', 'photos', 'notes.user']);

        return view('guard.incidents.show', [
            'incident' => $incident,
            'events' => $this->timeline($incident),
            'blockers' => $incident->status === 'resolved' ? [] : $incidents->resolutionBlockers($incident),
            'notifyCooldown' => IncidentService::NOTIFY_COOLDOWN_MINUTES,
        ]);
    }

    /** Notify Driver on a long-parking incident: an in-app notice, or a text for a walk-in. The result is recorded. */
    public function notifyDriver(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        abort_unless($incident->category === IncidentService::LONG_PARKED, 404);

        $result = $incidents->notifyDriver($incident, $request->user(), $request);

        return redirect()->route('guard.incidents.show', $incident)->with('modal', [
            'title' => $result['sent'] ? 'Driver notified' : 'Driver not notified',
            'body' => $result['message'],
            'primary' => ['label' => 'OK', 'close' => true],
            'tone' => $result['sent'] ? null : 'stop',
        ]);
    }

    public function addNote(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:500']], ['body.required' => 'Write the note first.', 'body.min' => 'Write the note first.', 'body.max' => 'The note can have up to 500 characters.']);
        $incidents->addNote($incident, $request->user(), $data['body'], $request);

        return redirect()->route('guard.incidents.show', $incident)->with('toast', ['Note added', 'The note is on the timeline record.']);
    }

    public function updateStatus(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['status.required' => 'Choose a status.', 'status.in' => 'Choose a status from the list.', 'note.max' => 'The note can have up to 500 characters.']);

        try {
            $incidents->changeStatus($incident, $request->user(), $data['status'], $data['note'] ?? null, $request);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('guard.incidents.show', $incident)->withInput()->with('modal', [
                'title' => 'Status not changed',
                'body' => $e->getMessage(),
                'primary' => ['label' => 'OK', 'close' => true],
                'tone' => 'stop',
            ]);
        }

        return redirect()->route('guard.incidents.show', $incident)->with('toast', ['Status updated', "{$incident->code} is now ".strtolower(Incident::statusLabel($data['status'])).'.']);
    }

    /** @return array<int, array{title:string,detail:string,state:string}> */
    private function timeline(Incident $incident): array
    {
        $photos = $incident->photos->count();
        $events = [[
            'title' => $incident->source === 'auto' ? 'Detected by Bayline' : 'Reported by '.($incident->reporter?->name ?? 'a guard'),
            'detail' => Lot::stamp($incident->reported_at).($photos ? ', '.$photos.' '.($photos === 1 ? 'photo' : 'photos').' attached' : ''),
            'state' => 'done',
        ]];

        foreach ($incident->notes as $note) {
            if ($note->kind === 'status') {
                [$head, $text] = array_pad(explode(' Note: ', $note->body, 2), 2, null);
                $events[] = ['title' => rtrim($head, '.').' by '.($note->user?->name ?? 'the system'), 'detail' => Lot::stamp($note->created_at).($text ? '. Note: '.$text : ''), 'state' => 'done'];
            } elseif ($note->kind === 'followup') {
                $events[] = ['title' => 'Follow-up by '.($note->user?->name ?? 'a guard'), 'detail' => Lot::stamp($note->created_at).'. '.$note->body, 'state' => 'done'];
            } elseif ($note->kind === 'system') {
                $events[] = ['title' => 'Bayline', 'detail' => Lot::stamp($note->created_at).'. '.$note->body, 'state' => 'done'];
            }
        }

        $events[] = $incident->status === 'resolved'
            ? ['title' => 'Resolved', 'detail' => Lot::stamp($incident->resolved_at).($incident->resolution_note ? '. '.$incident->resolution_note : ''), 'state' => 'done']
            : ['title' => 'Waiting for resolution', 'detail' => 'Current status: '.Incident::statusLabel($incident->status), 'state' => 'now'];

        return $events;
    }

    private function normalize(Request $request): void
    {
        $plate = Vehicle::normalizePlate((string) $request->input('plate_number', ''));
        $responsible = Vehicle::normalizePlate((string) $request->input('responsible_plate', ''));
        $request->merge([
            'plate_number' => $plate !== '' ? $plate : null,
            'responsible_plate' => $responsible !== '' ? $responsible : null,
            'responsible_unknown' => $request->boolean('responsible_unknown'),
            'other_label' => Rules::squish($request->input('other_label')) ?: null,
            'location' => Rules::squish($request->input('location')) ?: null,
            'place_text' => Rules::squish($request->input('place_text')) ?: null,
            'description' => trim((string) $request->input('description')),
        ]);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'category.required' => 'Choose a category.',
            'category.in' => 'Choose a category from the list.',
            'description.required' => 'Describe what you saw.',
            'description.min' => 'Write at least 10 characters.',
            'description.max' => 'The description can have up to 500 characters.',
            'other_label.max' => 'The name can have up to 80 characters.',
            'location.max' => 'The location can have up to 120 characters.',
            'place_text.max' => 'The place can have up to 120 characters.',
            'session_id.exists' => 'There is no parking session with that number.',
            'photos.max' => 'You can add up to 3 photos.',
            'photos.*.mimes' => 'Photos must be JPG or PNG.',
            'photos.*.max' => 'Each photo must be under 5 MB.',
            'photos.*.file' => 'That photo could not be uploaded. Try again.',
        ];
    }
}
