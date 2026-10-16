<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\IncidentService;
use App\Support\Rules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentController extends ManagerController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Incident::STATUSES)],
            'category' => ['nullable', Rule::in(Incident::CATEGORIES)],
            'source' => ['nullable', Rule::in(['manual', 'auto'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $incidents = Incident::query()->with(['reporter', 'slot'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->latest('reported_at')->paginate(20)->withQueryString();

        return $this->managerPage('Incidents', 'incidents', view('staff.manager.incidents.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'categories' => Incident::CATEGORIES,
            'statuses' => Incident::STATUSES,
            'openCount' => Incident::whereIn('status', ['open', 'under_review'])->count(),
            'autoOpenCount' => Incident::where('source', 'auto')->whereIn('status', ['open', 'under_review'])->count(),
            'weekCount' => Incident::where('reported_at', '>=', now()->startOfWeek())->count(),
        ]));
    }

    public function create(): View
    {
        return $this->managerPage('Report incident', 'incidents', view('staff.manager.incidents.create', [
            'categories' => array_values(array_diff(Incident::CATEGORIES, ['Long-parked vehicle'])),
            'slots' => ParkingSlot::orderBy('code')->get(['id', 'code']),
        ]));
    }

    public function store(Request $request, IncidentService $incidents): RedirectResponse
    {
        $plate = Vehicle::normalizePlate((string) $request->input('plate_number'));
        $request->merge([
            'plate_number' => $plate !== '' ? $plate : null,
            'location' => Rules::squish($request->input('location')) ?: null,
            'description' => trim((string) $request->input('description')),
        ]);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_values(array_diff(Incident::CATEGORIES, ['Long-parked vehicle'])))],
            'session_id' => ['nullable', 'integer', 'exists:parking_sessions,id'],
            'slot_id' => ['nullable', 'integer', 'exists:parking_slots,id'],
            'plate_number' => Rules::plate(false),
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'category.required' => 'Choose a category.',
            'category.in' => 'Choose a category from the list.',
            'session_id.exists' => 'There is no parking session with that number.',
            'slot_id.exists' => 'Choose a slot from the list.',
            'plate_number.regex' => Rules::PLATE_MESSAGE.' Leave it empty if there is none.',
            'location.max' => 'The location can have up to 120 characters.',
            'description.required' => 'Add a description of at least 10 characters.',
            'description.min' => 'Add a description of at least 10 characters.',
            'description.max' => 'The description can have up to 500 characters.',
        ]);

        $incident = $incidents->create($data + [
            'source' => 'manual',
            'status' => 'open',
            'reported_by' => $request->user()->id,
            'reported_at' => now(),
            'last_seen_at' => now(),
        ]);
        ActivityLog::record('incident_reported', $request->user(), $request, $incident->code, 'manager', [
            'after' => $incident->only(['code', 'category', 'source', 'plate_number', 'location', 'description', 'status']),
        ]);

        return redirect()->route('manager.incidents.index')->with('status', "{$incident->code} was reported.");
    }

    public function show(Incident $incident): View
    {
        return $this->managerPage("Incident {$incident->code}", 'incidents', view('staff.manager.incidents.show', [
            'incident' => $incident->load(['reporter', 'resolver', 'slot', 'session']),
            'statuses' => Incident::STATUSES,
        ]));
    }

    public function update(Request $request, Incident $incident, IncidentService $incidents): RedirectResponse
    {
        $request->merge(['resolution_note' => trim((string) $request->input('resolution_note')) ?: null]);
        $data = $request->validate([
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'resolution_note' => ['nullable', 'required_if:status,resolved', 'string', 'min:3', 'max:500'],
        ], [
            'status.required' => 'Choose a status.',
            'status.in' => 'Choose a status from the list.',
            'resolution_note.required_if' => 'Add a resolution note to resolve this incident.',
            'resolution_note.min' => 'Write a note of at least 3 characters.',
            'resolution_note.max' => 'The note can have up to 500 characters.',
        ]);
        $rank = ['open' => 0, 'under_review' => 1, 'resolved' => 2];

        if ($rank[$data['status']] < $rank[$incident->status]
            || ($incident->status !== 'resolved' && $rank[$data['status']] > $rank[$incident->status] + 1)) {
            return back()->withErrors(['status' => 'Incident status can only move forward from open to under review to resolved.']);
        }

        if ($incident->status === 'resolved' && $data['status'] !== 'resolved') {
            return back()->withErrors(['status' => 'Resolved incidents remain in the records and cannot be reopened.']);
        }

        if ($incident->status === 'resolved') {
            return back()->withErrors(['status' => 'This incident is already resolved.']);
        }

        try {
            $incidents->changeStatus($incident, $request->user(), $data['status'], $data['resolution_note'] ?? null, $request, 'manager');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('manager.incidents.show', $incident)->with('status', 'Incident status was saved.');
    }
}
