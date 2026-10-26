<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\GateService;
use App\Services\IncidentService;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'requirements' => Incident::REQUIREMENTS,
            'slotCodes' => ParkingSlot::query()->orderBy('code')->pluck('code'),
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
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'plate_number' => Rules::plate(false),
            'location' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'integer', 'exists:parking_sessions,id'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], $this->messages());

        $data = $this->checkVehicleAndPlace($data, $incidents);

        $incident = $incidents->createManual($request->user(), $data, $request->file('photos', []), $request);

        return redirect()->route('guard.incidents.show', $incident)->with('modal', [
            'title' => 'Incident submitted',
            'body' => "{$incident->code} was sent to the Parking Manager.",
            'note' => $incident->driver_notified ? 'The driver was notified.' : ($incident->driver_notice_reason ?: 'No driver was notified.'),
            'primary' => ['label' => 'View incident', 'close' => true],
            'secondary' => ['label' => 'Report another', 'url' => route('guard.incidents.create')],
        ]);
    }

    /** Live check for the form: is this plate parked in the lot right now, and in which slot? */
    public function checkPlate(Request $request, IncidentService $incidents): JsonResponse
    {
        $plate = Vehicle::normalizePlate((string) $request->query('plate', ''));

        if ($plate === '') {
            return response()->json(['state' => 'empty']);
        }

        if (! preg_match(Vehicle::PLATE_PATTERN, $plate)) {
            return response()->json(['state' => 'invalid', 'message' => 'Enter a plate like ABC 1234.']);
        }

        $session = $incidents->openSession($plate);

        if ($session) {
            return response()->json([
                'state' => 'parked', 'plate' => Lot::plate($plate), 'slot' => $session->slot?->code ?? $session->slot_code,
                'message' => Lot::plate($plate).' is parked in '.($session->slot?->code ?? $session->slot_code).'.',
            ]);
        }

        return response()->json(['state' => 'gone', 'message' => $this->plateProblem($plate)]);
    }

    /** Why a plate cannot be reported, or null when the vehicle is parked now. */
    private function plateProblem(string $plate): ?string
    {
        if (app(IncidentService::class)->openSession($plate)) {
            return null;
        }

        $ever = ParkingSession::query()->where('plate_number', $plate)->latest('entered_at')->first();

        return $ever
            ? Lot::plate($plate).' is no longer parked. It left the lot at '.Lot::stamp($ever->exited_at ?? $ever->entered_at).'. Only vehicles parked now can be reported.'
            : 'No vehicle with plate '.Lot::plate($plate).' is parked in the lot. Check the plate on the vehicle.';
    }

    /**
     * The rules that depend on the category: which of plate and slot are required, that a plate belongs to a vehicle
     * parked now, and that the slot exists and is the one the vehicle is in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function checkVehicleAndPlace(array $data, IncidentService $incidents): array
    {
        $need = Incident::requirements($data['category']);
        $plate = (string) ($data['plate_number'] ?? '');
        $place = trim((string) ($data['location'] ?? ''));

        // "b3", "b 03" and "B-03" are the same slot.
        if (preg_match('/^([A-Za-z])\s*-?\s*0*(\d{1,2})$/', $place, $m)) {
            $place = sprintf('%s-%02d', strtoupper($m[1]), (int) $m[2]);
        }

        $errors = [];
        $session = null;

        if ($plate === '' && ! empty($data['session_id'])) {
            $session = ParkingSession::query()->with('slot')->whereIn('status', ParkingSession::OPEN_STATUSES)->find($data['session_id']);
            $plate = $session?->plate_number ?? '';
        }

        if ($plate === '') {
            if ($need['plate'] === 'required') {
                $errors['plate_number'] = 'Enter the plate number of the vehicle. This kind of report needs it.';
            }
        } elseif (! $session) {
            $session = $incidents->openSession($plate);

            if (! $session) {
                $errors['plate_number'] = $this->plateProblem($plate);
            }
        }

        $parkedSlot = $session ? (string) ($session->slot?->code ?? $session->slot_code) : '';

        if ($need['place'] === 'auto') {
            $place = $parkedSlot;
        } elseif ($need['place'] === 'slot') {
            if ($place === '') {
                $errors['location'] = 'Enter the slot, for example B-03. This kind of report needs it.';
            } elseif (! ParkingSlot::query()->where('code', $place)->exists()) {
                $errors['location'] = "There is no slot {$place}. Use a slot code like B-03.";
            } elseif ($parkedSlot !== '' && $parkedSlot !== $place && ! isset($errors['plate_number'])) {
                $errors['location'] = Lot::plate($plate)." is parked in {$parkedSlot}, not {$place}.";
            }
        } elseif ($need['place'] === 'location' && $place === '') {
            $errors['location'] = 'Say where it is, for example Zone A lane or a slot like B-03.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_merge($data, [
            'plate_number' => $plate !== '' ? $plate : null,
            'location' => $place !== '' ? $place : null,
            'session_id' => $session?->id,
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
                'note' => 'A vehicle that never got a ticket cannot be reported by plate. Use New incident, choose Suspicious vehicle, and say where it is.',
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
        $request->merge(['description' => trim((string) $request->input('description'))]);

        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:parking_sessions,id'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
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
        $request->merge([
            'plate_number' => $plate !== '' ? $plate : null,
            'location' => Rules::squish($request->input('location')) ?: null,
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
            'plate_number.regex' => 'Enter a plate like ABC 1234.',
            'location.max' => 'The location can have up to 120 characters.',
            'session_id.exists' => 'There is no parking session with that number.',
            'photos.max' => 'You can add up to 3 photos.',
            'photos.*.mimes' => 'Photos must be JPG or PNG.',
            'photos.*.max' => 'Each photo must be under 5 MB.',
            'photos.*.file' => 'That photo could not be uploaded. Try again.',
        ];
    }
}
