<section class="manager-page-heading"><p class="eyebrow">Manager records</p><h1>Records</h1><p class="manager-page-lede">Parking sessions, cash payments, and reservations. Records are retained as an operational history.</p></section>
<nav class="manager-tabs" aria-label="Record type">
  @foreach (['sessions' => 'Parking sessions', 'payments' => 'Payments', 'reservations' => 'Reservations'] as $key => $label)
    <a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route('manager.records.index', ['tab' => $key, 'date' => $date]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.records.index') }}">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="manager-filter-field"><label class="f" for="record-date">Date</label><input id="record-date" name="date" type="date" value="{{ $date }}" required></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="record-search">Search</label><input id="record-search" name="q" value="{{ $search }}" maxlength="100" placeholder="Plate, ticket, receipt, or slot"></div>
  @if ($tab !== 'payments')
  <div class="manager-filter-field"><label class="f" for="record-status">Status</label><select id="record-status" name="status"><option value="">All statuses</option>@foreach (\App\Http\Controllers\Manager\RecordsController::STATUSES as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>@endforeach</select></div>
  @endif
  <button class="btn sm" type="submit">Filter</button>
  <a class="btn ghost sm" href="{{ route('manager.records.export', ['tab' => $tab, 'date' => $date]) }}">Export CSV</a>
</form>

@if ($tab === 'sessions')
  <div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Date / entry</th><th>Exit</th><th>Ticket</th><th>Plate</th><th>Vehicle</th><th>Slot</th><th>Duration</th><th>Payment</th></tr></thead><tbody>
  @forelse ($sessions as $session)
    <tr><td>{{ \App\Support\Lot::local($session->entered_at)?->format('M j, g:i A') }}</td><td>{{ \App\Support\Lot::local($session->exited_at)?->format('g:i A') ?? '—' }}</td><th scope="row"><a class="manager-slot-code" href="{{ route('manager.records.sessions.show', $session) }}">{{ $session->ticket_number }}</a></th><td>{{ $session->plate_number }}</td><td>{{ $session->vehicle_color }} {{ $session->vehicle_type }}</td><td>{{ $session->slot?->code ?? $session->slot_code ?? '—' }}</td><td>{{ $session->exited_at ? $session->entered_at->diffForHumans($session->exited_at, true) : $session->entered_at->diffForHumans(null, true) }}</td><td>{{ $session->payment ? 'PAID' : strtoupper(str_replace('_', ' ', $session->status)) }}</td></tr>
  @empty<tr><td colspan="8" class="manager-table-empty">No parking sessions found for this date. Sessions appear here when booth entry operations are connected.</td></tr>@endforelse
  </tbody></table></div><div class="manager-table-footer"><span>{{ $sessions->total() }} sessions</span>{{ $sessions->links() }}</div>
@elseif ($tab === 'payments')
  <div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Receipt</th><th>Time</th><th>Ticket</th><th>Plate</th><th>Base</th><th>Additional</th><th>Penalty</th><th>Discount</th><th>Total</th><th>Attendant</th></tr></thead><tbody>
  @forelse ($payments as $payment)
    <tr><th scope="row">{{ $payment->receipt_number }}</th><td>{{ \App\Support\Lot::local($payment->paid_at)?->format('g:i A') }}</td><td>{{ $payment->session?->ticket_number }}</td><td>{{ $payment->session?->plate_number }}</td><td>₱{{ number_format((float) $payment->base_fee, 2) }}</td><td>₱{{ number_format((float) $payment->additional_fee, 2) }}</td><td>₱{{ number_format((float) $payment->penalty, 2) }}</td><td>₱{{ number_format((float) $payment->discount, 2) }}</td><td><strong>₱{{ number_format((float) $payment->total, 2) }}</strong></td><td>{{ $payment->attendant?->name ?? '—' }}</td></tr>
  @empty<tr><td colspan="10" class="manager-table-empty">No payments found for this date.</td></tr>@endforelse
  </tbody></table></div><div class="manager-table-footer"><span>{{ $payments->total() }} payments</span>{{ $payments->links() }}</div>
@else
  <div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Code</th><th>Scheduled</th><th>Driver</th><th>Plate</th><th>Slot</th><th>Status</th><th>Ticket</th></tr></thead><tbody>
  @forelse ($reservations as $reservation)
    <tr><th scope="row">{{ $reservation->code }}</th><td>{{ \App\Support\Lot::local($reservation->scheduled_at)?->format('M j, g:i A') }}</td><td>{{ $reservation->driver?->name ?? '—' }}</td><td>{{ $reservation->plate_number }}</td><td>{{ $reservation->slot?->code ?? $reservation->slot_code ?? '—' }}</td><td>{{ strtoupper(str_replace('_', ' ', $reservation->status)) }}</td><td>{{ $reservation->session?->ticket_number ?? '—' }}</td></tr>
  @empty<tr><td colspan="7" class="manager-table-empty">No reservations found for this date.</td></tr>@endforelse
  </tbody></table></div><div class="manager-table-footer"><span>{{ $reservations->total() }} reservations</span>{{ $reservations->links() }}</div>
@endif
