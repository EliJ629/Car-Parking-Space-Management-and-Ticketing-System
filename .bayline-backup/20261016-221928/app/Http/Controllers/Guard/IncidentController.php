<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\Vehicle;
use App\Services\GateService;
use App\Services\IncidentService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Incident list, new incident, long-parked report, detail, notes and status. */
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

    public function create(Request $request): View|RedirectResponse
    {
        $category = (string) $request->query('category', '');

        if ($category === IncidentService::LONG_PARKED) {
            return redirect()->route('guard.incidents.long-parked', array_filter(['plate' => $request->query('plate')]));
        }

        return view('guard.incidents.create', [
            'categories' => Incident::CATEGORIES,
            'prefill' => [
                'category' => in_array($category, Incident::CATEGORIES, true) ? $category : '',
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

        if ($request->input('category') === IncidentService::LONG_PARKED) {
            return redirect()->route('guard.incidents.long-parked', array_filter(['plate' => $request->input('plate_number')]));
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(Incident::CATEGORIES)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'plate_number' => ['nullable', 'regex:/^[A-Z0-9]{5,8}$/'],
            'location' => ['nullable', 'string', 'max:60'],
            'session_id' => ['nullable', 'integer'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], $this->messages());

        $incident = $incidents->createManual($request->user(), $data, $request->file('photos', []), $request);

        return redirect()->route('guard.incidents.show', $incident)->with('modal', [
            'title' => 'Incident submitted',
            'body' => "{$incident->code} was sent to the Parking Manager.",
            'note' => $incident->driver_notified ? 'The driver was notified.' : ($incident->driver_notice_reason ?: 'No driver was notified.'),
            'primary' => ['label' => 'View incident', 'close' => true],
            'secondary' => ['label' => 'Report another', 'url' => route('guard.incidents.create')],
        ]);
    }

    /** Report a long-parked vehicle. Step 1 asks for the plate, step 2 checks the details. */
    public function longParked(Request $request, IncidentService $incidents): View
    {
        $plate = Vehicle::normalizePlate((string) $request->query('plate', ''));

        if ($plate === '') {
            return view('guard.incidents.long-parked', ['step' => 1, 'plate' => '', 'modal' => null]);
        }

        $session = $incidents->openSession($plate);

        if (! $session) {
            $ever = ParkingSession::query()->where('plate_number', $plate)->latest('entered_at')->first();

            return view('guard.incidents.long-parked', ['step' => 1, 'plate' => $plate, 'modal' => $ever ? [
                'title' => 'Vehicle not currently parked',
                'body' => Lot::plate($plate).' left the lot at '.Lot::stamp($ever->exited_at ?? $ever->entered_at).'. A long-parking report needs a vehicle that is parked now.',
                'primary' => ['label' => 'Enter another plate', 'close' => true],
            ] : [
                'title' => 'Plate not found',
                'body' => 'There is no parking session for '.Lot::plate($plate).'. Check the plate on the vehicle and try again.',
                'note' => 'For a vehicle that never got a ticket, use New incident and choose Unauthorized parking.',
                'primary' => ['label' => 'Enter another plate', 'close' => true],
                'secondary' => ['label' => 'New incident', 'url' => route('guard.incidents.create')],
            ]]);
        }

        $existing = Incident::query()->where('session_id', $session->id)->where('category', IncidentService::LONG_PARKED)->oldest('id')->first();

        return view('guard.incidents.long-parked', [
            'step' => 2,
            'plate' => $plate,
            'session' => $session,
            'minutes' => Lot::minutesBetween($session->entered_at),
            'limit' => Lot::longParkingHours(),
            'existing' => $existing,
            'modal' => $existing ? [
                'title' => 'An alert already exists',
                'body' => "{$existing->code} is already open for ".Lot::plate($plate).'. Your report will be added to it as a follow-up. No second alert is made.',
                'primary' => ['label' => 'Continue as follow-up', 'close' => true],
                'secondary' => ['label' => 'Open '.$existing->code, 'url' => route('guard.incidents.show', $existing)],
            ] : null,
        ]);
    }

    public function storeLongParked(Request $request, IncidentService $incidents): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:parking_sessions,id'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'notify_driver' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], $this->messages());

        $session = ParkingSession::query()->with('slot')->findOrFail($data['session_id']);

        if (! in_array($session->status, ParkingSession::OPEN_STATUSES, true)) {
            return redirect()->route('guard.incidents.long-parked', ['plate' => $session->plate_number]);
        }

        $result = $incidents->reportLongParked($request->user(), $session, $data['description'], $request->file('photos', []), $request->boolean('notify_driver'), $request);
        $incident = $result['incident'];

        return redirect()->route('guard.incidents.show', $incident)->with('modal', [
            'title' => $result['followup'] ? 'Follow-up added' : 'Long-parking report submitted',
            'body' => $result['followup']
                ? "Your report was added to {$incident->code}."
                : "{$incident->code} was created and the Parking Manager was alerted.",
            'note' => $result['followup'] ? null : ($incident->driver_notified ? 'The driver was notified.' : ($incident->driver_notice_reason ?: null)),
            'primary' => ['label' => 'View incident', 'close' => true],
        ]);
    }

    public function show(Incident $incident): View
    {
        $incident->load(['reporter', 'resolver', 'slot', 'session', 'photos', 'notes.user']);

        return view('guard.incidents.show', ['incident' => $incident, 'events' => $this->timeline($incident)]);
    }

    public function addNote(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:500']], ['body.required' => 'Write the note first.', 'body.min' => 'Write the note first.']);
        $incidents->addNote($incident, $request->user(), $data['body'], $request);

        return redirect()->route('guard.incidents.show', $incident)->with('toast', ['Note added', 'The note is on the timeline record.']);
    }

    public function updateStatus(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['status.required' => 'Choose a status.']);

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
        $request->merge(['plate_number' => $plate !== '' ? $plate : null]);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'category.required' => 'Choose a category.',
            'category.in' => 'Choose a category from the list.',
            'description.required' => 'Describe what you saw.',
            'description.min' => 'Write at least 10 characters.',
            'plate_number.regex' => 'Enter a plate like ABC 1234, or leave it empty.',
            'photos.max' => 'You can add up to 3 photos.',
            'photos.*.mimes' => 'Photos must be JPG or PNG.',
            'photos.*.max' => 'Each photo must be under 5 MB.',
            'photos.*.file' => 'That photo could not be uploaded. Try again.',
        ];
    }
}
