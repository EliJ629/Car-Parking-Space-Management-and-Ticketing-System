@extends('layouts.attendant')

@section('title', 'Session details and fee - Booth Attendant')
@section('tab', 'exit')

@section('content')
<h1 class="ba-page-title">Exit and payment</h1>
<p class="ba-sub">Check the session, then take cash.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Search</span>
  <span class="ba-step active">2. Fee</span>
  <span class="ba-step">3. Payment</span>
  <span class="ba-step">4. Receipt</span>
  <span class="ba-step">5. Guard approval</span>
</div>

@if ($errors->any())
  <div style="background:var(--stop-bg);border-left:5px solid var(--stop);border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px;color:var(--stop);">
    <b style="font-size:15px;display:block;margin-bottom:4px;">Cannot process payment &mdash; please check details:</b>
    <ul style="margin:0;padding-left:18px;font-size:13px;">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div style="display:grid;grid-template-columns:1.2fr 1.4fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Parking Session Details Card (BA-37, BA-43) -->
  <div class="ba-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <h2 style="margin:0;">Parking session</h2>
      <span class="badge unpaid">UNPAID</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Ticket</span>
        <b>{{ $session->ticket_number }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Plate</span>
        <b>{{ \App\Support\Lot::plate($session->plate_number) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Vehicle</span>
        <b>{{ $session->vehicle_color }} {{ $session->vehicle_type }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Slot</span>
        <b>{{ $session->slot_code }}, {{ $session->slot?->zone }}, {{ $session->slot?->type }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Entry</span>
        <b>{{ \App\Support\Lot::stamp($session->entered_at) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Exit time</span>
        <b>{{ \App\Support\Lot::stamp(now()) }} (now)</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Duration</span>
        <b>{{ \App\Support\Lot::duration($estimate['minutes']) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Entry type</span>
        <b>{{ $isReserved ? 'Reserved, '.$session->reservation->code : 'Walk-in' }}</b>
      </div>
      @if ($isReserved)
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Driver</span>
          <b>{{ $session->driver?->name ?? 'Reserved Driver' }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--muted);">Checked in</span>
          <b>{{ \App\Support\Lot::time($session->reservation->checked_in_at ?? $session->entered_at) }}</b>
        </div>
      @endif
    </div>
  </div>

  <!-- Right Column: Fee Breakdown & Discount Card (BA-37, BA-38) -->
  <div>
    <div class="ba-fee-card" style="margin-bottom:20px;">
      <h2>Fee breakdown</h2>

      <div class="ba-fee-row">
        <span>Base fee <small style="color:var(--muted);display:block;">First hour</small></span>
        <b>{{ \App\Support\Lot::peso($estimate['first_hour']) }}</b>
      </div>

      <div class="ba-fee-row">
        <span>Additional hours <small style="color:var(--muted);display:block;">{{ $estimate['additional_hours'] }} started {{ \Illuminate\Support\Str::plural('hour', $estimate['additional_hours']) }} x ₱{{ number_format($estimate['additional_unit'], 2) }}</small></span>
        <b>{{ \App\Support\Lot::peso($estimate['additional']) }}</b>
      </div>

      @if ($estimate['capped'])
        <div class="ba-fee-row">
          <span>Daily maximum adjustment <small style="color:var(--muted);display:block;">Capped by daily max rate (₱{{ number_format($estimate['daily_maximum'], 2) }}/day policy)</small></span>
          <b style="color:var(--muted);">-{{ \App\Support\Lot::peso(abs($estimate['cap_adjustment'])) }}</b>
        </div>
      @endif

      <div class="ba-fee-row" id="row-penalties">
        <span>Penalties <small id="lbl-penalty-desc" style="color:var(--muted);display:block;">None</small></span>
        <b id="lbl-penalty-amt">₱0.00</b>
      </div>

      <div class="ba-fee-row" id="row-discount" style="color:var(--ok);">
        <span>Discounts <small id="lbl-discount-desc" style="display:block;">None applied</small></span>
        <b id="lbl-discount-amt">₱0.00</b>
      </div>

      @if ($isReserved)
        <!-- BA-43: Reserved driver remaining balance lines -->
        <div class="ba-fee-row" style="border-top:1px dashed var(--line);margin-top:8px;">
          <span>Total parking fee</span>
          <b>{{ \App\Support\Lot::peso($estimate['parking_charge']) }}</b>
        </div>
        <div class="ba-fee-row" style="color:var(--muted);">
          <span>Paid in advance <small style="display:block;">Reservation is free, nothing was paid online</small></span>
          <b>-₱0.00</b>
        </div>
        <div class="ba-fee-row total">
          <div>
            <b style="font-size:18px;">Remaining balance</b>
            <small style="color:var(--muted);display:block;font-size:12px;margin-top:2px;">The balance is the total fee minus anything already paid for this reservation.</small>
          </div>
          <b class="amt" id="final-amt-display">{{ \App\Support\Lot::peso($estimate['total']) }}</b>
        </div>
      @else
        <div class="ba-fee-row total">
          <div>
            <b style="font-size:18px;">Final amount</b>
            <small style="color:var(--muted);display:block;font-size:12px;margin-top:2px;">Rates and the daily maximum (₱300) come from Rates and policies.</small>
          </div>
          <b class="amt" id="final-amt-display">{{ \App\Support\Lot::peso($estimate['total']) }}</b>
        </div>
      @endif

      <div style="margin-top:16px;display:flex;gap:10px;align-items:center;" id="penalty-actions-wrap">
        <button class="btn ghost sm" type="button" data-open="#add-penalty-dlg" id="btn-open-penalty-dlg">{{ ($estimate['penalties'] ?? 0) > 0 ? 'Change penalty' : 'Add penalty' }}</button>
        <button class="btn ghost sm danger" type="button" id="btn-remove-penalty" style="{{ ($estimate['penalties'] ?? 0) > 0 ? '' : 'display:none;' }}">Remove penalty</button>
      </div>
    </div>

    <!-- Discount Section (BA-37 vs BA-38) -->
    <div class="ba-card" style="margin-bottom:20px;">
      <h2>Discount</h2>

      <!-- State 1: No discount (BA-37) -->
      <div id="disc-unapplied-box">
        <p style="color:var(--muted);font-size:14px;margin-bottom:16px;">
          No discount applied. Select a discount and provide the required proof.
        </p>
        <button class="btn ghost sm" type="button" data-open="#apply-discount-dlg">Apply discount</button>
      </div>

      <!-- State 2: Discount Applied (BA-38) -->
      <div id="disc-applied-box" style="display:none;">
        <div style="display:flex;flex-direction:column;gap:8px;font-size:14px;margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Type</span><b id="app-disc-type">-</b></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">ID number</span><b id="app-disc-id">-</b></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Note</span><span id="app-disc-note">ID checked at the booth</span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Applied by</span><b>{{ auth()->user()->name }}</b></div>
        </div>
        <div style="display:flex;gap:10px;">
          <button class="btn ghost sm" type="button" data-open="#apply-discount-dlg">Change</button>
          <button class="btn ghost sm danger" type="button" id="btn-remove-discount">Remove</button>
        </div>
      </div>
    </div>

    <!-- Bottom Actions -->
    <div style="display:flex;gap:12px;justify-content:flex-end;">
      <a class="btn ghost" href="{{ route('attendant.exit.index') }}">Back to search</a>
      <button class="btn primary" type="button" id="btn-open-pay-modal" data-open="#cash-payment-dlg" style="min-height:46px;font-size:16px;">Take payment</button>
    </div>
  </div>
</div>

<!-- POP-UP BA-39: Apply Discount Modal -->
<dialog class="badlg" id="apply-discount-dlg">
  <div class="in">
    <h2>Apply discount</h2>

    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
      @foreach ($discounts as $d)
        <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
          <input type="radio" name="modal_discount_id" value="{{ $d->id }}" data-name="{{ $d->name }}" data-percent="{{ $d->percent }}" data-proof-label="{{ $d->proof_label ?? 'ID number' }}" style="width:18px;height:18px;" @checked($loop->first)>
          <div>
            <b>{{ $d->name }}, {{ $d->percent }}%</b>
            <small style="color:var(--muted);display:block;">
              Needs an ID number
            </small>
          </div>
        </label>
      @endforeach
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="disc-proof-input" id="disc-proof-label">ID number / Proof</label>
      <input class="ba-input" id="disc-proof-input" type="text" placeholder="e.g. SC-0048215" autocomplete="off"
             maxlength="30" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s\/-]/g, '').slice(0, 30)">
      <p id="disc-proof-hint" style="font-size:12px;color:var(--muted);margin-top:4px;">OSCA ID card number, Senior Citizen card, or PhilSys PCN</p>
      <div class="ba-err-msg" id="disc-proof-err" hidden style="margin-top:6px;font-size:13px;"></div>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="disc-note-input">Note (optional)</label>
      <input class="ba-input" id="disc-note-input" type="text" placeholder="Optional note (e.g. ID verified at booth)"
             maxlength="120" oninput="this.value = this.value.replace(/[<>{}\`\\]/g, '').slice(0, 120)">
    </div>

    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:12px;margin-bottom:18px;font-size:13px;color:#166534;">
      <b style="display:block;font-size:14px;">New total <span id="dlg-disc-preview-total">₱0.00</span></b>
      Discount of <span id="dlg-disc-amt">₱0.00</span> on <span id="dlg-disc-subtotal">₱{{ number_format($estimate['parking_charge'], 2) }}</span>. Rates are read from Rates and policies.
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-discount">Apply discount</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-42: Add Penalty Modal -->
<dialog class="badlg" id="add-penalty-dlg">
  <div class="in">
    <h2>Add penalty</h2>

    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
        <input type="radio" name="penalty_choice" value="lost_ticket" data-amt="{{ (float) ($currentRate?->lost_ticket_penalty ?? 100) }}" style="width:18px;height:18px;">
        <div><b>Lost ticket ₱{{ number_format((float) ($currentRate?->lost_ticket_penalty ?? 100), 2) }}</b></div>
      </label>
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
        <input type="radio" name="penalty_choice" value="overstay" data-amt="{{ (float) ($currentRate?->overstay_penalty ?? 150) }}" style="width:18px;height:18px;">
        <div><b>Overstay ₱{{ number_format((float) ($currentRate?->overstay_penalty ?? 150), 2) }}</b></div>
      </label>
    </div>

    <div class="ba-err-msg" id="penalty-choice-err" hidden style="margin-bottom:14px;">Please select a penalty option.</div>

    <div class="ba-form-group">
      <label class="ba-label" for="penalty-note-input">Note (optional)</label>
      <input class="ba-input" id="penalty-note-input" type="text" placeholder="Driver could not find the ticket. ID shown." oninput="this.value = this.value.replace(/[<>{}\`\\]/g, '')">
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">You can remove or change the penalty before taking payment.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-penalty">Add penalty</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-44 & BA-45: Take Cash Payment Modal -->
<dialog class="badlg {{ $errors->has('cash_received') ? 'auto-open' : '' }}" id="cash-payment-dlg">
  <form class="in" method="POST" action="{{ route('attendant.exit.pay', $session) }}">
    @csrf
    <input type="hidden" name="discount_id" id="pay-hidden-disc-id" value="">
    <input type="hidden" name="discount_proof" id="pay-hidden-disc-proof" value="">
    <input type="hidden" name="discount_note" id="pay-hidden-disc-note" value="">
    <input type="hidden" name="lost_ticket" id="pay-hidden-lost" value="0">
    <input type="hidden" name="overstay" id="pay-hidden-overstay" value="0">

    <h2>Take cash payment</h2>
    
    <div style="font-size:14px;margin-bottom:14px;color:var(--muted);">
      Ticket <b>{{ $session->ticket_number }}</b>, {{ \App\Support\Lot::plate($session->plate_number) }}<br>
      Amount due: <b style="font-size:18px;color:var(--ink);" id="due-amount-val" data-due="{{ $estimate['total'] }}">{{ \App\Support\Lot::peso($estimate['total']) }}</b>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="cash-received-input">Cash received</label>
      <div style="position:relative;">
        <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:22px;font-weight:800;color:var(--muted);pointer-events:none;">₱</span>
        <input class="ba-input" id="cash-received-input" type="text" inputmode="decimal" maxlength="12"
               value="{{ old('cash_received') ? number_format((float) old('cash_received'), 2) : number_format($estimate['total'], 2) }}"
               style="font-size:24px;font-weight:800;padding-left:36px;letter-spacing:0.5px;" autofocus autocomplete="off">
      </div>
      <input type="hidden" name="cash_received" id="hidden-cash-received" value="{{ old('cash_received', $estimate['total']) }}">
      <div class="ba-err-msg" id="cash-error-msg" {{ $errors->has('cash_received') ? '' : 'hidden' }} style="margin-top:6px;font-size:13px;color:var(--stop);font-weight:600;">
        {{ $errors->first('cash_received') }}
      </div>
    </div>

    <!-- Quick Amount Buttons (BA-44) -->
    <div style="display:flex;gap:8px;margin-bottom:18px;">
      <button class="btn ghost sm" type="button" data-quick-cash="exact">Exact</button>
      <button class="btn ghost sm" type="button" data-quick-cash="100">₱100</button>
      <button class="btn ghost sm" type="button" data-quick-cash="200">₱200</button>
      <button class="btn ghost sm" type="button" data-quick-cash="500">₱500</button>
    </div>

    <div class="ba-detail-box" style="background:var(--ok-bg);border:1px solid var(--ok);margin-bottom:20px;font-size:14px;display:flex;justify-content:space-between;align-items:center;">
      <span style="color:var(--ink);">Change to give back:</span>
      <b id="change-val" style="font-size:20px;color:var(--ok);">₱0.00</b>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="submit" id="confirm-pay-btn">Confirm payment</button>
    </div>
  </form>
</dialog>

@push('scripts')
<script>
const baseCharge = {{ $estimate['parking_charge'] }};
let currentPenalty = {{ (float) ($estimate['penalties'] ?? 0.0) }};
let penaltyType = null;
let currentDiscount = null;

function recalculateFinal() {
  const subtotal = baseCharge + currentPenalty;
  let discAmt = 0;
  if (currentDiscount) {
    discAmt = Math.round((subtotal * currentDiscount.percent) / 100 * 100) / 100;
  }
  const total = Math.max(0, subtotal - discAmt);

  document.getElementById('lbl-discount-amt').textContent = discAmt > 0 ? `-\u20B1${discAmt.toFixed(2)}` : '\u20B10.00';
  document.getElementById('lbl-penalty-amt').textContent = currentPenalty > 0 ? `\u20B1${currentPenalty.toFixed(2)}` : '\u20B10.00';
  document.getElementById('final-amt-display').textContent = `\u20B1${total.toFixed(2)}`;

  if (currentDiscount) {
    document.getElementById('lbl-discount-desc').textContent = `${currentDiscount.name} ${currentDiscount.percent}% of \u20B1${subtotal.toFixed(2)}`;
  }

  // Update modal due
  const dueVal = document.getElementById('due-amount-val');
  if (dueVal) {
    dueVal.dataset.due = total.toFixed(2);
    dueVal.textContent = `\u20B1${total.toFixed(2)}`;
  }
  const cashIn = document.getElementById('cash-received-input');
  if (cashIn) {
    cashIn.value = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    cashIn.dispatchEvent(new Event('input'));
  }
}

// Sync cash modal when clicking Take payment
const openPayBtn = document.getElementById('btn-open-pay-modal');
if (openPayBtn) {
  openPayBtn.addEventListener('click', () => {
    const cashIn = document.getElementById('cash-received-input');
    const dueVal = document.getElementById('due-amount-val');
    if (cashIn && dueVal) {
      const rawDue = parseFloat(dueVal.dataset.due || '0');
      cashIn.value = rawDue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
      cashIn.dispatchEvent(new Event('input'));
      setTimeout(() => {
        cashIn.focus();
        cashIn.select();
      }, 80);
    }
  });
}

function updateDiscountModalPreview() {
  const selectedRadio = document.querySelector('input[name="modal_discount_id"]:checked');
  if (!selectedRadio) return;

  const pct = parseFloat(selectedRadio.dataset.percent) || 0;
  const subtotal = baseCharge + currentPenalty;
  const discAmt = Math.round((subtotal * pct) / 100 * 100) / 100;
  const newTotal = Math.max(0, subtotal - discAmt);

  const prevTotalEl = document.getElementById('dlg-disc-preview-total');
  const prevAmtEl = document.getElementById('dlg-disc-amt');
  const prevSubtotalEl = document.getElementById('dlg-disc-subtotal');
  if (prevTotalEl) prevTotalEl.textContent = `\u20B1${newTotal.toFixed(2)}`;
  if (prevAmtEl) prevAmtEl.textContent = `\u20B1${discAmt.toFixed(2)}`;
  if (prevSubtotalEl) prevSubtotalEl.textContent = `\u20B1${subtotal.toFixed(2)}`;

  const discName = (selectedRadio.dataset.name || '').toLowerCase();
  const proofLabelEl = document.getElementById('disc-proof-label');
  const proofInputEl = document.getElementById('disc-proof-input');
  const proofHintEl = document.getElementById('disc-proof-hint');
  const proofErrEl = document.getElementById('disc-proof-err');
  if (proofErrEl) proofErrEl.hidden = true;

  if (proofInputEl && proofHintEl) {
    if (discName.includes('senior')) {
      if (proofLabelEl) proofLabelEl.textContent = 'Senior Citizen (OSCA) ID';
      proofInputEl.placeholder = 'e.g. SC-0048215 or 12-3456-789';
      proofHintEl.textContent = 'OSCA ID card number, Senior Citizen card, or PhilSys PCN';
    } else if (discName.includes('pwd')) {
      if (proofLabelEl) proofLabelEl.textContent = 'PWD ID number';
      proofInputEl.placeholder = 'e.g. PWD-2024-00142 or 11-2402-001-0001234';
      proofHintEl.textContent = 'NCDA standard or LGU-issued PWD ID card number';
    } else {
      if (proofLabelEl) proofLabelEl.textContent = selectedRadio.dataset.proofLabel || 'ID number';
      proofInputEl.placeholder = 'e.g. ID-0012345';
      proofHintEl.textContent = 'Valid identification number or verification reference';
    }
  }
}

document.querySelectorAll('input[name="modal_discount_id"]').forEach(r => {
  r.addEventListener('change', updateDiscountModalPreview);
});
updateDiscountModalPreview();

// Reset/populate discount inputs when opening discount modal
document.querySelectorAll('[data-open="#apply-discount-dlg"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const proofInput = document.getElementById('disc-proof-input');
    const noteInput = document.getElementById('disc-note-input');
    const proofErrEl = document.getElementById('disc-proof-err');
    if (proofErrEl) proofErrEl.hidden = true;
    if (currentDiscount) {
      const targetRadio = document.querySelector(`input[name="modal_discount_id"][value="${currentDiscount.id}"]`);
      if (targetRadio) targetRadio.checked = true;
      proofInput.value = currentDiscount.proof || '';
      noteInput.value = currentDiscount.note || '';
    } else {
      proofInput.value = '';
      noteInput.value = '';
    }
    updateDiscountModalPreview();
  });
});

// Clear discount proof error when typing
const proofInputElem = document.getElementById('disc-proof-input');
if (proofInputElem) {
  proofInputElem.addEventListener('input', () => {
    const proofErrEl = document.getElementById('disc-proof-err');
    if (proofErrEl) proofErrEl.hidden = true;
  });
}

// Apply Discount Click
document.getElementById('btn-submit-discount').addEventListener('click', () => {
  const selectedRadio = document.querySelector('input[name="modal_discount_id"]:checked');
  if (!selectedRadio) return;

  const proofVal = document.getElementById('disc-proof-input').value.trim();
  const discName = (selectedRadio.dataset.name || '').toLowerCase();
  const proofErrEl = document.getElementById('disc-proof-err');

  const seniorRegex = /^(?:(?:SC|OSCA|SR)[-\s]?[A-Z0-9-]{4,18}|[0-9]{2,4}[-\s]?[0-9]{4,10}|[0-9]{4}[-\s][0-9]{4}[-\s][0-9]{4}[-\s]?[0-9]{4}|[A-Z0-9-]{5,20})$/i;
  const pwdRegex = /^(?:PWD[-\s]?[A-Z0-9-]{4,18}|[0-9]{2}[-\s][0-9]{4}[-\s][0-9]{3}[-\s]?[0-9]{4,7}|[0-9]{2,4}[-\s]?[0-9]{4,10}|[A-Z0-9-]{5,20})$/i;
  const isDummy = /^(?:0+|12345|test|none|asdf|demo)$/i.test(proofVal);

  if (!proofVal) {
    if (proofErrEl) {
      proofErrEl.textContent = 'Please enter the required ID number or proof.';
      proofErrEl.hidden = false;
    }
    document.getElementById('disc-proof-input').focus();
    return;
  }

  if (discName.includes('senior')) {
    if (!seniorRegex.test(proofVal) || isDummy || proofVal.length < 5) {
      if (proofErrEl) {
        proofErrEl.textContent = 'Invalid OSCA ID format. Example: SC-0048215 or 12-3456-789';
        proofErrEl.hidden = false;
      }
      document.getElementById('disc-proof-input').focus();
      return;
    }
  } else if (discName.includes('pwd')) {
    if (!pwdRegex.test(proofVal) || isDummy || proofVal.length < 5) {
      if (proofErrEl) {
        proofErrEl.textContent = 'Invalid PWD ID format. Example: PWD-2024-00142 or 11-2402-001-0001234';
        proofErrEl.hidden = false;
      }
      document.getElementById('disc-proof-input').focus();
      return;
    }
  } else if (proofVal.length < 3) {
    if (proofErrEl) {
      proofErrEl.textContent = 'Please enter a valid proof (at least 3 characters).';
      proofErrEl.hidden = false;
    }
    document.getElementById('disc-proof-input').focus();
    return;
  }

  if (proofErrEl) proofErrEl.hidden = true;

  const disc = {
    id: selectedRadio.value,
    name: selectedRadio.dataset.name,
    percent: parseFloat(selectedRadio.dataset.percent),
    proof: proofVal,
    note: document.getElementById('disc-note-input').value.trim(),
  };

  applyDiscountDirectly(disc);
});

function applyDiscountDirectly(disc) {
  currentDiscount = disc;
  document.getElementById('apply-discount-dlg').close();

  document.getElementById('disc-unapplied-box').style.display = 'none';
  document.getElementById('disc-applied-box').style.display = 'block';

  document.getElementById('app-disc-type').textContent = `${disc.name}, ${disc.percent}%`;
  document.getElementById('app-disc-id').textContent = disc.proof || 'Verified';
  document.getElementById('app-disc-note').textContent = disc.note || 'ID checked at the booth';

  const subtotal = baseCharge + currentPenalty;
  document.getElementById('lbl-discount-desc').textContent = `${disc.name} ${disc.percent}% of \u20B1${subtotal.toFixed(2)}`;

  // Set hidden payment fields
  document.getElementById('pay-hidden-disc-id').value = disc.id;
  document.getElementById('pay-hidden-disc-proof').value = disc.proof;
  document.getElementById('pay-hidden-disc-note').value = disc.note;
  recalculateFinal();
}

// Remove Discount
document.getElementById('btn-remove-discount').addEventListener('click', () => {
  currentDiscount = null;
  document.getElementById('disc-applied-box').style.display = 'none';
  document.getElementById('disc-unapplied-box').style.display = 'block';
  document.getElementById('lbl-discount-desc').textContent = 'None applied';
  document.getElementById('pay-hidden-disc-id').value = '';
  document.getElementById('pay-hidden-disc-proof').value = '';
  recalculateFinal();
});

// Reset/sync penalty modal when opening
document.querySelectorAll('[data-open="#add-penalty-dlg"]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('input[name="penalty_choice"]').forEach(r => {
      r.checked = (penaltyType && r.value === penaltyType);
    });
    const errEl = document.getElementById('penalty-choice-err');
    if (errEl) errEl.hidden = true;
  });
});

document.querySelectorAll('input[name="penalty_choice"]').forEach(r => {
  r.addEventListener('change', () => {
    const errEl = document.getElementById('penalty-choice-err');
    if (errEl) errEl.hidden = true;
  });
});

// Submit Penalty (BA-42)
document.getElementById('btn-submit-penalty').addEventListener('click', () => {
  const chosen = document.querySelector('input[name="penalty_choice"]:checked');
  const errEl = document.getElementById('penalty-choice-err');
  if (!chosen) {
    if (errEl) {
      errEl.textContent = 'Please select a penalty option.';
      errEl.hidden = false;
    }
    return;
  }
  if (errEl) errEl.hidden = true;

  currentPenalty = parseFloat(chosen.dataset.amt);
  penaltyType = chosen.value;

  if (penaltyType === 'lost_ticket') {
    document.getElementById('lbl-penalty-desc').textContent = `Lost ticket penalty \u20B1${currentPenalty.toFixed(2)}`;
    document.getElementById('pay-hidden-lost').value = '1';
    document.getElementById('pay-hidden-overstay').value = '0';
  } else {
    document.getElementById('lbl-penalty-desc').textContent = `Overstay penalty \u20B1${currentPenalty.toFixed(2)}`;
    document.getElementById('pay-hidden-overstay').value = '1';
    document.getElementById('pay-hidden-lost').value = '0';
  }

  const openBtn = document.getElementById('btn-open-penalty-dlg');
  const rmBtn = document.getElementById('btn-remove-penalty');
  if (openBtn) openBtn.textContent = 'Change penalty';
  if (rmBtn) rmBtn.style.display = 'inline-flex';

  document.getElementById('add-penalty-dlg').close();
  recalculateFinal();
});

// Remove / Undo Penalty
const removePenaltyBtn = document.getElementById('btn-remove-penalty');
if (removePenaltyBtn) {
  removePenaltyBtn.addEventListener('click', () => {
    currentPenalty = 0.0;
    penaltyType = null;

    document.getElementById('lbl-penalty-desc').textContent = 'None';
    document.getElementById('lbl-penalty-amt').textContent = '\u20B10.00';
    document.getElementById('pay-hidden-lost').value = '0';
    document.getElementById('pay-hidden-overstay').value = '0';

    const openBtn = document.getElementById('btn-open-penalty-dlg');
    if (openBtn) openBtn.textContent = 'Add penalty';
    removePenaltyBtn.style.display = 'none';

    recalculateFinal();
  });
}
</script>
@endpush
@endsection
