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

      <form method="GET" action="{{ route('attendant.exit.index') }}" id="exit-search-form">
        <div class="ba-form-group">
          <label class="ba-label" for="exit-q">Ticket number or plate number</label>

          <div class="ba-predict-wrap">
            <input class="ba-input ba-predict-ghost" id="exit-ghost" type="text" readonly tabindex="-1" aria-hidden="true" style="font-size:20px;font-weight:700;text-transform:uppercase;">
            <input class="ba-input" id="exit-q" name="q" type="text" value="{{ $q }}"
                   placeholder="PK-0016 or HJK 4471" style="font-size:20px;font-weight:700;text-transform:uppercase;"
                   autocomplete="off" autofocus maxlength="20"
                   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s-]/g, '').slice(0, 20);">
          </div>

          <!-- Active Customer Suggestions Chips -->
          <div id="prediction-box" style="display:none;margin-top:10px;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12px;color:var(--muted);">
              <span>Currently parked matches:</span>
              <div id="prediction-chips" style="display:inline-flex;gap:6px;flex-wrap:wrap;"></div>
            </div>
          </div>

          <p style="font-size:12px;color:var(--muted);margin-top:6px;">Type plate letters/numbers or PK to see live predictions from currently parked customers (max 20 characters).</p>
          <div class="ba-err-msg" id="exit-q-err" hidden style="margin-top:8px;"></div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;">
          <button class="btn primary" type="submit" id="btn-exit-search">Search</button>
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
    <p>No active session matches <b>{{ $q }}</b>.<br><br>Check the digits with the driver, or check <a href="{{ route('attendant.records.index', ['q' => $q]) }}" style="color:var(--paint);font-weight:600;">Parking records</a> if the vehicle has already completed their stay and exited.</p>
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
  const exitGhost = document.getElementById('exit-ghost');
  const predictionBox = document.getElementById('prediction-box');
  const predictionChips = document.getElementById('prediction-chips');

  if (!exitInput || !exitGhost) return;

  const activeTargets = @json($activeTargets ?? []);

  let currentPrediction = null;
  let isDeleting = false;

  function updatePredictions() {
    const rawVal = exitInput.value.toUpperCase();
    const cleanVal = rawVal.replace(/[\s\-]/g, '');

    if (!cleanVal || cleanVal.length < 1) {
      exitGhost.value = '';
      currentPrediction = null;
      if (predictionBox) predictionBox.style.display = 'none';
      return;
    }

    // Filter active targets matching plate or ticket
    const matches = activeTargets.filter(t => {
      const pClean = (t.raw_plate || '').replace(/[\s\-]/g, '').toUpperCase();
      const pFmt = (t.plate || '').toUpperCase();
      const tClean = (t.ticket || '').replace(/[\s\-]/g, '').toUpperCase();
      const tFmt = (t.ticket || '').toUpperCase();

      return pClean.startsWith(cleanVal) ||
             pFmt.startsWith(rawVal) ||
             pClean.includes(cleanVal) ||
             tClean.startsWith(cleanVal) ||
             tFmt.startsWith(rawVal);
    });

    // Prioritize prefix matches
    matches.sort((a, b) => {
      const aPClean = (a.raw_plate || '').replace(/[\s\-]/g, '').toUpperCase();
      const bPClean = (b.raw_plate || '').replace(/[\s\-]/g, '').toUpperCase();
      const aTClean = (a.ticket || '').replace(/[\s\-]/g, '').toUpperCase();
      const bTClean = (b.ticket || '').replace(/[\s\-]/g, '').toUpperCase();

      const aStarts = aPClean.startsWith(cleanVal) || aTClean.startsWith(cleanVal);
      const bStarts = bPClean.startsWith(cleanVal) || bTClean.startsWith(cleanVal);

      if (aStarts && !bStarts) return -1;
      if (!aStarts && bStarts) return 1;
      return 0;
    });

    if (matches.length === 0) {
      exitGhost.value = '';
      currentPrediction = null;
      if (predictionBox) predictionBox.style.display = 'none';
      return;
    }

    const top = matches[0];
    const isTicket = rawVal.startsWith('P') || rawVal.startsWith('PK');
    const targetString = isTicket ? top.ticket : top.plate;
    currentPrediction = targetString;

    // Show ghost text overlay
    let ghostText = '';
    if (targetString.toUpperCase().startsWith(rawVal)) {
      ghostText = rawVal + targetString.slice(rawVal.length);
    } else {
      const tClean = targetString.replace(/[\s\-]/g, '').toUpperCase();
      if (tClean.startsWith(cleanVal)) {
        ghostText = targetString;
      }
    }
    exitGhost.value = ghostText;

    if (predictionBox && predictionChips) {
      predictionChips.innerHTML = '';
      matches.slice(0, 5).forEach((m, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ba-predict-chip' + (idx === 0 ? ' active' : '');
        btn.innerHTML = `${m.plate} <small>(${m.ticket} • ${m.slot})</small>`;
        btn.addEventListener('click', () => {
          applyPrediction(isTicket ? m.ticket : m.plate);
        });
        predictionChips.appendChild(btn);
      });
      predictionBox.style.display = 'block';
    }
  }

  function applyPrediction(val) {
    if (!val) return;
    exitInput.value = val;
    exitGhost.value = '';
    currentPrediction = null;
    if (predictionBox) predictionBox.style.display = 'none';
    exitInput.focus();
  }

  exitInput.addEventListener('keydown', (e) => {
    isDeleting = (e.key === 'Backspace' || e.key === 'Delete');

    // Tab or Right-Arrow (at the end of text) accepts the prediction
    if (e.key === 'Tab' || (e.key === 'ArrowRight' && exitInput.selectionStart === exitInput.value.length)) {
      if (currentPrediction && exitInput.value.toUpperCase() !== currentPrediction.toUpperCase()) {
        e.preventDefault();
        applyPrediction(currentPrediction);
      }
    } else if (e.key === 'Escape') {
      exitGhost.value = '';
      currentPrediction = null;
      if (predictionBox) predictionBox.style.display = 'none';
    }
  });

  exitInput.addEventListener('input', () => {
    let val = exitInput.value.toUpperCase();

    // Auto-dash for PK
    if (!isDeleting) {
      if (/^PK-([A-Z])/i.test(val)) {
        val = 'PK' + val.slice(3);
        exitInput.value = val;
      } else if (/^PK[0-9]/i.test(val)) {
        val = 'PK-' + val.slice(2);
        exitInput.value = val;
      } else if (val === 'PK') {
        val = 'PK-';
        exitInput.value = val;
      }
    } else if (val === 'PK-') {
      val = 'P';
      exitInput.value = val;
    }

    const errEl = document.getElementById('exit-q-err');
    if (errEl) errEl.hidden = true;
    exitInput.style.borderColor = '';

    updatePredictions();
  });

  const searchForm = document.getElementById('exit-search-form');
  const errEl = document.getElementById('exit-q-err');
  if (searchForm) {
    searchForm.addEventListener('submit', (e) => {
      const val = exitInput.value.trim().toUpperCase();
      if (!val) {
        e.preventDefault();
        if (errEl) {
          errEl.textContent = 'Please enter a ticket number (e.g. PK-0016) or a plate number.';
          errEl.hidden = false;
        }
        exitInput.style.borderColor = 'var(--stop)';
        exitInput.focus();
        return;
      }

      if (val.length < 1) {
        e.preventDefault();
        if (errEl) {
          errEl.textContent = 'Search query is too short.';
          errEl.hidden = false;
        }
        exitInput.style.borderColor = 'var(--stop)';
        exitInput.focus();
        return;
      }

      if (val.length > 20) {
        e.preventDefault();
        if (errEl) {
          errEl.textContent = 'Search query cannot exceed 20 characters.';
          errEl.hidden = false;
        }
        exitInput.style.borderColor = 'var(--stop)';
        exitInput.focus();
        return;
      }
    });
  }

  // Check on focus or if already has value
  exitInput.addEventListener('focus', updatePredictions);
  if (exitInput.value) {
    updatePredictions();
  }
});
</script>
@endpush
@endsection
