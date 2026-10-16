<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Services\DriverData;
use App\Support\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $notices = Notice::query()->forDriver($request->user())->latest('id')->limit(100)->get();
        $startOfToday = Lot::dayStart();

        return view('driver.app.notices.index', [
            'today' => $notices->filter(fn (Notice $n) => $n->created_at->greaterThanOrEqualTo($startOfToday))->values(),
            'earlier' => $notices->filter(fn (Notice $n) => $n->created_at->lessThan($startOfToday))->values(),
            'unread' => $notices->whereNull('read_at')->count(),
        ]);
    }

    public function show(Request $request, Notice $notice): View
    {
        abort_unless($notice->user_id === $request->user()->id, 404);

        if ($notice->isUnread()) {
            $notice->update(['read_at' => now()]);
        }

        $subject = match ($notice->subject_type) {
            'reservation' => ParkingReservation::query()->with('slot')->find($notice->subject_id),
            'session' => ParkingSession::query()->with(['slot', 'payment'])->find($notice->subject_id),
            'incident' => Incident::query()->with('slot')->find($notice->subject_id),
            default => null,
        };

        return view('driver.app.notices.show', ['notice' => $notice, 'subject' => $subject]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notice::query()->forDriver($request->user())->unread()->update(['read_at' => now()]);

        return redirect()->route('driver.notices');
    }

    /** Polled by the app to update the bell and show a banner for a new notice. */
    public function count(Request $request, DriverData $data): JsonResponse
    {
        $user = $request->user();
        $latest = Notice::query()->forDriver($user)->unread()->latest('id')->first();

        return response()->json([
            'count' => $data->unread($user),
            'latest' => $latest ? ['id' => $latest->id, 'title' => $latest->title, 'body' => $latest->body, 'url' => route('driver.notices.show', $latest)] : null,
        ]);
    }
}
