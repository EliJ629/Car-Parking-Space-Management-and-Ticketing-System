@extends('layouts.attendant')

@section('title', 'Exit and payment - Booth Attendant')
@section('tab', 'exit')

@section('content')
<h1 class="ba-page-title">Exit and payment</h1>
<p class="ba-sub">Search by ticket number or plate number.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step active">1. Search</span>
  <span class="ba-step">2. Fee</span>
  <span class="ba-step">3. Payment</span>
  <span class="ba-step">4. Receipt</span>
  <span class="ba-step">5. Guard approval</span>
</div>

<div style="display:grid;grid-template-columns:1.8fr 1fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Search Form & Results -->
  <div>
    <div class="ba-card" style="margin-bottom:20px;">
      <h2>Find the parking session</h2>

      <form method="GET" action="{{ route('attendant.exit.index') }}">
        <div class="ba-form-group">
          <label class="ba-label" for="exit-q">Ticket number or plate number</label>
          <input class="ba-input" id="exit-q" name="q" type="text" value="{{ $q }}"
                 placeholder="PK-0016 or HJK 4471" style="font-size:20px;font-weight:700;text-transform:uppercase;"
                 autocomplete="off" autofocus>
          <p style="font-size:12px;color:var(--muted);margin-top:4px;">Type at least 3 characters.</p>
        </div>

        <div class="ba-form-group">
          <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
            <input type="checkbox" name="include_closed" value="1" @checked($includeClosed) style="width:18px;height:18px;">
            Include closed sessions
          </label>
        </div>

        <div style="display:flex;gap:10px;align-items:center;">
          <button class="btn primary" type="submit">Search</button>
          <a class="btn ghost" href="{{ route('attendant.exit.index') }}">Clear</a>
        </div>
      </form>
    </div>

    <!-- Search Results Table (BA-35) -->
    @if ($searched && $results->isNotEmpty())
      <div class="ba-card">
        <h2>{{ $results->count() }} {{ \Illuminate\Support\Str::plural('match', $results->count()) }} for {{ $q }}</h2>

        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
            <thead>
              <tr style="border-bottom:2px solid var(--line);color:var(--muted);font-size:12px;text-transform:uppercase;">
                <th style="padding:10px 8px;">Ticket</th>
                <th style="padding:10px 8px;">Plate</th>
                <th style="padding:10px 8px;">Vehicle</th>
                <th style="padding:10px 8px;">Slot</th>
                <th style="padding:10px 8px;">Entry</th>
                <th style="padding:10px 8px;">Duration</th>
                <th style="padding:10px 8px;">Status</th>
                <th style="padding:10px 8px;text-align:right;">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($results as $s)
                @php
                    $durationMins = \App\Support\Lot::minutesBetween($s->entered_at, $s->exited_at ?? now());
                @endphp
                <tr style="border-bottom:1px solid var(--line);">
                  <td style="padding:12px 8px;font-weight:700;">{{ $s->ticket_number }}</td>
                  <td style="padding:12px 8px;font-weight:700;">{{ \App\Support\Lot::plate($s->plate_number) }}</td>
                  <td style="padding:12px 8px;">{{ $s->vehicle_color }} {{ $s->vehicle_type }}</td>
                  <td style="padding:12px 8px;font-weight:700;">{{ $s->slot_code }}</td>
                  <td style="padding:12px 8px;">{{ \App\Support\Lot::stamp($s->entered_at) }}</td>
                  <td style="padding:12px 8px;">{{ \App\Support\Lot::duration($durationMins) }}</td>
                  <td style="padding:12px 8px;">
                    @if ($s->status === 'paid')
                      <span class="badge paid">PAID</span> <span class="badge pending">PENDING EXIT</span>
                    @elseif ($s->status === 'completed')
                      <span class="badge completed">COMPLETED</span>
                    @else
                      <span class="badge unpaid">UNPAID</span>
                    @endif
                  </td>
                  <td style="padding:12px 8px;text-align:right;">
                    <a class="btn primary sm" href="{{ route('attendant.exit.show', $s) }}">Open</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p style="font-size:12px;color:var(--muted);margin-top:12px;">Active sessions only. Tick Include closed sessions to see finished ones.</p>
      </div>
    @endif
  </div>

  <!-- Right Column: Waiting for the Guard (BA-34) -->
  <div class="ba-card">
    <h2>Waiting for the Guard</h2>
    <div class="ba-list-group">
      @forelse ($waitingForGuard as $w)
        <a class="ba-item" href="{{ route('attendant.exit.show', $w) }}" style="text-decoration:none;">
          <div class="details">
            <b>{{ $w->slot_code }}, {{ \App\Support\Lot::plate($w->plate_number) }}</b>
            <p>Paid {{ \App\Support\Lot::time($w->payment?->paid_at) }}, receipt {{ $w->payment?->receipt_number ?? '-' }}</p>
          </div>
          <span class="badge pending">PENDING EXIT</span>
        </a>
      @empty
        <p style="color:var(--muted);font-size:14px;margin:0;">No vehicles currently waiting for the Guard.</p>
      @endforelse
    </div>
  </div>
</div>

<!-- POP-UP BA-36: No Session Found Modal -->
@if ($noMatch)
<dialog class="badlg auto-open" id="no-match-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>No session found</h2>
    <p>No active session matches <b>{{ $q }}</b>.<br><br>Check the digits with the driver. Closed sessions are hidden. Tick <b>Include closed sessions</b> to search them.</p>
    <div class="actions">
      <a class="btn ghost" href="{{ route('attendant.exit.index') }}">Clear</a>
      <button class="btn primary" type="button" data-close onclick="document.getElementById('exit-q').focus()">Search again</button>
    </div>
  </div>
</dialog>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const exitInput = document.getElementById('exit-q');
  if (!exitInput) return;

  let isDeleting = false;

  exitInput.addEventListener('keydown', (e) => {
    isDeleting = (e.key === 'Backspace' || e.key === 'Delete');
  });

  exitInput.addEventListener('input', () => {
    let val = exitInput.value.toUpperCase();

    // If backspacing and currently at "PK-", back up to "P"
    if (isDeleting) {
      if (val === 'PK-') {
        exitInput.value = 'P';
      }
      return;
    }

    // If user types a third letter after "PK-" (e.g. plate PKR 1234), remove dash
    if (/^PK-([A-Z])/i.test(val)) {
      exitInput.value = 'PK' + val.slice(3);
      return;
    }

    // If user typed/pasted "PK" followed by numbers without dash: "PK16" -> "PK-16"
    if (/^PK[0-9]/i.test(val)) {
      exitInput.value = 'PK-' + val.slice(2);
      return;
    }

    // When "PK" is typed, automatically append dash: "PK" -> "PK-"
    if (val === 'PK') {
      exitInput.value = 'PK-';
    }
  });
});
</script>
@endpush
@endsection
