@extends('layouts.attendant')

@section('title', 'Parking records - Booth Attendant')
@section('tab', 'records')

@section('content')
<h1 class="ba-page-title">Parking records</h1>
<p class="ba-sub">Showing records for <b>{{ $filterLabel }}</b>. Sessions with an entry or an exit in this period are listed.</p>

<div class="ba-card" style="margin-bottom:20px;">
  <form method="GET" action="{{ route('attendant.records.index') }}" id="records-filter-form" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;">
      <label class="ba-label" for="rec-q">Search</label>
      <input class="ba-input" id="rec-q" name="q" type="text" value="{{ $q }}" placeholder="Ticket, plate, or attendant"
             maxlength="20" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s-]/g, '').slice(0, 20);">
    </div>

    <div style="min-width:160px;">
      <label class="ba-label" for="rec-filter">Period</label>
      <select class="ba-input" id="rec-filter" name="filter" style="cursor:pointer;">
        <option value="today" @selected($filter === 'today')>Today</option>
        <option value="this_week" @selected($filter === 'this_week')>This week</option>
        <option value="this_month" @selected($filter === 'this_month')>This month</option>
        <option value="custom" @selected($filter === 'custom')>Custom</option>
      </select>
    </div>

    <div id="custom-date-field" style="display:{{ $filter === 'custom' ? 'block' : 'none' }};min-width:160px;">
      <label class="ba-label" for="rec-date">Date</label>
      <input class="ba-input" id="rec-date" name="date" type="date" value="{{ $customDate }}" max="{{ now()->toDateString() }}">
    </div>

    <div style="display:flex;gap:8px;">
      <button class="btn primary" type="submit">Search</button>
      @if ($q || $filter !== 'today')
        <a class="btn ghost" href="{{ route('attendant.records.index') }}">Reset</a>
      @endif
    </div>
  </form>
</div>

@if ($sessions->isEmpty())
  <!-- Empty State (BA-60) -->
  <div class="ba-card" style="text-align:center;padding:48px 24px;">
    <h2 style="font-size:24px;margin-bottom:8px;">No records found</h2>
    <p style="color:var(--muted);max-width:32em;margin:0 auto 20px;">
      No parking record matches <b>{{ $q ?: 'the selected criteria' }}</b> for <b>{{ $filterLabel }}</b>. Check the text or choose another period.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;">
      @if ($q)
        <a class="btn ghost" href="{{ route('attendant.records.index', ['filter' => $filter, 'date' => $customDate]) }}">Clear search</a>
      @endif
      <a class="btn primary" href="{{ route('attendant.records.index') }}">Back to today</a>
    </div>
  </div>
@else
  <!-- Records Table (BA-57) -->
  <div class="ba-card">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
        <thead>
          <tr style="border-bottom:2px solid var(--line);color:var(--muted);font-size:12px;text-transform:uppercase;">
            <th style="padding:10px 8px;">Date</th>
            <th style="padding:10px 8px;">Entry</th>
            <th style="padding:10px 8px;">Exit</th>
            <th style="padding:10px 8px;">Ticket</th>
            <th style="padding:10px 8px;">Plate</th>
            <th style="padding:10px 8px;">Color</th>
            <th style="padding:10px 8px;">Type</th>
            <th style="padding:10px 8px;">Slot</th>
            <th style="padding:10px 8px;">Duration</th>
            <th style="padding:10px 8px;">Payment</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($sessions as $s)
            @php
                $durationMins = \App\Support\Lot::minutesBetween($s->entered_at, $s->exited_at ?? now());
            @endphp
            <tr style="border-bottom:1px solid var(--line);cursor:pointer;"
                onclick="openRecordDetail({{ json_encode([
                    'ticket' => $s->ticket_number,
                    'plate' => \App\Support\Lot::plate($s->plate_number),
                    'vehicle' => "{$s->vehicle_color} {$s->vehicle_type}",
                    'slot' => "{$s->slot_code}, {$s->slot?->zone}",
                    'entry' => \App\Support\Lot::stamp($s->entered_at) . ($s->attendant ? ', ' . $s->attendant->name : ''),
                    'exit' => $s->exited_at ? \App\Support\Lot::stamp($s->exited_at) . ($s->exitApprover ? ', approved by ' . $s->exitApprover->name : '') : '-',
                    'duration' => \App\Support\Lot::duration($durationMins),
                    'payment_status' => $s->payment ? 'PAID ' . \App\Support\Lot::peso($s->payment->total) : 'UNPAID',
                    'receipt' => $s->payment?->receipt_number ?? '-',
                    'receipt_url' => $s->payment ? route('attendant.exit.receipt', $s->payment) : null,
                ]) }})">
              <td style="padding:12px 8px;">{{ \App\Support\Lot::local($s->entered_at)->format('M j') }}</td>
              <td style="padding:12px 8px;">{{ \App\Support\Lot::time($s->entered_at) }}</td>
              <td style="padding:12px 8px;">
                @if ($s->status === 'paid')
                  <span class="badge pending">PENDING EXIT</span>
                @elseif ($s->exited_at)
                  {{ \App\Support\Lot::time($s->exited_at) }}
                @else
                  -
                @endif
              </td>
              <td style="padding:12px 8px;font-weight:700;">{{ $s->ticket_number }}</td>
              <td style="padding:12px 8px;font-weight:700;">{{ \App\Support\Lot::plate($s->plate_number) }}</td>
              <td style="padding:12px 8px;">{{ $s->vehicle_color }}</td>
              <td style="padding:12px 8px;">{{ $s->vehicle_type }}</td>
              <td style="padding:12px 8px;font-weight:700;">{{ $s->slot_code }}</td>
              <td style="padding:12px 8px;">{{ \App\Support\Lot::duration($durationMins) }}</td>
              <td style="padding:12px 8px;">
                @if ($s->payment)
                  <span class="badge paid">PAID</span>
                @else
                  <span class="badge unpaid">UNPAID</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="margin-top:16px;">
      {{ $sessions->links() }}
    </div>
  </div>
@endif

<!-- POP-UP BA-61: Record Detail Modal -->
<dialog class="badlg" id="record-detail-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Record <span id="rd-ticket"></span></h2>

    <div class="ba-detail-box" style="display:flex;flex-direction:column;gap:10px;font-size:14px;background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:18px;color:var(--ink);">
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Ticket</span><b id="rd-ticket-val"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Plate</span><b id="rd-plate"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Vehicle</span><b id="rd-veh"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Slot</span><b id="rd-slot"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Entry</span><span id="rd-entry"></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Exit</span><span id="rd-exit"></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Duration</span><b id="rd-duration"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Payment</span><b id="rd-payment" style="color:var(--ok);"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Receipt</span><b id="rd-receipt"></b></div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Close</button>
      <a class="btn primary" id="rd-reprint-btn" href="#" style="display:none;">Reprint receipt</a>
    </div>
  </div>
</dialog>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const filterSelect = document.getElementById('rec-filter');
  const customDateField = document.getElementById('custom-date-field');
  const dateInput = document.getElementById('rec-date');
  const form = document.getElementById('records-filter-form');

  if (filterSelect && customDateField && form) {
    filterSelect.addEventListener('change', () => {
      if (filterSelect.value === 'custom') {
        customDateField.style.display = 'block';
        if (dateInput) {
          dateInput.focus();
        }
      } else {
        customDateField.style.display = 'none';
        form.submit();
      }
    });

    if (dateInput) {
      dateInput.addEventListener('change', () => {
        if (filterSelect.value === 'custom') {
          form.submit();
        }
      });
    }
  }
});

window.openRecordDetail = function(rec) {
  document.getElementById('rd-ticket').textContent = rec.ticket;
  document.getElementById('rd-ticket-val').textContent = rec.ticket;
  document.getElementById('rd-plate').textContent = rec.plate;
  document.getElementById('rd-veh').textContent = rec.vehicle;
  document.getElementById('rd-slot').textContent = rec.slot;
  document.getElementById('rd-entry').textContent = rec.entry;
  document.getElementById('rd-exit').textContent = rec.exit;
  document.getElementById('rd-duration').textContent = rec.duration;
  document.getElementById('rd-payment').textContent = rec.payment_status;
  document.getElementById('rd-receipt').textContent = rec.receipt;

  const reprintBtn = document.getElementById('rd-reprint-btn');
  if (rec.receipt_url) {
    reprintBtn.href = rec.receipt_url;
    reprintBtn.style.display = 'inline-flex';
  } else {
    reprintBtn.style.display = 'none';
  }

  document.getElementById('record-detail-dlg').showModal();
};
</script>
@endpush
@endsection
