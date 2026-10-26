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
  <div style="background:var(--stop-bg);border-left:5px solid var(--stop);border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px;color:var(--stop);">
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
          <div style="position:relative;">
            <input class="ba-input" id="plate-input" name="plate" type="text"
                   maxlength="20"
                   value="{{ request('plate', '') }}"
                   placeholder="e.g. ABC 1234, AB 12345, or 1" style="font-size:24px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding-right:44px;transition:border-color .15s, background-color .15s;"
                   autocomplete="off" autofocus>
            <span id="plate-live-icon" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:18px;display:none;pointer-events:none;"></span>
          </div>
          <div id="plate-live-feedback" style="font-size:12px;color:var(--muted);margin-top:6px;font-weight:600;min-height:18px;display:flex;align-items:center;gap:4px;">
            Philippine standard (ABC 1234, AB 12345), legacy, vintage, vanity, or protocol plates (e.g. 1 for President).
          </div>
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

          <div id="expired-warning-box" style="display:none;background:var(--stop-bg);border:1px solid var(--stop);border-radius:6px;padding:10px;margin-top:12px;color:var(--stop);font-size:13px;">
            <b>Reservation <span id="lbl-expired-code"></span> expired</b><br>
            This arrival is entered as a walk-in. The old code cannot be used.
          </div>
        </div>

        <!-- Result Box: Guest Vehicle (BA-14) -->
        <div id="guest-vehicle-box" class="ba-detail-box" style="display:none;background:var(--bg);border:1.5px solid var(--line);border-radius:8px;padding:16px;margin-bottom:18px;color:var(--ink);">
          <b style="color:var(--ink);font-size:15px;display:block;">No saved vehicle for <span id="lbl-guest-plate"></span></b>
          <p style="font-size:13px;color:var(--muted);margin:3px 0 0;">Enter the vehicle details by hand. The entry is saved as a guest vehicle without a driver account.</p>
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

        <!-- Direct Guest Arrival Banner -->
        <div id="guest-details-banner" class="ba-detail-box" style="display:none;background:var(--bg);border-left:4px solid var(--paint);border-radius:0 8px 8px 0;padding:12px 16px;margin-bottom:18px;color:var(--ink);">
          <b style="color:var(--ink);font-size:14px;display:block;">Guest arrival &mdash; <span id="lbl-guest-plate-title"></span></b>
          <p style="font-size:12.5px;color:var(--muted);margin:2px 0 0;">No saved profile found for this plate. Please enter the car color and vehicle type below.</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="ba-form-group">
            <label class="ba-label">Plate number</label>
            <input class="ba-input" id="det-plate" type="text" readonly style="font-weight:700;">
          </div>

          <div class="ba-form-group">
            <label class="ba-label" for="det-phone">Phone number (optional)</label>
            <input class="ba-input" id="det-phone" name="phone" type="tel" placeholder="09171234567" maxlength="11"
                   oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11); const pe = document.getElementById('phone-err-msg'); if (pe) pe.hidden = true;">
            <p style="font-size:12px;color:var(--muted);margin-top:4px;">11-digit mobile starting with 09 (e.g. 09171234567)</p>
            <div class="ba-err-msg" id="phone-err-msg" hidden style="margin-top:4px;font-size:12px;"></div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="ba-form-group">
            <label class="ba-label" for="det-color">Vehicle color</label>
            <select class="ba-input" id="det-color" name="color">
              <option value="">Select a color</option>
              @foreach ($vehicleColors as $col)
                <option value="{{ $col }}">{{ $col }}</option>
              @endforeach
            </select>
            <div id="color-other-wrap" style="display:none;margin-top:8px;">
              <input class="ba-input" id="det-color-other" type="text" placeholder="Type custom color (e.g. Bronze)" maxlength="20"
                     oninput="this.value = this.value.replace(/[^A-Za-z\s-]/g, '').slice(0, 20); const ce = document.getElementById('color-err-msg'); if (ce) ce.hidden = true;">
            </div>
            <div class="ba-err-msg" id="color-err-msg" hidden style="margin-top:4px;font-size:12px;"></div>
          </div>
          <div class="ba-form-group">
            <label class="ba-label" for="det-type">Vehicle type</label>
            <select class="ba-input" id="det-type" name="type">
              <option value="">Select a type</option>
              @foreach ($vehicleTypes as $vt)
                <option value="{{ $vt }}">{{ $vt }}</option>
              @endforeach
            </select>
            <div class="ba-err-msg" id="type-err-msg" hidden style="margin-top:4px;font-size:12px;"></div>
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
          <!-- PWD Slot Verification Banner (Appears on top when an attendant clicks a PWD slot) -->
          <div id="pwd-verification-banner" style="display:none;background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#1E40AF;align-items:center;gap:12px;">
            <span style="font-size:24px;line-height:1;">♿</span>
            <div>
              <b style="font-size:14px;display:block;margin-bottom:2px;color:#1D4ED8;">
                Accessible (PWD) Bay Selected &mdash; <span id="pwd-banner-slot-code">Slot</span>
              </b>
              <span style="font-size:13px;color:#1E40AF;">
                Please visually verify that the vehicle displays an <strong>LTO PWD car sticker</strong> or the driver/passenger presents a valid <strong>PWD ID card</strong>.
              </span>
            </div>
          </div>

          <div style="margin-bottom:14px;">
            <h2 style="margin:0 0 4px;">Lot map</h2>
            <p style="font-size:13px;color:var(--muted);margin:0;">Only Available slots can be picked. Other slots are dimmed.</p>
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
      
      <div class="ba-detail-box" style="background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;color:var(--ink);">
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

      <!-- PWD Slot Visual Verification Reminder -->
      <div id="dlg-pwd-notice" style="display:none;background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:8px;padding:12px 14px;margin-bottom:14px;color:#1E40AF;font-size:13px;">
        <div style="display:flex;align-items:flex-start;gap:10px;">
          <span style="font-size:20px;line-height:1;">♿</span>
          <div>
            <b style="font-size:13.5px;display:block;margin-bottom:2px;color:#1D4ED8;">Accessible (PWD) Bay Selected</b>
            <span>Please visually verify that the vehicle displays an <strong>LTO PWD car sticker</strong> or the driver/passenger has shown a <strong>PWD ID card</strong>.</span>
          </div>
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
    
    <div class="ba-detail-box" style="background:var(--bg);border:1.5px dashed var(--line);border-radius:8px;padding:18px;margin-bottom:16px;font-family:'Courier New',Courier,monospace;color:var(--ink);">
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

// Validation Pattern matching PHP Vehicle::PLATE_PATTERN
const PLATE_REGEX = /^(?:[A-Z]{3}[0-9]{2,4}|[A-Z]{2}[0-9]{3,5}|[A-Z][0-9]{4,5}|[A-Z][0-9]{3,4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{4}[A-Z]{2}|[1-9]|1[0-7]|(?:SENATE|CONGRESS|GOV|PNP)[0-9]{1,4}|[0-9]{6,7}|[0-9]{15})$/;

const plateInput = document.getElementById('plate-input');
const plateErr = document.getElementById('plate-err');
const plateLiveIcon = document.getElementById('plate-live-icon');
const plateLiveFeedback = document.getElementById('plate-live-feedback');
const btnLookup = document.getElementById('btn-lookup');
const knownBox = document.getElementById('known-vehicle-box');
const guestBox = document.getElementById('guest-vehicle-box');
const knownBtns = document.getElementById('known-btns');
const guestBtns = document.getElementById('guest-btns');
const plateBtns = document.getElementById('plate-btns');

function checkPlateValidity(val) {
  const norm = (val || '').toUpperCase().replace(/[\s-]+/g, '');
  if (!norm) return { valid: false, empty: true, norm: '' };
  return { valid: PLATE_REGEX.test(norm), empty: false, norm };
}

function handlePlateLiveValidation() {
  // Format input: uppercase, allowed plate characters
  plateInput.value = plateInput.value.toUpperCase().replace(/[^A-Z0-9\s-]/g, '');
  plateErr.hidden = true;

  const result = checkPlateValidity(plateInput.value);

  if (result.empty) {
    plateInput.style.borderColor = 'var(--line)';
    plateInput.style.backgroundColor = '';
    plateLiveIcon.style.display = 'none';
    plateLiveFeedback.innerHTML = 'Philippine standard (ABC 1234, AB 12345), legacy, vintage, vanity, or protocol plates (e.g. 1 for President).';
    plateLiveFeedback.style.color = 'var(--muted)';
    return;
  }

  if (result.valid) {
    plateInput.style.borderColor = 'var(--ok)';
    plateInput.style.backgroundColor = '';
    plateLiveIcon.textContent = '✓';
    plateLiveIcon.style.color = 'var(--ok)';
    plateLiveIcon.style.fontWeight = '900';
    plateLiveIcon.style.display = 'block';
    plateLiveFeedback.innerHTML = '<span style="color:var(--ok);font-weight:900;">✓</span> Valid Philippine plate format';
    plateLiveFeedback.style.color = 'var(--ok)';
  } else {
    plateInput.style.borderColor = 'var(--paint)';
    plateInput.style.backgroundColor = '';
    plateLiveIcon.textContent = '•';
    plateLiveIcon.style.color = 'var(--paint)';
    plateLiveIcon.style.fontWeight = '900';
    plateLiveIcon.style.display = 'block';
    plateLiveFeedback.innerHTML = '<span style="color:var(--paint);font-weight:900;">●</span> Type standard (e.g. ABC 1234, AB 12345), vanity, or protocol (e.g. 1 to 17)';
    plateLiveFeedback.style.color = 'var(--paint)';
  }
}

plateInput.addEventListener('input', handlePlateLiveValidation);

// Lookup handler
async function doLookup() {
  const rawPlate = plateInput.value.trim();
  plateErr.hidden = true;

  const result = checkPlateValidity(rawPlate);
  if (result.empty || !result.valid) {
    plateInput.style.borderColor = 'var(--stop)';
    plateInput.style.backgroundColor = '';
    plateLiveIcon.textContent = '✕';
    plateLiveIcon.style.color = 'var(--stop)';
    plateLiveIcon.style.fontWeight = '900';
    plateLiveIcon.style.display = 'block';
    plateErr.textContent = 'Please enter a valid Philippine plate (e.g. ABC 1234, AB 12345, or 1).';
    plateErr.hidden = false;
    plateInput.focus();
    return;
  }

  try {
    const res = await fetch(lookupUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ plate: rawPlate })
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

    if (data.status === 'registered') {
      plateBtns.style.display = 'none';
      const v = data.vehicle;
      const cleanPhone = (v.phone || '').replace(/[\s-]+/g, '');
      vehicleState = { plate: v.plate, phone: cleanPhone, color: v.color, type: v.type, isRegistered: true, driverId: v.driver_id };
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
      // Guest vehicle: Skip redundant confirmation screen & go directly to Step 3: Vehicle details!
      vehicleState = { plate: data.plate, phone: '', color: '', type: '', isRegistered: false, driverId: null };
      goToDetails(false);
      document.getElementById('guest-details-banner').style.display = 'block';
      document.getElementById('lbl-guest-plate-title').textContent = data.plate;
      setTimeout(() => {
        document.getElementById('det-color').focus();
      }, 50);
    }
  } catch (e) {
    console.error(e);
  }
}

btnLookup.addEventListener('click', doLookup);
plateInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doLookup(); } });

// Auto-lookup if plate was passed via query parameter (e.g. from reserved mismatch lockout)
if (plateInput.value.trim() !== '') {
  handlePlateLiveValidation();
  doLookup();
}

// Search again reset
function resetLookup() {
  knownBox.style.display = 'none';
  guestBox.style.display = 'none';
  knownBtns.style.display = 'none';
  guestBtns.style.display = 'none';
  plateBtns.style.display = 'flex';
  handlePlateLiveValidation();
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
    document.getElementById('guest-details-banner').style.display = 'none';
    document.getElementById('det-phone').value = (vehicleState.phone || '').replace(/[\s-]+/g, '');
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
  resetLookup();
});


// Toggle Other color input field
const detColorSelect = document.getElementById('det-color');
const colorOtherWrap = document.getElementById('color-other-wrap');
const detColorOther = document.getElementById('det-color-other');

if (detColorSelect) {
  detColorSelect.addEventListener('change', () => {
    const ce = document.getElementById('color-err-msg');
    if (ce) ce.hidden = true;
    if (detColorSelect.value === 'Other') {
      colorOtherWrap.style.display = 'block';
      setTimeout(() => detColorOther.focus(), 50);
    } else {
      colorOtherWrap.style.display = 'none';
      detColorOther.value = '';
    }
  });
}

// Step 3 to Step 4: Slot Selection
function goToSlot() {
  const phoneErr = document.getElementById('phone-err-msg');
  const colorErr = document.getElementById('color-err-msg');
  const typeErr = document.getElementById('type-err-msg');

  if (phoneErr) phoneErr.hidden = true;
  if (colorErr) colorErr.hidden = true;
  if (typeErr) typeErr.hidden = true;

  const rawColor = document.getElementById('det-color').value;
  const type = document.getElementById('det-type').value;

  if (!type) {
    if (typeErr) {
      typeErr.textContent = 'Please select a vehicle type.';
      typeErr.hidden = false;
    }
    document.getElementById('det-type').focus();
    return;
  }

  if (!rawColor) {
    if (colorErr) {
      colorErr.textContent = 'Please choose a vehicle color.';
      colorErr.hidden = false;
    }
    document.getElementById('det-color').focus();
    return;
  }

  let finalColor = rawColor;
  if (rawColor === 'Other') {
    const customColor = (detColorOther.value || '').trim();
    if (!customColor) {
      if (colorErr) {
        colorErr.textContent = 'Please type the custom vehicle color.';
        colorErr.hidden = false;
      }
      detColorOther.focus();
      return;
    }
    finalColor = customColor;

    // Ensure form submits the actual custom color name
    let opt = Array.from(detColorSelect.options).find(o => o.value === finalColor);
    if (!opt) {
      opt = new Option(finalColor, finalColor, true, true);
      detColorSelect.add(opt);
    }
    detColorSelect.value = finalColor;
  }

  const phoneVal = (document.getElementById('det-phone').value || '').trim();
  if (phoneVal) {
    if (!/^09\d{9}$/.test(phoneVal)) {
      if (phoneErr) {
        phoneErr.textContent = 'Mobile number must be exactly 11 digits starting with 09 (e.g. 09171234567).';
        phoneErr.hidden = false;
      }
      document.getElementById('det-phone').focus();
      return;
    }
  }

  document.getElementById('det-phone').value = phoneVal;
  vehicleState.color = finalColor;
  vehicleState.type = type;
  vehicleState.phone = phoneVal;

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

  // Apply PWD highlight if checked on vehicle details
  const isPwd = document.getElementById('det-pwd').checked;
  applyPwdHighlight(isPwd);
}

function applyPwdHighlight(isPwd) {
  document.querySelectorAll('.ba-slot').forEach(s => {
    if (isPwd && s.dataset.type === 'PWD' && s.classList.contains('available')) {
      s.classList.add('pwd-highlight');
    } else {
      s.classList.remove('pwd-highlight');
    }
  });
}

document.getElementById('btn-to-slot').addEventListener('click', goToSlot);

document.getElementById('btn-back-to-details').addEventListener('click', () => {
  document.getElementById('section-slot').style.display = 'none';
  document.getElementById('section-details').style.display = 'block';
  document.getElementById('step-slot').classList.remove('active');
  document.getElementById('step-details').classList.add('active');

  const topPwdBanner = document.getElementById('pwd-verification-banner');
  if (topPwdBanner) topPwdBanner.style.display = 'none';

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

  const isPwdSlot = (selectedSlot.type === 'PWD');
  const pwdNotice = document.getElementById('dlg-pwd-notice');
  if (pwdNotice) pwdNotice.style.display = isPwdSlot ? 'block' : 'none';

  // Top banner verification notice
  const topPwdBanner = document.getElementById('pwd-verification-banner');
  if (topPwdBanner) {
    if (isPwdSlot) {
      topPwdBanner.style.display = 'flex';
      const slotLabel = document.getElementById('pwd-banner-slot-code');
      if (slotLabel) slotLabel.textContent = `Slot ${selectedSlot.code}`;
    } else {
      topPwdBanner.style.display = 'none';
    }
  }
};
</script>
@endpush
@endsection
