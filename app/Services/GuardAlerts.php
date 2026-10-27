<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\ParkingSession;
use App\Support\Lot;
use Illuminate\Support\Collection;

/**
 * What the Alerts button on the Guard console shows: stored alerts for the
 * guard role. The read state belongs to each persisted notice.
 */
class GuardAlerts
{
    public function __construct(private GateService $gate) {}

    /** @return Collection<int, array<string, mixed>> */
    public function items(): Collection
    {
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
                'sort' => $n->created_at?->getTimestamp() ?? 0,
            ];
        });

        return $notices->sortByDesc('sort')->values();
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
