<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use App\Services\BanService;
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
                ->orWhere('plate_number', 'like', '%'.(Vehicle::normalizePlate($search) ?: $search).'%')
                ->orWhere('responsible_plate', 'like', '%'.(Vehicle::normalizePlate($search) ?: $search).'%')
                ->orWhere('other_label', 'like', "%{$search}%")
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

    public function create(IncidentService $incidents): View
    {
        return $this->managerPage('Report incident', 'incidents', view('staff.manager.incidents.create', [
            'categories' => Incident::MANUAL_CATEGORIES,
            'requirements' => Incident::REQUIREMENTS,
            'occupied' => $incidents->occupiedSlots(),
        ]));
    }

    public function store(Request $request, IncidentService $incidents): RedirectResponse
    {
        $plate = Vehicle::normalizePlate((string) $request->input('plate_number'));
        $responsible = Vehicle::normalizePlate((string) $request->input('responsible_plate'));
        $request->merge([
            'plate_number' => $plate !== '' ? $plate : null,
            'responsible_plate' => $responsible !== '' ? $responsible : null,
            'responsible_unknown' => $request->boolean('responsible_unknown'),
            'other_label' => Rules::squish($request->input('other_label')) ?: null,
            'location' => Rules::squish($request->input('location')) ?: null,
            'place_text' => Rules::squish($request->input('place_text')) ?: null,
            'description' => trim((string) $request->input('description')),
        ]);

        $data = $request->validate([
            'category' => ['required', Rule::in(Incident::MANUAL_CATEGORIES)],
            'session_id' => ['nullable', 'integer', 'exists:parking_sessions,id'],
            'plate_number' => Rules::plate(false),
            'responsible_plate' => Rules::plate(false),
            'responsible_unknown' => ['nullable', 'boolean'],
            'other_label' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'place_text' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'category.required' => 'Choose a category.',
            'category.in' => 'Choose a category from the list. Long-parking incidents are made automatically.',
            'session_id.exists' => 'There is no parking session with that number.',
            'other_label.max' => 'The name can have up to 80 characters.',
            'location.max' => 'The location can have up to 120 characters.',
            'description.required' => 'Add a description of at least 10 characters.',
            'description.min' => 'Add a description of at least 10 characters.',
            'description.max' => 'The description can have up to 500 characters.',
        ]);

        $incident = $incidents->createManual($request->user(), $incidents->prepare($data), [], $request);

        return redirect()->route('manager.incidents.index')->with('status', $incident->wasRecentlyCreated
            ? "{$incident->code} was reported."
            : "This damage was already reported today as {$incident->code}. Your report was added to it.");
    }

    public function show(Incident $incident, BanService $bans, IncidentService $incidents): View
    {
        return $this->managerPage("Incident {$incident->code}", 'incidents', view('staff.manager.incidents.show', [
            'incident' => $incident->load(['reporter', 'resolver', 'slot', 'session', 'confirmer', 'notes.user']),
            'statuses' => Incident::STATUSES,
            'counted' => $incident->attributed_plate ? $bans->counted($incident->attributed_plate) : collect(),
            'ban' => $incident->attributed_plate ? $bans->activeBan($incident->attributed_plate) : null,
            'threshold' => $bans->threshold(),
            'blockers' => $incident->status === 'resolved' ? [] : $incidents->resolutionBlockers($incident),
        ]));
    }

    /** The Manager confirms the report. A confirmed report counts toward the three-incident ban of the responsible plate. */
    public function confirm(Request $request, Incident $incident, BanService $bans): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $result = $bans->confirm($incident, $request->user(), $data['note'] ?? null, $request);

        if (! $result['changed']) {
            return back()->with('status', 'This report was already reviewed. Nothing changed.');
        }

        $message = "{$incident->code} was confirmed.";
        if ($result['counts']) {
            $message .= $result['banned']
                ? ' This was incident '.$result['count'].' for '.\App\Support\Lot::plate($result['plate']).'. The vehicle is now banned.'
                : ' It counts as incident '.$result['count'].' of '.$bans->threshold().' for '.\App\Support\Lot::plate($result['plate']).'.';
        } elseif (! $incident->attributed_plate && in_array($incident->category, Incident::BAN_CATEGORIES, true)) {
            $message .= ' No responsible vehicle is recorded, so it does not count toward a ban.';
        }

        return back()->with('status', $message);
    }

    public function dismiss(Request $request, Incident $incident, BanService $bans): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']], ['note.required' => 'Say why the report is not valid.', 'note.min' => 'Say why the report is not valid.']);

        return back()->with('status', $bans->dismiss($incident, $request->user(), $data['note'], $request)
            ? "{$incident->code} was dismissed. It stays on file and does not count toward a ban."
            : 'This report was already reviewed. Nothing changed.');
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
