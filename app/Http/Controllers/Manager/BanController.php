<?php

namespace App\Http\Controllers\Manager;

use App\Models\BannedPlate;
use App\Models\Incident;
use App\Services\BanService;
use App\Support\PlateNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The banned vehicles list: who is blocked, why, and lifting a ban. Only the Manager changes it. */
class BanController extends ManagerController
{
    public function index(Request $request, BanService $bans): View
    {
        $filters = $request->validate([
            'state' => ['nullable', 'in:active,lifted'],
            'q' => ['nullable', 'string', 'max:20'],
        ]);

        $query = BannedPlate::query()->with(['owner', 'banner', 'lifter'])
            ->when(($filters['state'] ?? 'active') === 'lifted', fn ($q) => $q->whereNotNull('lifted_at'), fn ($q) => $q->whereNull('lifted_at'))
            ->when($filters['q'] ?? null, fn ($q, string $search) => $q->where('plate_number', 'like', '%'.PlateNumber::normalize($search).'%'))
            ->latest('banned_at');

        $rows = $query->paginate(20)->withQueryString();

        // Pending reports that would add to a count, so the Manager sees what is coming.
        $pending = Incident::query()->where('confirmation', Incident::PENDING)->whereNotNull('attributed_plate')->whereIn('category', Incident::BAN_CATEGORIES)->count();

        return $this->managerPage('Banned vehicles', 'bans', view('staff.manager.bans.index', [
            'bans' => $rows,
            'filters' => $filters,
            'threshold' => $bans->threshold(),
            'pending' => $pending,
            'counted' => fn (string $plate) => $bans->counted($plate),
        ]));
    }

    public function lift(Request $request, BannedPlate $ban, BanService $bans): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']], ['note.required' => 'Say why the ban is lifted.', 'note.min' => 'Say why the ban is lifted.']);

        return back()->with('status', $bans->lift($ban, $request->user(), $data['note'], $request)
            ? PlateNumber::format($ban->plate_number).' can park again. The incidents stay on file.'
            : 'That ban was already lifted.');
    }
}
