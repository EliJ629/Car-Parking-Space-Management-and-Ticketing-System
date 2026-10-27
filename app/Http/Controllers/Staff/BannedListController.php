<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BannedPlate;
use App\Models\Incident;
use App\Services\BanService;
use App\Support\PlateNumber;
use App\Support\SearchTerm;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The banned list for the Guard and the booth attendant: who is banned, the contact details on record and the
 * reports that led to the ban. Read only. Only the Parking Manager lifts a ban.
 */
class BannedListController extends Controller
{
    public function guard(Request $request, BanService $bans): View
    {
        return view('guard.bans', $this->data($request, $bans) + ['incidentLinks' => true]);
    }

    public function attendant(Request $request, BanService $bans): View
    {
        return view('staff.attendant.bans', $this->data($request, $bans) + ['incidentLinks' => false]);
    }

    /** @return array<string, mixed> */
    private function data(Request $request, BanService $bans): array
    {
        $q = SearchTerm::normalize($request->query('q'));
        $plate = PlateNumber::normalize($q);
        $tail = BanService::phoneTail($q);

        $rows = BannedPlate::query()->active()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $plate, $tail) {
                $w->whereRaw('lower(driver_name) like ?', ['%'.mb_strtolower($q).'%']);

                if ($plate !== '') {
                    $w->orWhere('plate_number', 'like', '%'.$plate.'%');
                }

                if ($tail !== '') {
                    $w->orWhere('driver_phone', 'like', '%'.$tail.'%');
                }
            }))
            ->latest('banned_at')
            ->paginate(10)
            ->withQueryString();

        return [
            'bans' => $rows,
            'q' => $q,
            'threshold' => $bans->threshold(),
            'counted' => fn (string $plate) => $bans->counted($plate),
            'labelOf' => fn (Incident $i) => Incident::guardLabel($i->category),
        ];
    }
}
