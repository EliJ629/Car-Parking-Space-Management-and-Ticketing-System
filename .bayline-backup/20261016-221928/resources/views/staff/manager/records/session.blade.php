<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.records.index') }}">Records</a> / Session detail</p><h1>{{ $session->ticket_number }}</h1><p class="manager-page-lede">Session and vehicle details are preserved for audit. A session can only be changed through the entry and exit workflows.</p></section>
<section class="manager-panel manager-detail-panel">
  <div class="manager-data-grid">
    <div><span>Plate</span><strong>{{ $session->plate_number }}</strong></div>
    <div><span>Vehicle</span><strong>{{ $session->vehicle_color }} {{ $session->vehicle_type }}</strong></div>
    <div><span>Slot</span><strong>{{ $session->slot?->code ?? $session->slot_code ?? 'Slot removed' }}</strong></div>
    <div><span>Status</span><strong>{{ strtoupper(str_replace('_', ' ', $session->status)) }}</strong></div>
    <div><span>Entry</span><strong>{{ $session->entered_at->format('M j, Y g:i A') }}</strong></div>
    <div><span>Exit</span><strong>{{ $session->exited_at?->format('M j, Y g:i A') ?? 'Still parked' }}</strong></div>
    <div><span>Attendant</span><strong>{{ $session->attendant?->name ?? '—' }}</strong></div>
  </div>
  <h2 class="manager-subheading">Payment receipt</h2>
  @if ($session->payment)
    <div class="manager-data-grid"><div><span>Receipt</span><strong>{{ $session->payment->receipt_number }}</strong></div><div><span>Paid at</span><strong>{{ $session->payment->paid_at->format('M j, Y g:i A') }}</strong></div><div><span>Base fee</span><strong>₱{{ number_format((float) $session->payment->base_fee, 2) }}</strong></div><div><span>Additional</span><strong>₱{{ number_format((float) $session->payment->additional_fee, 2) }}</strong></div><div><span>Penalty</span><strong>₱{{ number_format((float) $session->payment->penalty, 2) }}</strong></div><div><span>Discount</span><strong>₱{{ number_format((float) $session->payment->discount, 2) }}</strong></div><div><span>Total</span><strong>₱{{ number_format((float) $session->payment->total, 2) }}</strong></div></div>
  @else
    <p class="manager-muted">No payment has been recorded for this session.</p>
  @endif
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.records.index', ['tab' => 'sessions', 'date' => $session->entered_at->toDateString()]) }}">Back to records</a></div>
</section>
