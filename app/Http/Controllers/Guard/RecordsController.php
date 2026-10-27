<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\Vehicle;
use App\Support\Lot;
use App\Support\SearchTerm;
use App\Support\TicketNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Read only history of parking sessions and incidents. */
class RecordsController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'incidents' ? 'incidents' : 'parking';
        [$from, $to, $range] = $this->range($request);
        $q = SearchTerm::normalize($request->query('q'));

        $filters = ['range' => $range, 'from' => Lot::local($from)->format('Y-m-d'), 'to' => Lot::local($to)->format('Y-m-d'), 'q' => $q];
        $data = ['tab' => $tab, 'f' => $filters, 'rangeText' => $this->rangeText($from, $to, $range), 'detail' => null];

        if ($tab === 'parking') {
            $payment = in_array($request->query('payment'), ['paid', 'unpaid'], true) ? $request->query('payment') : 'all';

            $query = ParkingSession::query()->with(['slot', 'payment', 'exitApprover'])
                ->whereBetween('entered_at', [$from, $to])
                ->when($q !== '', function (Builder $b) use ($q) {
                    $plate = Vehicle::normalizePlate($q);
                    $ticket = TicketNumber::looksLikeTicket($q) ? TicketNumber::normalize($q) : null;
                    $b->where(fn (Builder $w) => $w->where('ticket_number', 'like', '%'.strtoupper($q).'%')->orWhere('plate_number', 'like', '%'.($plate ?: strtoupper($q)).'%')->when($ticket, fn (Builder $t) => $t->orWhere('ticket_number', $ticket)));
                })
                ->when($payment === 'paid', fn (Builder $b) => $b->whereIn('status', [ParkingSession::STATUS_PAID, ParkingSession::STATUS_COMPLETED]))
                ->when($payment === 'unpaid', fn (Builder $b) => $b->where('status', ParkingSession::STATUS_ACTIVE))
                ->orderByDesc('entered_at');

            $data['payment'] = $payment;
            $data['rows'] = $query->paginate(10)->withQueryString();

            if ($id = (int) $request->query('view')) {
                $data['detail'] = ParkingSession::query()->with(['slot', 'payment.attendant', 'exitApprover', 'incidents'])->find($id);
            }
        } else {
            $status = in_array($request->query('status'), Incident::STATUSES, true) ? $request->query('status') : null;
            $category = in_array($request->query('category'), Incident::CATEGORIES, true) ? $request->query('category') : null;

            $query = Incident::query()->with(['reporter', 'slot'])
                ->whereBetween('reported_at', [$from, $to])
                ->when($q !== '', function (Builder $b) use ($q) {
                    $plate = Vehicle::normalizePlate($q);
                    $b->where(fn (Builder $w) => $w->where('code', 'like', '%'.strtoupper($q).'%')->orWhere('plate_number', 'like', '%'.($plate ?: strtoupper($q)).'%'));
                })
                ->when($status, fn (Builder $b) => $b->where('status', $status))
                ->when($category, fn (Builder $b) => $b->where('category', $category))
                ->orderByDesc('reported_at');

            $data += ['status' => $status, 'category' => $category, 'categories' => Incident::CATEGORIES, 'rows' => $query->paginate(10)->withQueryString()];

            if ($id = (int) $request->query('view')) {
                $data['detail'] = Incident::query()->with(['reporter', 'resolver', 'slot', 'photos'])->find($id);
            }
        }

        return view('guard.records.index', $data);
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function range(Request $request): array
    {
        $range = in_array($request->query('range'), ['today', '7', '30', 'all', 'custom'], true) ? $request->query('range') : '7';
        $end = Lot::dayEnd();

        return match ($range) {
            'today' => [Lot::dayStart(), $end, $range],
            '30' => [Lot::dayStart()->subDays(29), $end, $range],
            'all' => [Carbon::create(2000, 1, 1), $end, $range],
            'custom' => $this->custom($request, $end),
            default => [Lot::dayStart()->subDays(6), $end, '7'],
        };
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function custom(Request $request, Carbon $end): array
    {
        $valid = fn (?string $d) => $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from = $valid($request->query('from')) ? Lot::parse($request->query('from')) : Lot::dayStart()->subDays(6);
        $to = $valid($request->query('to')) ? Lot::dayEnd(Lot::parse($request->query('to'))) : $end;

        // A record cannot be in the future, so neither end of the range can be later than today.
        if ($from->greaterThan($end)) {
            $from = Lot::dayStart();
        }

        if ($to->greaterThan($end)) {
            $to = $end;
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [Lot::dayStart($to), Lot::dayEnd($from)];
        }

        return [$from, $to, 'custom'];
    }

    private function rangeText(Carbon $from, Carbon $to, string $range): string
    {
        return match ($range) {
            'today' => 'Today',
            'all' => 'All dates',
            default => Lot::local($from)->format('M j').' to '.Lot::local($to)->format('M j, Y'),
        };
    }
}
