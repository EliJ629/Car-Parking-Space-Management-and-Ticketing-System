@extends('layouts.attendant')

@section('title', 'Shift sale summary - Booth Attendant')
@section('tab', 'shift')

@section('content')
<h1 class="ba-page-title">Shift sale summary</h1>
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

        <div class="ba-detail-box" style="background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:14px;font-size:14px;margin-bottom:18px;color:var(--ink);">
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

<!-- POP-UP: Note Required when Cash Count Differs -->
@if(session('error_modal') && session('error_modal')['type'] === 'close_shift_note_required')
<dialog class="badlg auto-open" id="close-note-required-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Note required for cash difference</h2>
    <div class="ba-detail-box" style="background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:14px;font-size:14px;margin-bottom:16px;color:var(--ink);">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Expected in drawer</span><b>{{ \App\Support\Lot::peso(session('error_modal')['expected']) }}</b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Cash counted</span><b>{{ \App\Support\Lot::peso(session('error_modal')['counted']) }}</b></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:6px;margin-top:6px;color:var(--stop);">
        <b>Difference</b>
        <b>{{ session('error_modal')['difference'] < 0 ? 'Short' : 'Over' }} {{ \App\Support\Lot::peso(abs(session('error_modal')['difference'])) }}</b>
      </div>
    </div>
    <p>Because the cash counted differs from expected, an explanation note is required for the manager before closing.</p>
    <div class="actions">
      <button class="btn primary" type="button" data-close data-open="#close-shift-dlg">Return and add note</button>
    </div>
  </div>
</dialog>
@endif

<!-- POP-UP BA-63: Close Shift Modal -->
<dialog class="badlg" id="close-shift-dlg">
  <form class="in" id="close-shift-form" method="POST" action="{{ route('attendant.shift.close') }}">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Close shift</h2>

    <div class="ba-detail-box" style="margin-bottom:18px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Opening float</span><b>{{ \App\Support\Lot::peso($openingFloat) }}</b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Cash collected</span><b>{{ \App\Support\Lot::peso($cashCollected) }}</b></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:6px;margin-top:6px;">
        <b style="font-size:15px;">Expected in drawer</b>
        <b style="font-size:18px;font-family:var(--f-d);color:var(--paint);">{{ \App\Support\Lot::peso($expectedInDrawer) }}</b>
      </div>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="cash-counted-input">Cash counted</label>
      <div style="position:relative;">
        <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:22px;font-weight:800;color:var(--muted);pointer-events:none;">₱</span>
        <input class="ba-input" id="cash-counted-input" type="text" inputmode="decimal" maxlength="12"
               value="{{ number_format($expectedInDrawer, 2) }}" style="font-size:24px;font-weight:800;padding-left:36px;letter-spacing:0.5px;" required autocomplete="off">
      </div>
      <input type="hidden" name="cash_counted" id="hidden-cash-counted" value="{{ $expectedInDrawer }}">
      <input type="hidden" name="manager_id" id="hidden-close-mgr-id" value="">
      <input type="hidden" name="manager_pin" id="hidden-close-mgr-pin" value="">
      <input type="hidden" name="manager_password" id="hidden-close-mgr-password" value="">
      <p style="font-size:12px;color:var(--muted);margin-top:4px;">Enter the counted bills and coins in the drawer.</p>
    </div>

    <div id="diff-warning-box" style="display:none;background:var(--stop-bg);border:1px solid var(--stop);border-radius:6px;padding:12px;margin-bottom:16px;color:var(--stop);font-size:13px;word-break:break-word;overflow-wrap:anywhere;">
      <b style="display:block;margin-bottom:2px;word-break:break-word;overflow-wrap:anywhere;" id="diff-title">Difference: short ₱0.00</b>
      <span id="diff-desc">The count differs from expected. Add a note for the manager before closing.</span>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="shift-note-input">Note for the manager</label>
      <textarea class="ba-input" id="shift-note-input" name="note" rows="2" placeholder="Count notes, reason for discrepancy, etc." oninput="this.value = this.value.replace(/[<>{}\`\\]/g, '')"></textarea>
      <div class="ba-err-msg" id="shift-note-err" hidden style="margin-top:6px;font-size:13px;color:var(--stop);font-weight:600;"></div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="submit" id="btn-submit-close-shift">Close shift</button>
    </div>
  </form>
</dialog>

<!-- POP-UP: Cash Above Expected (Count Again) -->
<dialog class="badlg" id="overage-count-again-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--paint);">Cash above expected</h2>

    <div class="ba-detail-box" style="background:var(--warn-bg);border:1px solid var(--paint);margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Expected in drawer</span><b>{{ \App\Support\Lot::peso($expectedInDrawer) }}</b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Cash counted</span><b id="overage-counted-lbl">₱0.00</b></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:6px;margin-top:6px;color:var(--paint);">
        <b>Difference (Overage)</b>
        <b id="overage-diff-lbl">+₱0.00 Over</b>
      </div>
    </div>

    <p style="font-size:14px;color:var(--muted);margin-bottom:18px;line-height:1.4;">
      The cash counted is more than expected in the drawer. Please recount the drawer to ensure there are no counting mistakes before closing.
    </p>

    <div class="actions">
      <button class="btn primary" type="button" data-close id="btn-recount-overage">Count again</button>
      <button class="btn ghost" type="button" id="btn-proceed-overage">Proceed with overage</button>
    </div>
  </div>
</dialog>

<!-- POP-UP: Manager Approval Required for Cash Shortage -->
<dialog class="badlg" id="shortage-mgr-approval-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Manager approval required</h2>

    <div class="ba-detail-box" style="background:var(--stop-bg);border:1px solid var(--stop);margin-bottom:16px;color:var(--ink);">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Expected in drawer</span><b>{{ \App\Support\Lot::peso($expectedInDrawer) }}</b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--muted);">Cash counted</span><b id="shortage-counted-lbl">₱0.00</b></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--stop);padding-top:6px;margin-top:6px;color:var(--stop);">
        <b>Difference (Shortage)</b>
        <b id="shortage-diff-lbl">-₱0.00 Short</b>
      </div>
    </div>

    <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
      The drawer is short of cash. An explanation note and the Parking manager's password are required before this shift can be closed.
    </p>

    <div class="ba-form-group">
      <label class="ba-label" for="shift-approving-mgr">Approving manager</label>
      <select class="ba-input" id="shift-approving-mgr">
        @foreach ($managers as $m)
          <option value="{{ $m->id }}">{{ $m->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="shift-manager-password">Manager password</label>
      <input class="ba-input" id="shift-manager-password" type="password" placeholder="Enter manager password" autocomplete="current-password" style="font-size:16px;">
      <p style="font-size:12px;color:var(--muted);margin-top:4px;">Enter the manager's account password. The attendant does not see it.</p>
      <div class="ba-err-msg" id="shift-password-err" hidden></div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-mgr-approval">Authorize & close shift</button>
    </div>
  </div>
</dialog>

@push('scripts')
<script>
const expectedCash = {{ $expectedInDrawer }};
const verifyShiftPinUrl = "{{ route('attendant.shift.verify-pin') }}";
const shiftCsrf = "{{ csrf_token() }}";

const countedInput = document.getElementById('cash-counted-input');
const hiddenCounted = document.getElementById('hidden-cash-counted');
const diffBox = document.getElementById('diff-warning-box');
const diffTitle = document.getElementById('diff-title');
const diffDesc = document.getElementById('diff-desc');
const closeShiftForm = document.getElementById('close-shift-form');

let allowBypassChecks = false;

// Format numbers with commas, disallow letters and special characters, limit to 7 digits before decimal
function formatCashString(val) {
  let clean = String(val).replace(/[^0-9.]/g, '');
  const parts = clean.split('.');
  // Limit to max 7 digits (up to ₱9,999,999)
  let intPart = parts[0].slice(0, 7);
  if (intPart.length > 1 && intPart.startsWith('0')) {
    intPart = intPart.replace(/^0+/, '') || '0';
  }
  const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  if (parts.length > 1) {
    return formattedInt + '.' + parts.slice(1).join('').slice(0, 2);
  }
  return formattedInt;
}

function getRawAmount(val) {
  const clean = String(val).replace(/,/g, '').trim();
  const num = parseFloat(clean);
  return isNaN(num) ? 0 : num;
}

function updateDifferenceUI(raw) {
  const diff = Math.round((raw - expectedCash) * 100) / 100;
  if (diff !== 0) {
    diffBox.style.display = 'block';
    const word = diff < 0 ? 'short' : 'over';
    diffTitle.textContent = `Difference: ${word} \u20B1${Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    if (diff < 0) {
      diffBox.style.background = 'var(--stop-bg)';
      diffBox.style.borderColor = 'var(--stop)';
      diffTitle.style.color = 'var(--stop)';
      diffDesc.style.color = 'var(--ink)';
      diffDesc.textContent = 'Cash is short of expected. A note and manager password approval will be required to close.';
    } else {
      diffBox.style.background = 'var(--warn-bg)';
      diffBox.style.borderColor = 'var(--paint)';
      diffTitle.style.color = 'var(--paint)';
      diffDesc.style.color = 'var(--ink)';
      diffDesc.textContent = 'Cash is above expected. You will be prompted to recount the drawer before proceeding.';
    }
  } else {
    diffBox.style.display = 'none';
  }
}

if (countedInput) {
  let selectOnMouseUp = false;

  countedInput.addEventListener('focus', () => {
    selectOnMouseUp = true;
    countedInput.select();
  });

  countedInput.addEventListener('mouseup', (e) => {
    if (selectOnMouseUp) {
      selectOnMouseUp = false;
      countedInput.select();
      e.preventDefault();
    }
  });

  // Strict keydown guard: blocks invalid characters, enforces 7-digit cap, fixes backspace across commas
  countedInput.addEventListener('keydown', (e) => {
    // Allow navigation & editing shortcuts
    if (
      ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab', 'Enter'].includes(e.key) ||
      e.ctrlKey || e.metaKey || e.altKey
    ) {
      // Smart backspace: if cursor is collapsed directly after a comma, skip the comma so the preceding digit is deleted
      if (e.key === 'Backspace' && countedInput.selectionStart === countedInput.selectionEnd) {
        const pos = countedInput.selectionStart;
        if (pos > 0 && countedInput.value[pos - 1] === ',') {
          countedInput.setSelectionRange(pos - 1, pos - 1);
        }
      }
      return;
    }

    // Block non-numeric characters (only digits and decimal point allowed)
    if (!/^[0-9.]$/.test(e.key)) {
      e.preventDefault();
      return;
    }

    const val = countedInput.value;
    const selStart = countedInput.selectionStart ?? val.length;
    const selEnd = countedInput.selectionEnd ?? val.length;
    const dotIdx = val.indexOf('.');

    // Block duplicate decimal points
    if (e.key === '.') {
      if (dotIdx !== -1) {
        const hasSelectedDot = val.slice(selStart, selEnd).includes('.');
        if (!hasSelectedDot) {
          e.preventDefault();
          return;
        }
      }
      return;
    }

    // Enforce 7-digit integer cap
    const isTypingInteger = dotIdx === -1 || selStart <= dotIdx;
    if (isTypingInteger) {
      const intDigits = (dotIdx === -1 ? val : val.slice(0, dotIdx)).replace(/[^0-9]/g, '');
      const selectedIntDigits = val.slice(selStart, Math.min(selEnd, dotIdx === -1 ? val.length : dotIdx)).replace(/[^0-9]/g, '');
      if (intDigits.length - selectedIntDigits.length >= 7) {
        e.preventDefault();
        return;
      }
    }

    // Enforce 2 decimal places cap
    if (dotIdx !== -1 && selStart > dotIdx) {
      const decDigits = val.slice(dotIdx + 1).replace(/[^0-9]/g, '');
      const selectedDecDigits = val.slice(Math.max(selStart, dotIdx + 1), selEnd).replace(/[^0-9]/g, '');
      if (decDigits.length - selectedDecDigits.length >= 2) {
        e.preventDefault();
        return;
      }
    }
  });

  // Handle paste: sanitize and cap to 7 integer digits and 2 decimals
  countedInput.addEventListener('paste', (e) => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
    const clean = pasted.replace(/[^0-9.]/g, '');
    const parts = clean.split('.');
    const intPart = parts[0].slice(0, 7);
    const decPart = parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, 2) : '';
    const formatted = formatCashString(intPart + decPart);
    countedInput.value = formatted;
    countedInput.setSelectionRange(formatted.length, formatted.length);
    const raw = getRawAmount(formatted);
    hiddenCounted.value = raw.toFixed(2);
    updateDifferenceUI(raw);
  });

  // Input event: format with commas while keeping cursor in natural position
  countedInput.addEventListener('input', (e) => {
    const input = e.target;
    const originalVal = input.value;
    const cursorPos = input.selectionStart ?? originalVal.length;

    // Count how many valid digits/dots were before the cursor
    const digitsBefore = originalVal.slice(0, cursorPos).replace(/[^0-9.]/g, '').length;

    const formatted = formatCashString(originalVal);
    input.value = formatted;

    // Restore cursor position by matching digitsBefore in the new formatted string
    let newCursor = 0;
    let digitsCounted = 0;
    while (newCursor < formatted.length && digitsCounted < digitsBefore) {
      if (/[0-9.]/.test(formatted[newCursor])) {
        digitsCounted++;
      }
      newCursor++;
    }
    input.setSelectionRange(newCursor, newCursor);

    const raw = getRawAmount(formatted);
    hiddenCounted.value = raw.toFixed(2);
    updateDifferenceUI(raw);
  });

  countedInput.addEventListener('blur', () => {
    const raw = getRawAmount(countedInput.value);
    if (raw > 0) {
      countedInput.value = raw.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } else {
      countedInput.value = '0.00';
    }
    hiddenCounted.value = raw.toFixed(2);
    updateDifferenceUI(raw);
  });
}

document.querySelector('[data-open="#close-shift-dlg"]')?.addEventListener('click', () => {
  setTimeout(() => {
    if (countedInput) {
      countedInput.focus();
      countedInput.select();
    }
  }, 100);
});

const noteInput = document.getElementById('shift-note-input');
const noteErr = document.getElementById('shift-note-err');

function showNoteError(msg) {
  if (noteErr) {
    noteErr.textContent = msg;
    noteErr.hidden = false;
  }
  if (noteInput) {
    noteInput.style.borderColor = 'var(--stop)';
    noteInput.style.backgroundColor = '';
    noteInput.focus();
  }
}

function clearNoteError() {
  if (noteErr) {
    noteErr.textContent = '';
    noteErr.hidden = true;
  }
  if (noteInput) {
    noteInput.style.borderColor = '';
    noteInput.style.backgroundColor = '';
  }
}

if (noteInput) {
  noteInput.addEventListener('input', () => {
    if (noteInput.value.trim() !== '') {
      clearNoteError();
    }
  });
}

// Close Shift submit flow
if (closeShiftForm) {
  closeShiftForm.addEventListener('submit', (e) => {
    if (allowBypassChecks) return;

    e.preventDefault();

    const val = getRawAmount(countedInput.value);
    const diff = Math.round((val - expectedCash) * 100) / 100;
    const note = (noteInput ? noteInput.value : '').trim();

    // 1. Exact match -> proceed directly
    if (diff === 0) {
      clearNoteError();
      allowBypassChecks = true;
      closeShiftForm.submit();
      return;
    }

    // 2. Overage (Cash > Expected) -> Prompt Count Again modal
    if (diff > 0) {
      document.getElementById('overage-counted-lbl').textContent = `\u20B1${val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
      document.getElementById('overage-diff-lbl').textContent = `+\u20B1${diff.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} Over`;
      document.getElementById('overage-count-again-dlg').showModal();
      return;
    }

    // 3. Shortage (Cash < Expected) -> Needs note & Manager approval
    if (diff < 0) {
      if (!note) {
        showNoteError('Please add a note explaining why the drawer is short before requesting manager approval.');
        return;
      }
      clearNoteError();

      document.getElementById('shortage-counted-lbl').textContent = `\u20B1${val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
      document.getElementById('shortage-diff-lbl').textContent = `-\u20B1${Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} Short`;
      document.getElementById('shift-password-err').hidden = true;
      document.getElementById('shift-manager-password').value = '';
      document.getElementById('shortage-mgr-approval-dlg').showModal();
      document.getElementById('shift-manager-password').focus();
    }
  });
}

// Count again in overage modal
document.getElementById('btn-recount-overage')?.addEventListener('click', () => {
  document.getElementById('overage-count-again-dlg').close();
  countedInput.focus();
  countedInput.select();
});

// Proceed with overage button
document.getElementById('btn-proceed-overage')?.addEventListener('click', () => {
  const note = (noteInput ? noteInput.value : '').trim();
  if (!note) {
    document.getElementById('overage-count-again-dlg').close();
    showNoteError('Please add a note explaining the overage for the manager before closing.');
    return;
  }
  clearNoteError();
  document.getElementById('overage-count-again-dlg').close();
  allowBypassChecks = true;
  closeShiftForm.submit();
});

// Submit manager approval for shortage
document.getElementById('btn-submit-mgr-approval')?.addEventListener('click', async () => {
  const mgrId = document.getElementById('shift-approving-mgr').value;
  const passInput = document.getElementById('shift-manager-password');
  const password = passInput.value;
  const errEl = document.getElementById('shift-password-err');
  errEl.hidden = true;

  if (!password) {
    errEl.textContent = 'Please enter the manager password.';
    errEl.hidden = false;
    passInput.focus();
    return;
  }

  try {
    const res = await fetch(verifyShiftPinUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': shiftCsrf,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ manager_id: mgrId, password: password, pin: password })
    });

    const data = await res.json();
    if (!data.ok) {
      errEl.textContent = data.message || 'Incorrect manager password.';
      errEl.hidden = false;
      passInput.focus();
      passInput.select();
      return;
    }

    // Approved! Populate hidden fields and submit close shift form
    document.getElementById('hidden-close-mgr-id').value = mgrId;
    document.getElementById('hidden-close-mgr-password').value = password;
    document.getElementById('hidden-close-mgr-pin').value = password;
    document.getElementById('shortage-mgr-approval-dlg').close();

    allowBypassChecks = true;
    closeShiftForm.submit();
  } catch (err) {
    console.error(err);
    errEl.textContent = 'Could not verify manager password. Please try again.';
    errEl.hidden = false;
  }
});
</script>
@endpush
@endsection
