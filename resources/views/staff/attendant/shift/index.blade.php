@extends('layouts.attendant')

@section('title', 'Shift summary - Booth Attendant')
@section('tab', 'shift')

@section('content')
<h1 class="ba-page-title">Shift summary</h1>
<p class="ba-sub">Totals for your shift since {{ $shiftStart }}. Count the drawer at the end of the shift.</p>

<!-- Top Stat Tiles (BA-62) -->
<div class="ba-tiles" style="grid-template-columns:repeat(5,1fr);">
  <div class="ba-tile">
    <b>{{ $shiftStart }}</b>
    <span>Shift start</span>
  </div>
  <div class="ba-tile">
    <b>{{ $transactionsCount }}</b>
    <span>Transactions</span>
  </div>
  <div class="ba-tile">
    <b>{{ \App\Support\Lot::peso($cashCollected) }}</b>
    <span>Cash collected</span>
  </div>
  <div class="ba-tile">
    <b>{{ \App\Support\Lot::peso($discountsGiven) }}</b>
    <span>Discounts given</span>
  </div>
  <div class="ba-tile">
    <b>{{ \App\Support\Lot::peso($penaltiesCharged) }}</b>
    <span>Penalties charged</span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Transactions & Discounts Breakdown (BA-62) -->
  <div>
    <!-- Latest Transactions Table -->
    <div class="ba-card" style="margin-bottom:20px;">
      <h2>Latest transactions</h2>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
          <thead>
            <tr style="border-bottom:2px solid var(--line);color:var(--muted);font-size:12px;text-transform:uppercase;">
              <th style="padding:8px;">Receipt</th>
              <th style="padding:8px;">Ticket</th>
              <th style="padding:8px;">Plate</th>
              <th style="padding:8px;">Paid at</th>
              <th style="padding:8px;text-align:right;">Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($latestPayments as $p)
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:10px 8px;font-weight:700;"><a href="{{ route('attendant.exit.receipt', $p) }}" style="color:inherit;">{{ $p->receipt_number }}</a></td>
                <td style="padding:10px 8px;">{{ $p->session?->ticket_number ?? '-' }}</td>
                <td style="padding:10px 8px;">{{ \App\Support\Lot::plate($p->session?->plate_number) }}</td>
                <td style="padding:10px 8px;">{{ \App\Support\Lot::time($p->paid_at) }}</td>
                <td style="padding:10px 8px;text-align:right;font-weight:700;">{{ \App\Support\Lot::peso($p->total) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" style="padding:16px;text-align:center;color:var(--muted);">No transactions in this shift yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Discounts and Penalties breakdown -->
    <div class="ba-card">
      <h2>Discounts and penalties</h2>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;">
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span>Senior 20%</span>
          <b>{{ $seniorCount }} {{ \Illuminate\Support\Str::plural('discount', $seniorCount) }}, {{ \App\Support\Lot::peso($seniorTotal) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span>PWD 20%</span>
          <b>{{ $pwdCount }} {{ \Illuminate\Support\Str::plural('discount', $pwdCount) }}, {{ \App\Support\Lot::peso($pwdTotal) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span>Lost ticket</span>
          <b>{{ $lostTicketCount }} {{ \Illuminate\Support\Str::plural('penalty', $lostTicketCount) }}, {{ \App\Support\Lot::peso($lostTicketTotal) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span>Overstay</span>
          <b>{{ $overstayCount }} {{ \Illuminate\Support\Str::plural('penalty', $overstayCount) }}, {{ \App\Support\Lot::peso($overstayTotal) }}</b>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Cash Reconciliation & Closed State (BA-62, BA-64) -->
  <div>
    @if ($closedSummary)
      <!-- Screen BA-64: Shift Closed State -->
      <div class="ba-card" style="border-top:4px solid var(--ok);margin-bottom:20px;">
        <h2 style="color:var(--ok);">Shift closed at {{ $closedSummary['closed_at'] }}</h2>
        <p style="color:var(--muted);font-size:13px;line-height:1.4;">
          The report went to the Parking manager. New entries and payments stay off until the next attendant starts a shift.
        </p>

        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;font-size:14px;margin-bottom:18px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Opening float</span><b>{{ \App\Support\Lot::peso($closedSummary['opening_float']) }}</b></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Cash collected</span><b>{{ \App\Support\Lot::peso($closedSummary['cash_collected']) }}</b></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Expected in drawer</span><b>{{ \App\Support\Lot::peso($closedSummary['expected']) }}</b></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Counted</span><b>{{ \App\Support\Lot::peso($closedSummary['counted']) }}</b></div>
          <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:6px;margin-top:6px;">
            <span style="color:var(--muted);">Difference</span>
            <b style="color:{{ $closedSummary['difference'] == 0 ? 'var(--ok)' : 'var(--stop)' }};">
              {{ $closedSummary['difference'] == 0 ? 'Exact' : ($closedSummary['difference'] < 0 ? 'Short ' : 'Over ') . \App\Support\Lot::peso(abs($closedSummary['difference'])) }}
            </b>
          </div>
          @if ($closedSummary['note'])
            <div style="margin-top:8px;font-size:12px;color:var(--muted);font-style:italic;">
              Note: {{ $closedSummary['note'] }}
            </div>
          @endif
        </div>

        <div style="display:flex;gap:10px;">
          <button class="btn ghost sm" type="button" onclick="window.baPrint('#shift-report-slip')">Print shift report</button>
          <form method="POST" action="{{ route('staff.logout') }}">
            @csrf
            <button class="btn dark sm" type="submit">Log out</button>
          </form>
        </div>

        <!-- 58mm Thermal Shift Report Slip -->
        <div class="ba-thermal" id="shift-report-slip" style="display:none;">
          <h4>Bayline</h4>
          <p class="subt">Shift report</p>
          <div class="t-div"></div>
          <div class="t-row"><span>Attendant</span><b>{{ auth()->user()->name }}</b></div>
          <div class="t-row"><span>Closed at</span><b>{{ $closedSummary['closed_at'] }}</b></div>
          <div class="t-div"></div>
          <div class="t-row"><span>Opening float</span><b>{{ \App\Support\Lot::peso($closedSummary['opening_float']) }}</b></div>
          <div class="t-row"><span>Cash collected</span><b>{{ \App\Support\Lot::peso($closedSummary['cash_collected']) }}</b></div>
          <div class="t-row"><span>Expected</span><b>{{ \App\Support\Lot::peso($closedSummary['expected']) }}</b></div>
          <div class="t-row"><span>Counted</span><b>{{ \App\Support\Lot::peso($closedSummary['counted']) }}</b></div>
          <div class="t-row"><span>Difference</span><b>{{ $closedSummary['difference'] == 0 ? 'Exact' : ($closedSummary['difference'] < 0 ? 'Short ' : 'Over ') . \App\Support\Lot::peso(abs($closedSummary['difference'])) }}</b></div>
          @if ($closedSummary['note'])
            <div class="t-div"></div>
            <div style="font-size:10px;margin-top:2px;">Note: {{ $closedSummary['note'] }}</div>
          @endif
          <div class="t-div"></div>
          <p class="foot">End of shift report.<br>Submitted to Parking Manager.</p>
        </div>
      </div>
    @else
      <!-- Screen BA-62: Cash Reconciliation Box -->
      <div class="ba-card" style="margin-bottom:20px;">
        <h2>Cash reconciliation</h2>
        
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
            <span style="color:var(--muted);">Opening float</span>
            <b>{{ \App\Support\Lot::peso($openingFloat) }}</b>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
            <span style="color:var(--muted);">Cash collected</span>
            <b>{{ \App\Support\Lot::peso($cashCollected) }}</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding-top:4px;">
            <b style="font-size:16px;">Expected in drawer</b>
            <b style="font-size:22px;font-family:var(--f-d);">{{ \App\Support\Lot::peso($expectedInDrawer) }}</b>
          </div>
        </div>

        <button class="btn primary" type="button" data-open="#close-shift-dlg" style="width:100%;">Close shift</button>
      </div>
    @endif

    <!-- Entries Handled Card (BA-62) -->
    <div class="ba-card">
      <h2>Entries handled</h2>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;">
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Walk-in</span>
          <b>{{ $walkInsCount }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Reserved check-ins</span>
          <b>{{ $reservedCheckInsCount }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--muted);">Exits paid</span>
          <b>{{ $exitsPaidCount }}</b>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- POP-UP BA-63: Close Shift Modal -->
<dialog class="badlg" id="close-shift-dlg">
  <form class="in" method="POST" action="{{ route('attendant.shift.close') }}">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Close shift</h2>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;font-size:14px;margin-bottom:18px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Opening float</span><b>{{ \App\Support\Lot::peso($openingFloat) }}</b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Cash collected</span><b>{{ \App\Support\Lot::peso($cashCollected) }}</b></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:6px;margin-top:6px;">
        <b style="font-size:15px;">Expected in drawer</b>
        <b style="font-size:18px;font-family:var(--f-d);">{{ \App\Support\Lot::peso($expectedInDrawer) }}</b>
      </div>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="cash-counted-input">Cash counted</label>
      <input class="ba-input" id="cash-counted-input" name="cash_counted" type="number" step="any"
             value="{{ $expectedInDrawer }}" style="font-size:22px;font-weight:800;" required>
    </div>

    <div id="diff-warning-box" style="display:none;background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:12px;margin-bottom:16px;color:#991B1B;font-size:13px;">
      <b style="display:block;margin-bottom:2px;" id="diff-title">Difference: short ₱0.00</b>
      The count differs from expected. Add a note for the manager before closing.
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="shift-note-input">Note for the manager</label>
      <textarea class="ba-input" id="shift-note-input" name="note" rows="2" placeholder="Count notes, reason for discrepancy, etc."></textarea>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="submit" id="btn-submit-close-shift">Close shift</button>
    </div>
  </form>
</dialog>

@push('scripts')
<script>
const expectedCash = {{ $expectedInDrawer }};
const countedInput = document.getElementById('cash-counted-input');
const diffBox = document.getElementById('diff-warning-box');
const diffTitle = document.getElementById('diff-title');

if (countedInput) {
  countedInput.addEventListener('input', (e) => {
    const val = parseFloat(e.target.value || '0');
    const diff = Math.round((val - expectedCash) * 100) / 100;

    if (diff !== 0) {
      diffBox.style.display = 'block';
      const word = diff < 0 ? 'short' : 'over';
      diffTitle.textContent = `Difference: ${word} \u20B1${Math.abs(diff).toFixed(2)}`;
    } else {
      diffBox.style.display = 'none';
    }
  });
}
</script>
@endpush
@endsection
