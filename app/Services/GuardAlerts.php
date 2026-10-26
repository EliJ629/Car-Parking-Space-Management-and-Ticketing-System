<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Notice;
use App\Models\ParkingSession;
use App\Support\Lot;
use Illuminate\Support\Collection;

/**
 * What the Alerts button on the Guard console shows: every open automatic
 * long-parking incident (it stays until resolved) plus the unread alerts
 * stored for the guard role (for example an exit window that ended).
 */
class GuardAlerts
{
    public function __construct(private GateService $gate) {}

    /** @return Collection<int, array<string, mixed>> */
    public function items(): Collection
    {
        $long = $this->gate->openLongParkingAlerts()->map(function (Incident $incident): array {
            $minutes = $incident->session ? Lot::minutesBetween($incident->session->entered_at) : null;

            return [
                'key' => 'inc-'.$incident->id,
                'title' => 'Long-parked vehicle',
                'body' => Lot::plate($incident->plate_number).' in '.($incident->slot?->code ?? $incident->location ?? '-')
                    .($minutes !== null ? ' has parked '.Lot::duration($minutes) : '').'. '.$incident->code.' is '.strtolower(Incident::statusLabel($incident->status)).'.',
                'time' => Lot::stamp($incident->reported_at),
                'url' => route('guard.incidents.show', $incident),
                'unread' => true,
                'sort' => $incident->reported_at?->getTimestamp() ?? 0,
            ];
        });

        $notices = Notice::query()->forRole('guard')->latest('id')->limit(15)->get()->map(function (Notice $n): array {
            return [
                'key' => 'n-'.$n->id,
                'title' => $n->title,
                'body' => $n->body,
                'time' => Lot::stamp($n->created_at),
                'url' => match ($n->subject_type) {
                    'incident' => route('guard.incidents.show', $n->subject_id),
                    'session' => route('guard.gate', ['tab' => 'search', 'q' => ParkingSession::query()->whereKey($n->subject_id)->value('ticket_number')]),
                    default => route('guard.gate'),
                },
                'unread' => $n->isUnread(),
                'sort' => $n->created_at->getTimestamp(),
            ];
        });

        return $long->concat($notices)->sortByDesc('sort')->values();
    }

    public function count(?Collection $items = null): int
    {
        return ($items ?? $this->items())->where('unread', true)->count();
    }

    public function markRead(): void
    {
        Notice::query()->forRole('guard')->unread()->update(['read_at' => now()]);
    }
}
