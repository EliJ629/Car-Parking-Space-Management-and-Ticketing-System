<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Models\ParkingReservation;
use App\Services\ReservationService;
use App\Support\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reservation approval. A new request waits here until the booth attendant approves it
 * (the driver then gets the QR code) or rejects it (the driver is told to pick another slot).
 * A PWD slot also shows the ID photo the driver uploaded.
 */
class ReservationController extends Controller
{
    /** The reasons offered when turning a request down. "Other" opens a text box. */
    public const REASONS = [
        'The ID photo is not readable. Upload a clearer photo.',
        'The ID does not look valid for a PWD slot.',
        'The ID does not match the account holder.',
        'The slot cannot be held for that time.',
    ];

    public function index(Request $request): View
    {
        $pending = ParkingReservation::query()
            ->where('status', ParkingReservation::STATUS_BOOKED)
            ->where('approval_status', ParkingReservation::APPROVAL_PENDING)
            ->with(['driver', 'slot', 'vehicle'])
            ->orderBy('scheduled_at')
            ->get();

        $decided = ParkingReservation::query()
            ->whereIn('approval_status', [ParkingReservation::APPROVAL_APPROVED, ParkingReservation::APPROVAL_REJECTED])
            ->where(fn ($q) => $q->where('approved_at', '>=', Lot::dayStart())->orWhere('rejected_at', '>=', Lot::dayStart()))
            ->with(['driver', 'slot', 'approver'])
            ->latest('updated_at')
            ->limit(15)
            ->get();

        return view('staff.attendant.reservations.index', [
            'pending' => $pending,
            'decided' => $decided,
            'reasons' => self::REASONS,
        ]);
    }

    /** The ID photo, streamed from a private disk. Only booth staff can open it. */
    public function idPhoto(ParkingReservation $reservation): StreamedResponse
    {
        $path = $reservation->id_photo_path;
        $disk = Storage::disk(ReservationService::ID_DISK);

        abort_unless($reservation->requires_id && $path && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function approve(Request $request, ParkingReservation $reservation, ReservationService $reservations): RedirectResponse
    {
        if ($reservation->isAwaitingApproval() && $reservation->requires_id && ! $reservation->id_photo_path) {
            return redirect()->route('attendant.reservations.index')->with('modal', [
                'title' => 'No ID photo to check',
                'body' => "{$reservation->code} is for a PWD slot but has no ID photo. Reject it so the driver can try again.",
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        if (! $reservations->approve($reservation, $request->user(), $request)) {
            return redirect()->route('attendant.reservations.index')->with('modal', $this->alreadyDecided($reservation));
        }

        return redirect()->route('attendant.reservations.index')->with('modal', [
            'title' => 'Reservation approved',
            'body' => "{$reservation->code} is approved. The driver was notified and the QR code is now on their phone.",
            'primary' => ['label' => 'OK', 'close' => true],
        ]);
    }

    public function reject(Request $request, ParkingReservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:300'],
            'reason_other' => ['nullable', 'string', 'max:250'],
        ], [
            'reason.required' => 'Choose why the reservation is not approved.',
        ]);

        $reason = $data['reason'] === 'other' ? trim((string) ($data['reason_other'] ?? '')) : $data['reason'];

        if ($data['reason'] !== 'other' && ! in_array($data['reason'], self::REASONS, true)) {
            return redirect()->route('attendant.reservations.index')->with('modal', [
                'title' => 'Choose a reason from the list',
                'body' => 'The reason was not one of the choices. Pick one, or choose Other and type it.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        if (mb_strlen($reason) < 3) {
            return redirect()->route('attendant.reservations.index')->with('modal', [
                'title' => 'Type the reason',
                'body' => 'Tell the driver why the reservation is not approved, in a few words.',
                'primary' => ['label' => 'OK', 'close' => true],
            ]);
        }

        if (! $reservations->reject($reservation, $request->user(), $reason, $request)) {
            return redirect()->route('attendant.reservations.index')->with('modal', $this->alreadyDecided($reservation));
        }

        return redirect()->route('attendant.reservations.index')->with('modal', [
            'title' => 'Reservation rejected',
            'body' => "{$reservation->code} was rejected and slot {$reservation->slot_code} is free again. The driver was notified and must choose a new slot.",
            'primary' => ['label' => 'OK', 'close' => true],
        ]);
    }

    /** @return array<string, mixed> */
    private function alreadyDecided(ParkingReservation $reservation): array
    {
        return [
            'title' => 'Nothing changed',
            'body' => "{$reservation->code} is now ".strtolower($reservation->fresh()->statusLabel()).'. It was already decided or the driver canceled it.',
            'primary' => ['label' => 'OK', 'close' => true],
        ];
    }
}
