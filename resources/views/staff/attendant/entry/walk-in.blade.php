@extends('layouts.attendant')

@section('title', 'New walk-in entry - Booth Attendant')
@section('tab', 'entry')

@section('content')
<h1 class="ba-page-title">New entry</h1>
<p class="ba-sub" id="walkin-sub">Walk-in driver. Enter the plate to check if the vehicle is already saved.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Entry type</span>
  <span class="ba-step active" id="step-plate">2. Plate</span>
  <span class="ba-step" id="step-details">3. Vehicle details</span>
  <span class="ba-step" id="step-slot">4. Slot</span>
  <span class="ba-step">5. Ticket</span>
</div>

@if ($errors->any())
  <div style="background:#FEF2F2;border-left:5px solid #EF4444;border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px;color:#991B1B;">
    <b style="font-size:15px;display:block;margin-bottom:4px;">Cannot create ticket &mdash; please check the details:</b>
    <ul style="margin:0;padding-left:18px;font-size:13px;">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form id="walkin-form" method="POST" action="{{ route('attendant.entry.walk-in.store') }}">
  @csrf
  <input type="hidden" name="driver_id" id="hidden-driver-id" value="">
  <input type="hidden" name="slot_code" id="hidden-slot-code" value="">

  <div style="display:grid;grid-template-columns:1.8fr 1fr;gap:24px;align-items:start;">
    
    <!-- LEFT PANEL: Dynamic Steps -->
    <div>

      <!-- STEP 2: Plate Lookup (BA-12, BA-13, BA-14) -->
      <div class="ba-card" id="section-plate">
        <h2>Find the vehicle</h2>
        
        <div class="ba-form-group">
          <label class="ba-label" for="plate-input">Plate number</label>
          <input class="ba-input" id="plate-input" name="plate" type="text"
                 placeholder="Enter the plate number" style="font-size:24px;font-weight:800;letter-spacing:1px;text-transform:uppercase;"
                 autocomplete="off" autofocus>
          <p style="font-size:12px;color:var(--muted);margin-top:4px;">Letters and digits as printed on the plate, for example ABC 1234.</p>
          <div class="ba-err-msg" id="plate-err" hidden></div>
        </div>

        <!-- Result Box: Known Vehicle (BA-13) -->
        <div id="known-vehicle-box" style="display:none;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;padding:16px;margin-bottom:18px;">
          <b style="color:#166534;font-size:15px;display:block;">Known vehicle</b>
          <p style="font-size:13px;color:#15803D;margin:3px 0 12px;">Saved in the Bayline app by a registered driver. The details below were filled in for you.</p>
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:14px;background:#fff;padding:12px;border-radius:6px;border:1px solid #BBF7D0;">
            <div><span style="color:var(--muted);">Registered driver:</span> <b id="lbl-driver-name">-</b></div>
            <div><span style="color:var(--muted);">Phone:</span> <b id="lbl-driver-phone">-</b></div>
            <div><span style="color:var(--muted);">Color:</span> <b id="lbl-driver-color">-</b></div>
            <div><span style="color:var(--muted);">Type:</span> <b id="lbl-driver-type">-</b></div>
            <div><span style="color:var(--muted);">Last visit:</span> <b id="lbl-driver-last">-</b></div>
          </div>

          <div id="expired-warning-box" style="display:none;background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:10px;margin-top:12px;color:#991B1B;font-size:13px;">
            <b>Reservation <span id="lbl-expired-code"></span> expired</b><br>
            This arrival is entered as a walk-in. The old code cannot be used.
          </div>
        </div>

        <!-- Result Box: Guest Vehicle (BA-14) -->
        <div id="guest-vehicle-box" style="display:none;background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:8px;padding:16px;margin-bottom:18px;">
          <b style="color:#334155;font-size:15px;display:block;">No saved vehicle for <span id="lbl-guest-plate"></span></b>
          <p style="font-size:13px;color:#64748B;margin:3px 0 0;">Enter the vehicle details by hand. The entry is saved as a guest vehicle without a driver account.</p>
        </div>

        <!-- Step 2 Buttons -->
        <div style="display:flex;gap:10px;margin-top:10px;" id="plate-btns">
          <button class="btn primary" type="button" id="btn-lookup">Look up plate</button>
          <a class="btn ghost" href="{{ route('attendant.entry.index') }}">Back</a>
        </div>

        <!-- Registered / Guest Actions -->
        <div style="display:none;gap:10px;margin-top:10px;" id="known-btns">
          <button class="btn primary" type="button" id="btn-use-known">Use these details</button>
          <button class="btn ghost" type="button" id="btn-edit-known">Edit details</button>
          <button class="btn ghost" type="button" id="btn-search-again-1">Search again</button>
        </div>

        <div style="display:none;gap:10px;margin-top:10px;" id="guest-btns">
          <button class="btn primary" type="button" id="btn-enter-guest">Enter vehicle details</button>
          <button class="btn ghost" type="button" id="btn-search-again-2">Search again</button>
        </div>
      </div>

      <!-- STEP 3: Vehicle Details Form (BA-15) -->
      <div class="ba-card" id="section-details" style="display:none;">
        <h2>Vehicle details</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="ba-form-group">
            <label class="ba-label">Plate number</label>
            <input class="ba-input" id="det-plate" type="text" readonly style="font-weight:700;">
          </div>
          <div class="ba-form-group">
            <label class="ba-label" for="det-phone">Phone number</label>
            <input class="ba-input" id="det-phone" name="phone" type="tel" placeholder="0917 555 0199" maxlength="11">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="ba-form-group">
            <label class="ba-label" for="det-color">Car color</label>
            <select class="ba-input" id="det-color" name="color">
              <option value="">Select a color</option>
              @foreach ($vehicleColors as $col)
                <option value="{{ $col }}">{{ $col }}</option>
              @endforeach
            </select>
          </div>
          <div class="ba-form-group">
            <label class="ba-label" for="det-type">Car type</label>
            <select class="ba-input" id="det-type" name="type">
              <option value="">Select a type</option>
              @foreach ($vehicleTypes as $vt)
                <option value="{{ $vt }}">{{ $vt }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="ba-form-group">
          <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="det-pwd" style="width:18px;height:18px;">
            Driver needs a PWD slot (highlights PWD slots first on the lot map)
          </label>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
          <button class="btn primary" type="button" id="btn-to-slot">Continue to slot selection</button>
          <button class="btn ghost" type="button" id="btn-back-to-plate">Back</button>
        </div>
      </div>

      <!-- STEP 4: Slot Selection (BA-16, BA-23) -->
      <div id="section-slot" style="display:none;">
        @if ($counts['available'] === 0)
          <!-- LOT FULL (BA-23) -->
          <div class="ba-card" style="margin-bottom:20px;border-left:5px solid var(--stop);">
            <div style="display:flex;gap:14px;align-items:center;">
              <span class="badge" style="background:var(--stop);color:#fff;font-size:16px;padding:6px 12px;">FULL</span>
              <div>
                <h3 style="font-size:20px;margin:0 0 4px;color:var(--stop);">No Available slots</h3>
                <p style="margin:0;font-size:14px;color:var(--muted);">Do not issue a ticket. Tell the driver how long the wait may be.</p>
              </div>
            </div>
            @if ($nearestPending)
              <div style="margin-top:16px;padding:12px;background:#F8FAFC;border-radius:6px;border:1px solid #E2E8F0;font-size:13px;">
                <b>Nearest release:</b> Slot {{ $nearestPending->slot_code }}, {{ \App\Support\Lot::plate($nearestPending->plate_number) }}<br>
                Paid at {{ \App\Support\Lot::time($nearestPending->payment?->paid_at) }}<br>
                Expected release by {{ \App\Support\Lot::time($nearestPending->payment?->paid_at?->copy()->addMinutes(15)) }} (end of 15 min exit window)
              </div>
            @endif
          </div>
        @endif

        <div class="ba-card">
          <!-- PWD Priority Banner -->
          <div id="pwd-banner" style="display:none;background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#1E40AF;align-items:center;justify-content:space-between;gap:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-size:22px;line-height:1;">♿</span>
              <div>
                <b style="font-size:14px;display:block;">PWD Priority Active</b>
                <span style="font-size:12.5px;color:#2563EB;">Available accessible (PWD) slots are highlighted in glowing blue and prioritized.</span>
              </div>
            </div>
            <button type="button" id="btn-toggle-pwd" class="btn sm ghost" style="border-color:#93C5FD;background:#DBEAFE;color:#1D4ED8;font-size:12px;padding:4px 10px;height:auto;min-height:28px;">Remove filter</button>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <div>
              <h2 style="margin:0 0 4px;">Lot map</h2>
              <p style="font-size:13px;color:var(--muted);margin:0;">Only Available slots can be picked. Other slots are dimmed.</p>
            </div>
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:6px 12px;color:var(--ink);">
              <input type="checkbox" id="map-pwd-filter" style="width:16px;height:16px;">
              <span>Highlight PWD slots</span>
            </label>
          </div>

          <div class="ba-lot-map">
            @foreach ($slots->groupBy('zone') as $zoneName => $zoneSlots)
              <div class="ba-zone">
                <div class="ba-zone-title">{{ $zoneName }}</div>
                <div class="ba-slot-grid" style="grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;">
                  @foreach ($zoneSlots as $s)
                    @php $isAvail = ($s->status === 'available'); @endphp
                    <div class="ba-slot {{ $s->status }} @if(!$isAvail) dimmed @endif"
                         data-slot="{{ $s->code }}" data-zone="{{ $s->zone }}" data-type="{{ $s->type }}"
                         @if($isAvail) onclick="selectSlot(this)" @endif>
                       @if($s->type === 'PWD')<span class="pwd-tag">PWD</span>@endif
                      <b>{{ $s->code }}</b>
                      <span class="st">{{ $s->statusLabel() }}</span>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
            <div style="display:flex;gap:12px;margin-top:10px;">
              <div class="ba-facility" style="flex:1;padding:10px;">Booth</div>
              <div class="ba-facility" style="flex:1;padding:10px;">Guard gate</div>
            </div>
          </div>

          <!-- Legend matching Driver App design -->
          <div class="leg ba-detail-box" style="margin-top:14px;gap:10px 20px;">
            <span><i></i>Available</span>
            <span><i class="r"></i>Reserved</span>
            <span><i class="o"></i>Occupied</span>
            <span><i class="p"></i>Pending exit</span>
            <span><i class="x"></i>Out of service</span>
            <span style="display:inline-flex;align-items:center;gap:6px;">
              <span style="font-size:10px;font-weight:900;background:#2563EB;color:#fff;border-radius:3px;padding:2px 5px;">♿ PWD</span> Priority
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL: Summary Box (BA-12 to BA-16) -->
    <div>
      <div class="ba-card" id="summary-box">
        <h2 id="summary-title">Lot now</h2>

        <!-- Right Side: Lot Now Overview (BA-12, BA-13) -->
        <div id="side-lot-now">
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span>Available slots</span>
            <b>{{ $counts['available'] }}</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span>Reserved, arriving soon</span>
            <b>{{ $counts['reserved'] }}</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span>Pending exit</span>
            <b>{{ $counts['pending_exit'] }}</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span>Out of service</span>
            <b>{{ $counts['out_of_service'] }}</b>
          </div>
          <div style="margin-top:18px;font-size:14px;color:var(--muted);">
            Next ticket number: <b style="color:var(--ink);">{{ $nextTicket }}</b>
          </div>
        </div>

        <!-- Right Side: Entry Summary on Step 4 (BA-16) -->
        <div id="side-entry-summary" style="display:none;">
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span style="color:var(--muted);">Plate</span>
            <b id="sum-plate">-</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span style="color:var(--muted);">Vehicle</span>
            <b id="sum-veh">-</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span style="color:var(--muted);">Phone</span>
            <b id="sum-phone">-</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span style="color:var(--muted);">Entry type</span>
            <b>Walk-in</b>
          </div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;">
            <span style="color:var(--muted);">Slot</span>
            <b id="sum-slot" style="color:var(--paint-deep);">None picked</b>
          </div>

          <div style="display:flex;gap:10px;margin-top:20px;">
            <button class="btn primary" type="button" id="btn-confirm-slot" disabled data-open="#confirm-slot-dlg">Confirm slot</button>
            <button class="btn ghost" type="button" id="btn-back-to-details">Back</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- POP-UP BA-17: Confirm Slot Modal -->
  <dialog class="badlg" id="confirm-slot-dlg">
    <div class="in">
      <button class="x" type="button" data-close aria-label="Close">&times;</button>
      <h2>Assign slot <span id="dlg-slot-code"></span>?</h2>
      
      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Plate</span>
          <b id="dlg-plate"></b>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Vehicle</span>
          <b id="dlg-veh"></b>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Slot</span>
          <b id="dlg-slot-full"></b>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--muted);">Entry time</span>
          <b>{{ \App\Support\Lot::stamp(now()) }}</b>
        </div>
      </div>

      <p>The slot is checked again when you confirm. If another entry took it, you will be asked to pick again.</p>

      <div class="actions">
        <button class="btn ghost" type="button" data-close>Back to map</button>
        <button class="btn primary" type="submit">Confirm and create ticket</button>
      </div>
    </div>
  </dialog>
</form>

<!-- POP-UP BA-21: Active Session Already Exists -->
<dialog class="badlg {{ (session('error_modal') && session('error_modal')['type'] === 'active_session_exists') ? 'auto-open' : '' }}" id="active-session-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">This plate already has an active session</h2>
    
    <div style="background:#F8FAFC;border:1.5px dashed #94A3B8;border-radius:8px;padding:18px;margin-bottom:16px;font-family:'Courier New',Courier,monospace;">
      <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Ticket number</div>
      <div style="font-size:24px;font-weight:800;" id="dup-ticket">{{ session('error_modal')['ticket'] ?? '' }}</div>
      <div style="margin-top:10px;font-size:14px;">
        Plate: <b id="dup-plate">{{ session('error_modal')['plate'] ?? '' }}</b><br>
        Slot: <b id="dup-slot">{{ session('error_modal')['slot'] ?? '' }}</b><br>
        Entry: <span id="dup-entry">{{ session('error_modal')['entry'] ?? '' }}</span>
      </div>
    </div>

    <p>A vehicle cannot have two active sessions. If the driver is leaving, take the payment in Exit and payment.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Back</button>
      <a class="btn primary" id="dup-link" href="{{ isset(session('error_modal')['session_id']) ? url('staff/attendant/exit/session/' . session('error_modal')['session_id']) : route('attendant.exit.index') }}">Open in Exit and payment</a>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-22: Slot Just Taken -->
@if(session('error_modal') && session('error_modal')['type'] === 'slot_just_taken')
<dialog class="badlg auto-open" id="slot-taken-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">{{ session('error_modal')['slot'] }} was just taken</h2>
    <p>Another entry started a session on {{ session('error_modal')['slot'] }} while you were choosing. The slot is now Occupied.<br><br>Your vehicle details are kept. Pick another Available slot.</p>
    <div class="actions">
      <button class="btn primary" type="button" data-close>Choose another slot</button>
    </div>
  </div>
</dialog>
@endif

@push('scripts')
<script>
let vehicleState = { plate: '', phone: '', color: '', type: '', isRegistered: false, driverId: null };
let selectedSlot = null;

const lookupUrl = "{{ route('attendant.entry.walk-in.lookup') }}";
const csrfToken = "{{ csrf_token() }}";

// DOM Elements
const plateInput = document.getElementById('plate-input');
const plateErr = document.getElementById('plate-err');
const btnLookup = document.getElementById('btn-lookup');
const knownBox = document.getElementById('known-vehicle-box');
const guestBox = document.getElementById('guest-vehicle-box');
const knownBtns = document.getElementById('known-btns');
const guestBtns = document.getElementById('guest-btns');
const plateBtns = document.getElementById('plate-btns');

// Lookup handler
async function doLookup() {
  const plate = plateInput.value.trim();
  plateErr.hidden = true;
  if (!plate) {
    plateErr.textContent = 'Enter a plate like ABC 1234.';
    plateErr.hidden = false;
    return;
  }

  try {
    const res = await fetch(lookupUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ plate })
    });
    const data = await res.json();

    if (data.status === 'active_session_exists') {
      document.getElementById('dup-ticket').textContent = data.session.ticket_number;
      document.getElementById('dup-plate').textContent = data.session.plate;
      document.getElementById('dup-slot').textContent = data.session.slot;
      document.getElementById('dup-entry').textContent = data.session.entry;
      document.getElementById('dup-link').href = "{{ url('staff/attendant/exit/session') }}/" + data.session.id;
      document.getElementById('active-session-dlg').showModal();
      return;
    }

    if (!res.ok) {
      plateErr.textContent = data.error || 'Invalid plate.';
      plateErr.hidden = false;
      return;
    }

    plateBtns.style.display = 'none';

    if (data.status === 'registered') {
      const v = data.vehicle;
      vehicleState = { plate: v.plate, phone: v.phone, color: v.color, type: v.type, isRegistered: true, driverId: v.driver_id };
      document.getElementById('lbl-driver-name').textContent = v.driver_name;
      document.getElementById('lbl-driver-phone').textContent = v.phone;
      document.getElementById('lbl-driver-color').textContent = v.color;
      document.getElementById('lbl-driver-type').textContent = v.type;
      document.getElementById('lbl-driver-last').textContent = v.last_visit;

      if (data.expired_reservation) {
        document.getElementById('lbl-expired-code').textContent = data.expired_reservation.code;
        document.getElementById('expired-warning-box').style.display = 'block';
      } else {
        document.getElementById('expired-warning-box').style.display = 'none';
      }

      knownBox.style.display = 'block';
      knownBtns.style.display = 'flex';
      guestBox.style.display = 'none';
    } else {
      vehicleState = { plate: data.plate, phone: '', color: '', type: '', isRegistered: false, driverId: null };
      document.getElementById('lbl-guest-plate').textContent = data.plate;
      guestBox.style.display = 'block';
      guestBtns.style.display = 'flex';
      knownBox.style.display = 'none';
    }
  } catch (e) {
    console.error(e);
  }
}

btnLookup.addEventListener('click', doLookup);
plateInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doLookup(); } });

// Search again reset
function resetLookup() {
  knownBox.style.display = 'none';
  guestBox.style.display = 'none';
  knownBtns.style.display = 'none';
  guestBtns.style.display = 'none';
  plateBtns.style.display = 'flex';
  plateInput.focus();
}
document.getElementById('btn-search-again-1').addEventListener('click', resetLookup);
document.getElementById('btn-search-again-2').addEventListener('click', resetLookup);

// Advance to Step 3 or directly to Step 4
function goToDetails(populate = false) {
  document.getElementById('section-plate').style.display = 'none';
  document.getElementById('section-details').style.display = 'block';
  document.getElementById('step-plate').classList.remove('active');
  document.getElementById('step-details').classList.add('active');

  document.getElementById('det-plate').value = vehicleState.plate;
  if (populate) {
    document.getElementById('det-phone').value = vehicleState.phone;
    document.getElementById('det-color').value = vehicleState.color;
    document.getElementById('det-type').value = vehicleState.type;
  }
}

document.getElementById('btn-use-known').addEventListener('click', () => {
  goToDetails(true);
  goToSlot();
});

document.getElementById('btn-edit-known').addEventListener('click', () => {
  goToDetails(true);
});

document.getElementById('btn-enter-guest').addEventListener('click', () => {
  goToDetails(false);
});

document.getElementById('btn-back-to-plate').addEventListener('click', () => {
  document.getElementById('section-details').style.display = 'none';
  document.getElementById('section-plate').style.display = 'block';
  document.getElementById('step-details').classList.remove('active');
  document.getElementById('step-plate').classList.add('active');
});

// Step 3 to Step 4: Slot Selection
function goToSlot() {
  const color = document.getElementById('det-color').value;
  const type = document.getElementById('det-type').value;

  if (!color || !type) {
    alert('Please choose a color and a type.');
    return;
  }

  vehicleState.color = color;
  vehicleState.type = type;
  vehicleState.phone = document.getElementById('det-phone').value;

  document.getElementById('section-details').style.display = 'none';
  document.getElementById('section-slot').style.display = 'block';
  document.getElementById('step-details').classList.remove('active');
  document.getElementById('step-slot').classList.add('active');

  // Switch right panel from "Lot now" to "Entry summary"
  document.getElementById('summary-title').textContent = 'Entry summary';
  document.getElementById('side-lot-now').style.display = 'none';
  document.getElementById('side-entry-summary').style.display = 'block';

  document.getElementById('sum-plate').textContent = vehicleState.plate;
  document.getElementById('sum-veh').textContent = `${vehicleState.color} ${vehicleState.type}`;
  document.getElementById('sum-phone').textContent = vehicleState.phone || '-';

  document.getElementById('hidden-driver-id').value = vehicleState.driverId || '';

  // Synchronize and apply PWD highlight (do NOT auto-select; attendant picks the slot)
  const isPwd = document.getElementById('det-pwd').checked;
  applyPwdFilter(isPwd);
}

function applyPwdFilter(isPwd) {
  const detPwd = document.getElementById('det-pwd');
  const mapPwd = document.getElementById('map-pwd-filter');
  const banner = document.getElementById('pwd-banner');

  if (detPwd) detPwd.checked = isPwd;
  if (mapPwd) mapPwd.checked = isPwd;

  if (isPwd) {
    if (banner) banner.style.display = 'flex';
    document.querySelectorAll('.ba-slot').forEach(s => {
      if (s.dataset.type === 'PWD' && s.classList.contains('available')) {
        s.classList.add('pwd-highlight');
      } else {
        s.classList.remove('pwd-highlight');
      }
    });
  } else {
    if (banner) banner.style.display = 'none';
    document.querySelectorAll('.ba-slot.pwd-highlight').forEach(s => s.classList.remove('pwd-highlight'));
  }
}

document.getElementById('btn-to-slot').addEventListener('click', goToSlot);

const mapPwdToggle = document.getElementById('map-pwd-filter');
if (mapPwdToggle) {
  mapPwdToggle.addEventListener('change', (e) => {
    applyPwdFilter(e.target.checked);
  });
}

const btnTogglePwd = document.getElementById('btn-toggle-pwd');
if (btnTogglePwd) {
  btnTogglePwd.addEventListener('click', () => {
    applyPwdFilter(false, false);
  });
}

document.getElementById('btn-back-to-details').addEventListener('click', () => {
  document.getElementById('section-slot').style.display = 'none';
  document.getElementById('section-details').style.display = 'block';
  document.getElementById('step-slot').classList.remove('active');
  document.getElementById('step-details').classList.add('active');

  document.getElementById('summary-title').textContent = 'Lot now';
  document.getElementById('side-lot-now').style.display = 'block';
  document.getElementById('side-entry-summary').style.display = 'none';
});

// Slot click selection
window.selectSlot = function(el) {
  if (!el || !el.classList.contains('available')) return;
  document.querySelectorAll('.ba-slot.selected').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');

  selectedSlot = {
    code: el.dataset.slot,
    zone: el.dataset.zone,
    type: el.dataset.type
  };

  document.getElementById('hidden-slot-code').value = selectedSlot.code;
  document.getElementById('sum-slot').textContent = `${selectedSlot.code} (${selectedSlot.zone}, ${selectedSlot.type})`;
  document.getElementById('btn-confirm-slot').disabled = false;

  // Populate modal
  document.getElementById('dlg-slot-code').textContent = selectedSlot.code;
  document.getElementById('dlg-slot-full').textContent = `${selectedSlot.code}, ${selectedSlot.zone}, ${selectedSlot.type}`;
  document.getElementById('dlg-plate').textContent = vehicleState.plate;
  document.getElementById('dlg-veh').textContent = `${vehicleState.color} ${vehicleState.type}`;
};
</script>
@endpush
@endsection
