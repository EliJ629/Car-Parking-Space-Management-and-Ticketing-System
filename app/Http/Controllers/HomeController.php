<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first screen after sign in. These pages are the starting point for the
 * next sprints: each account type gets its own workspace here.
 */
class HomeController extends Controller
{
    public function staff(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === User::ROLE_MANAGER) {
            return redirect()->route('manager.dashboard');
        }

        return view('staff.home', [
            'user' => $user,
            'tools' => $this->toolsFor($user),
        ]);
    }

    public function driver(Request $request): View
    {
        $user = $request->user();

        return view('driver.home', [
            'user' => $user,
            'vehicles' => $user->vehicles()->orderBy('created_at')->get(),
        ]);
    }

    /**
     * What each post will find in its workspace. Replace this with the real
     * screens as they are built.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function toolsFor(User $user): array
    {
        return match ($user->role) {
            User::ROLE_MANAGER => [
                ['Dashboard', 'Sales, slot status and open alerts in one view.'],
                ['Slots', 'Add, edit and take bays out of service.'],
                ['Rates and policies', 'Hourly rates, discounts, penalties and the 12-hour limit.'],
                ['Accounts and logs', 'Create staff accounts, reset passwords and read the activity log.'],
            ],
            User::ROLE_ATTENDANT => [
                ['New entry', 'Walk-in details and a slot pick, or a reservation scan. The ticket prints.'],
                ['Exit and payment', 'Fee breakdown, discounts, cash and receipt.'],
                ['Parking slots', 'Live board with logged manual corrections.'],
                ['Records', 'Today by default, searchable by ticket or plate, plus the shift summary.'],
            ],
            default => [
                ['Gate validation', 'Paid cars in a queue. Good to Go, or search by plate or ticket.'],
                ['Incident reports', 'Suspicious vehicles, damage, violations, mismatches and unpaid exits.'],
                ['Long-parked reports', 'Automatic alerts after the limit, or a manual report by plate.'],
                ['Records', 'Parking records and every incident, filtered by date, type and status.'],
            ],
        };
    }
}
