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
        <div class="ba-fee-row" style="color:var(--ok);">
          <span>Daily maximum cap</span>
          <b>-{{ \App\Support\Lot::peso(abs($estimate['cap_adjustment'])) }}</b>
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

      <div style="margin-top:16px;">
        <button class="btn ghost sm" type="button" data-open="#add-penalty-dlg">Add penalty</button>
      </div>
    </div>

    <!-- Discount Section (BA-37 vs BA-38) -->
    <div class="ba-card" style="margin-bottom:20px;">
      <h2>Discount</h2>

      <!-- State 1: No discount (BA-37) -->
      <div id="disc-unapplied-box">
        <p style="color:var(--muted);font-size:14px;margin-bottom:16px;">
          No discount applied. Senior and PWD need an ID number. Tenant validation needs a manager PIN.
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
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Apply discount</h2>

    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
      @foreach ($discounts as $d)
        <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
          <input type="radio" name="modal_discount_id" value="{{ $d->id }}" data-name="{{ $d->name }}" data-percent="{{ $d->percent }}" data-pin="{{ $d->requires_manager_pin ? '1' : '0' }}" data-proof-label="{{ $d->proof_label ?? 'ID number' }}" style="width:18px;height:18px;" @checked($loop->first)>
          <div>
            <b>{{ $d->name }}, {{ $d->percent }}%</b>
            <small style="color:var(--muted);display:block;">
              @if ($d->requires_manager_pin)
                Needs manager approval PIN
              @else
                Needs an ID number
              @endif
            </small>
          </div>
        </label>
      @endforeach
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="disc-proof-input" id="disc-proof-label">ID number / Proof</label>
      <input class="ba-input" id="disc-proof-input" type="text" placeholder="e.g. SC-0048215">
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="disc-note-input">Note (optional)</label>
      <input class="ba-input" id="disc-note-input" type="text" placeholder="Optional note (e.g. ID verified at booth)">
    </div>

    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:12px;margin-bottom:18px;font-size:13px;color:#166534;">
      <b style="display:block;font-size:14px;">New total <span id="dlg-disc-preview-total">₱0.00</span></b>
      Discount of <span id="dlg-disc-amt">₱0.00</span> on ₱{{ number_format($estimate['parking_charge'], 2) }}. Rates are read from Rates and policies.
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-discount">Apply discount</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-40 & BA-41: Manager Approval PIN Modal -->
<dialog class="badlg" id="manager-pin-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Manager approval needed</h2>
    <p>Tenant validation (50%) and penalty waivers need the approval of the Parking manager.</p>

    <div class="ba-form-group">
      <label class="ba-label" for="approving-mgr-select">Approving manager</label>
      <select class="ba-input" id="approving-mgr-select">
        @foreach ($managers as $m)
          <option value="{{ $m->id }}">{{ $m->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="manager-pin-input">Manager PIN</label>
      <input class="ba-input" id="manager-pin-input" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="••••••" autocomplete="off" style="letter-spacing:6px;font-size:20px;">
      <p style="font-size:12px;color:var(--muted);margin-top:4px;">Enter the 6-digit PIN. The attendant does not see it.</p>
      <div class="ba-err-msg" id="pin-err-msg" hidden>Wrong PIN. 2 attempts left.</div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-pin">Approve</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-42: Add Penalty Modal -->
<dialog class="badlg" id="add-penalty-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Add penalty</h2>

    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
        <input type="radio" name="penalty_choice" value="lost_ticket" data-amt="100" checked style="width:18px;height:18px;">
        <div><b>Lost ticket ₱100.00</b></div>
      </label>
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line);border-radius:6px;cursor:pointer;">
        <input type="radio" name="penalty_choice" value="overstay" data-amt="150" style="width:18px;height:18px;">
        <div><b>Overstay ₱150.00</b></div>
      </label>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="penalty-note-input">Note (optional)</label>
      <input class="ba-input" id="penalty-note-input" type="text" placeholder="Driver could not find the ticket. ID shown.">
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">Removing a penalty later needs the manager PIN.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-submit-penalty">Add penalty</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-44 & BA-45: Take Cash Payment Modal -->
<dialog class="badlg" id="cash-payment-dlg">
  <form class="in" method="POST" action="{{ route('attendant.exit.pay', $session) }}">
    @csrf
    <input type="hidden" name="discount_id" id="pay-hidden-disc-id" value="">
    <input type="hidden" name="discount_proof" id="pay-hidden-disc-proof" value="">
    <input type="hidden" name="discount_note" id="pay-hidden-disc-note" value="">
    <input type="hidden" name="approving_manager" id="pay-hidden-mgr" value="">
    <input type="hidden" name="lost_ticket" id="pay-hidden-lost" value="0">
    <input type="hidden" name="overstay" id="pay-hidden-overstay" value="0">

    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Take cash payment</h2>
    
    <div style="font-size:14px;margin-bottom:14px;color:var(--muted);">
      Ticket <b>{{ $session->ticket_number }}</b>, {{ \App\Support\Lot::plate($session->plate_number) }}<br>
      Amount due: <b style="font-size:18px;color:var(--ink);" id="due-amount-val" data-due="{{ $estimate['total'] }}">{{ \App\Support\Lot::peso($estimate['total']) }}</b>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="cash-received-input">Cash received</label>
      <input class="ba-input" id="cash-received-input" name="cash_received" type="number" step="any"
             value="{{ $estimate['total'] }}"
             style="font-size:24px;font-weight:800;" autofocus>
      <div class="ba-err-msg" id="cash-error-msg" hidden></div>
    </div>

    <!-- Quick Amount Buttons (BA-44) -->
    <div style="display:flex;gap:8px;margin-bottom:18px;">
      <button class="btn ghost sm" type="button" data-quick-cash="exact">Exact</button>
      <button class="btn ghost sm" type="button" data-quick-cash="100">₱100</button>
      <button class="btn ghost sm" type="button" data-quick-cash="200">₱200</button>
      <button class="btn ghost sm" type="button" data-quick-cash="500">₱500</button>
    </div>

    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:12px;margin-bottom:20px;font-size:14px;">
      Change to give back: <b id="change-val" style="font-size:20px;color:#166534;margin-left:8px;">₱0.00</b>
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
let currentPenalty = 0.0;
let penaltyType = null;
let currentDiscount = null;
const verifyPinUrl = "{{ route('attendant.exit.verify-pin', $session) }}";
const exitCsrf = "{{ csrf_token() }}";

function recalculateFinal() {
  let discAmt = 0.0;
  if (currentDiscount) {
    discAmt = Math.round((baseCharge * currentDiscount.percent) / 100 * 100) / 100;
  }
  const total = Math.max(0, baseCharge - discAmt + currentPenalty);

  document.getElementById('lbl-discount-amt').textContent = discAmt > 0 ? `-\u20B1${discAmt.toFixed(2)}` : '\u20B10.00';
  document.getElementById('lbl-penalty-amt').textContent = currentPenalty > 0 ? `\u20B1${currentPenalty.toFixed(2)}` : '\u20B10.00';
  document.getElementById('final-amt-display').textContent = `\u20B1${total.toFixed(2)}`;

  // Update modal due
  const dueVal = document.getElementById('due-amount-val');
  if (dueVal) {
    dueVal.dataset.due = total.toFixed(2);
    dueVal.textContent = `\u20B1${total.toFixed(2)}`;
  }
  const cashIn = document.getElementById('cash-received-input');
  if (cashIn) {
    cashIn.value = total.toFixed(2);
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
      cashIn.value = parseFloat(dueVal.dataset.due || '0').toFixed(2);
      cashIn.dispatchEvent(new Event('input'));
    }
  });
}

function updateDiscountModalPreview() {
  const selectedRadio = document.querySelector('input[name="modal_discount_id"]:checked');
  if (!selectedRadio) return;

  const pct = parseFloat(selectedRadio.dataset.percent) || 0;
  const discAmt = Math.round((baseCharge * pct) / 100 * 100) / 100;
  const newTotal = Math.max(0, baseCharge - discAmt);

  const prevTotalEl = document.getElementById('dlg-disc-preview-total');
  const prevAmtEl = document.getElementById('dlg-disc-amt');
  if (prevTotalEl) prevTotalEl.textContent = `\u20B1${newTotal.toFixed(2)}`;
  if (prevAmtEl) prevAmtEl.textContent = `\u20B1${discAmt.toFixed(2)}`;

  const proofLabel = selectedRadio.dataset.proofLabel || (selectedRadio.dataset.pin === '1' ? 'Stamp code / Tenant ID' : 'ID number');
  const proofLabelEl = document.getElementById('disc-proof-label');
  const proofInputEl = document.getElementById('disc-proof-input');
  if (proofLabelEl) proofLabelEl.textContent = proofLabel;
  if (proofInputEl) {
    if (selectedRadio.dataset.pin === '1') {
      proofInputEl.placeholder = 'e.g. STAMP-9214 or Tenant Name';
    } else {
      proofInputEl.placeholder = 'e.g. SC-0048215';
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

// Apply Discount Click
document.getElementById('btn-submit-discount').addEventListener('click', () => {
  const selectedRadio = document.querySelector('input[name="modal_discount_id"]:checked');
  if (!selectedRadio) return;

  const needsPin = selectedRadio.dataset.pin === '1';
  const disc = {
    id: selectedRadio.value,
    name: selectedRadio.dataset.name,
    percent: parseFloat(selectedRadio.dataset.percent),
    proof: document.getElementById('disc-proof-input').value.trim(),
    note: document.getElementById('disc-note-input').value.trim(),
  };

  if (needsPin) {
    // Open Manager PIN Dialog
    document.getElementById('apply-discount-dlg').close();
    const pinDlg = document.getElementById('manager-pin-dlg');
    const pinInput = document.getElementById('manager-pin-input');
    const submitBtn = document.getElementById('btn-submit-pin');
    pinInput.disabled = false;
    submitBtn.disabled = false;
    pinInput.value = '';
    document.getElementById('pin-err-msg').hidden = true;
    pinDlg.showModal();
    setTimeout(() => pinInput.focus(), 60);
    window.pendingDiscount = disc;
    return;
  }

  applyDiscountDirectly(disc);
});

function applyDiscountDirectly(disc, managerName = null) {
  currentDiscount = disc;
  document.getElementById('apply-discount-dlg').close();

  document.getElementById('disc-unapplied-box').style.display = 'none';
  document.getElementById('disc-applied-box').style.display = 'block';

  document.getElementById('app-disc-type').textContent = `${disc.name}, ${disc.percent}%`;
  document.getElementById('app-disc-id').textContent = disc.proof || 'Verified';
  document.getElementById('app-disc-note').textContent = disc.note || 'ID checked at the booth';

  document.getElementById('lbl-discount-desc').textContent = `${disc.name} ${disc.percent}% of \u20B1${baseCharge.toFixed(2)}`;

  // Set hidden payment fields
  document.getElementById('pay-hidden-disc-id').value = disc.id;
  document.getElementById('pay-hidden-disc-proof').value = disc.proof;
  document.getElementById('pay-hidden-disc-note').value = disc.note;
  if (managerName) {
    document.getElementById('pay-hidden-mgr').value = managerName;
  }

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

// Manager PIN input handling (digits only, max 6)
const mgrPinInput = document.getElementById('manager-pin-input');
if (mgrPinInput) {
  mgrPinInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    document.getElementById('pin-err-msg').hidden = true;
  });

  mgrPinInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      document.getElementById('btn-submit-pin').click();
      return;
    }
    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Escape'];
    if (!allowedKeys.includes(e.key) && !/^[0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
    }
  });
}

// Submit Manager PIN (BA-40 & BA-41)
document.getElementById('btn-submit-pin').addEventListener('click', async () => {
  const managerId = document.getElementById('approving-mgr-select').value;
  const pinInput = document.getElementById('manager-pin-input');
  const pin = pinInput.value.trim();
  const pinErr = document.getElementById('pin-err-msg');
  pinErr.hidden = true;

  if (pin.length !== 6 || !/^\d{6}$/.test(pin)) {
    pinErr.textContent = 'Please enter a 6-digit manager PIN.';
    pinErr.hidden = false;
    pinInput.focus();
    return;
  }

  try {
    const res = await fetch(verifyPinUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': exitCsrf, 'Accept': 'application/json' },
      body: JSON.stringify({ manager_id: managerId, pin })
    });
    const data = await res.json();

    if (!data.ok) {
      pinErr.textContent = data.message || 'Wrong PIN.';
      pinErr.hidden = false;
      if (data.blocked) {
        pinInput.disabled = true;
        document.getElementById('btn-submit-pin').disabled = true;
      } else {
        pinInput.focus();
        pinInput.select();
      }
      return;
    }

    document.getElementById('manager-pin-dlg').close();
    applyDiscountDirectly(window.pendingDiscount, data.manager_name);
  } catch (e) {
    console.error(e);
    pinErr.textContent = 'Could not verify PIN. Please try again.';
    pinErr.hidden = false;
  }
});

// Submit Penalty (BA-42)
document.getElementById('btn-submit-penalty').addEventListener('click', () => {
  const chosen = document.querySelector('input[name="penalty_choice"]:checked');
  if (!chosen) return;

  currentPenalty = parseFloat(chosen.dataset.amt);
  penaltyType = chosen.value;

  if (penaltyType === 'lost_ticket') {
    document.getElementById('lbl-penalty-desc').textContent = 'Lost ticket penalty \u20B1100.00';
    document.getElementById('pay-hidden-lost').value = '1';
  } else {
    document.getElementById('lbl-penalty-desc').textContent = 'Overstay penalty \u20B1150.00';
    document.getElementById('pay-hidden-overstay').value = '1';
  }

  document.getElementById('add-penalty-dlg').close();
  recalculateFinal();
});
</script>
@endpush
@endsection
