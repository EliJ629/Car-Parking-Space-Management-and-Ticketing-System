@extends('layouts.attendant')

@section('title', 'Check in a reservation - Booth Attendant')
@section('tab', 'entry')

@section('content')
<h1 class="ba-page-title">New entry</h1>
<p class="ba-sub">Reserved driver. Scan the QR code or enter the code from the Bayline app.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Entry type</span>
  <span class="ba-step active" id="step-rsv-code">2. Reservation code</span>
  <span class="ba-step" id="step-rsv-verify">3. Verify</span>
  <span class="ba-step">4. Slot</span>
  <span class="ba-step">5. Ticket</span>
</div>

@if ($errors->any())
  <div style="background:#FEF2F2;border-left:5px solid #EF4444;border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px;color:#991B1B;">
    <b style="font-size:15px;display:block;margin-bottom:4px;">Cannot check in reservation &mdash; please check details:</b>
    <ul style="margin:0;padding-left:18px;font-size:13px;">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form id="checkin-form" method="POST" action="{{ route('attendant.entry.reserved.check-in') }}">
  @csrf
  <input type="hidden" name="reservation_id" id="hidden-rsv-id" value="">
  <input type="hidden" name="entered_plate" id="hidden-rsv-plate" value="">

  <!-- STEP 2: Code Entry & Camera Scan (BA-24, BA-25, BA-26) -->
  <div id="section-rsv-lookup">
    <div style="display:grid;grid-template-columns:1.8fr 1fr;gap:24px;align-items:start;">
      
      <div class="ba-card">
        <!-- Switch tabs between Scan QR and Enter code -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
          <button class="btn sm ghost" type="button" id="tab-scan">Scan QR code</button>
          <button class="btn sm primary" type="button" id="tab-manual">Enter code</button>
        </div>

        <!-- Camera / Viewfinder Box (BA-24) -->
        <div id="viewfinder-box" style="display:none;background:#0F172A;border-radius:10px;padding:20px 16px;text-align:center;color:#fff;margin-bottom:20px;position:relative;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;">
            <label for="camera-select" style="font-size:12px;color:#94A3B8;white-space:nowrap;">Camera:</label>
            <select id="camera-select" class="ba-input sm" style="background:#1E293B;color:#fff;border-color:#334155;max-width:240px;font-size:12px;padding:4px 8px;min-height:32px;">
              <option value="">Detecting cameras...</option>
            </select>
            <button class="btn sm ghost" type="button" id="btn-stop-cam" style="color:#fff;border-color:#475569;padding:0 8px;min-height:30px;font-size:11px;">Pause</button>
          </div>

          <div id="qr-reader" style="width:100%;max-width:320px;margin:0 auto;border-radius:8px;overflow:hidden;background:#000;min-height:220px;display:flex;align-items:center;justify-content:center;">
            <span style="font-size:12px;color:#64748B;">Starting camera feed...</span>
          </div>

          <p style="margin:12px 0 0;font-size:12px;color:#94A3B8;" id="qr-status-msg">
            Point camera at the driver's reservation QR code.
          </p>
        </div>

        <!-- Manual Code Input (BA-25) -->
        <div id="manual-code-box">
          <div class="ba-form-group">
            <label class="ba-label" for="rsv-code-input">Reservation code</label>
            <input class="ba-input" id="rsv-code-input" type="text" placeholder="RSV-4F7K29"
                   value="{{ $code }}"
                   style="font-size:24px;font-weight:800;letter-spacing:1px;text-transform:uppercase;" autocomplete="off" autofocus>
            <p style="font-size:12px;color:var(--muted);margin-top:4px;">RSV, a dash and 6 letters or numbers. Not case sensitive.</p>
            <div class="ba-err-msg" id="rsv-err" hidden></div>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:10px;">
          <button class="btn primary" type="button" id="btn-find-rsv">Find reservation</button>
          <a class="btn ghost" href="{{ route('attendant.entry.index') }}">Back</a>
        </div>
      </div>

      <!-- Right Box: Reservation Rules (BA-25) -->
      <div class="ba-card">
        <h2>Reservation rules</h2>
        <div style="font-size:14px;margin-bottom:14px;">
          <b style="display:block;">Grace period</b>
          <span style="color:var(--muted);">{{ $graceMinutes }} minutes after the scheduled time.</span>
        </div>
        <div style="font-size:14px;margin-bottom:14px;">
          <b style="display:block;">Early check-in</b>
          <span style="color:var(--muted);">Opens {{ $earlyMinutes }} minutes before the scheduled time.</span>
        </div>
        <div style="font-size:14px;">
          <b style="display:block;">Payment</b>
          <span style="color:var(--muted);">Reservations are free. The driver pays at exit.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- STEP 3: Verify Checklist (BA-27) -->
  <div id="section-rsv-verify" style="display:none;">
    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:24px;align-items:start;">
      
      <!-- Left: Reservation & Vehicle Cards -->
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="ba-card">
          <h2>Reservation</h2>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Code</span>
              <b id="v-code"></b>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Status</span>
              <span class="badge" style="background:#DCFCE7;color:#166534;font-size:11px;">CONFIRMED</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Driver</span>
              <b id="v-driver"></b>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Scheduled</span>
              <b id="v-sched"></b>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Check-in window</span>
              <b id="v-window"></b>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:var(--muted);">Reserved slot</span>
              <b id="v-slot" style="color:var(--paint-deep);"></b>
            </div>
          </div>
        </div>

        <div class="ba-card">
          <h2>Registered vehicle</h2>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Plate</span>
              <b id="v-plate"></b>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
              <span style="color:var(--muted);">Color</span>
              <b id="v-color"></b>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:var(--muted);">Type</span>
              <b id="v-type"></b>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Verify Checklist -->
      <div class="ba-card">
        <h2>Verify before check-in</h2>
        
        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;font-size:14px;">
          <label style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" checked disabled style="width:18px;height:18px;accent-color:var(--ok);">
            Code is valid and the reservation is Confirmed
          </label>
          <label style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" checked disabled style="width:18px;height:18px;accent-color:var(--ok);">
            Arrival is inside the check-in window
          </label>
          <label style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" id="chk-veh-match" checked style="width:18px;height:18px;accent-color:var(--paint-deep);">
            Color and type match the vehicle at the booth
          </label>
          <label style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" id="chk-plate-match" checked style="width:18px;height:18px;accent-color:var(--paint-deep);">
            Plate on the vehicle matches the reservation
          </label>
        </div>

        <div class="ba-form-group">
          <label class="ba-label" for="plate-seen-input">Plate seen on the vehicle</label>
          <input class="ba-input" id="plate-seen-input" type="text" style="font-weight:800;letter-spacing:1px;text-transform:uppercase;">
          <div class="ba-err-msg" id="plate-mismatch-msg" hidden>Does not match reservation plate</div>
        </div>

        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px;margin-bottom:20px;font-size:13px;">
          <b>Payment at exit</b><br>
          Reservations are free. The driver pays only the remaining balance when leaving.
        </div>

        <div style="display:flex;gap:10px;">
          <button class="btn primary" type="button" id="btn-open-confirm-checkin" data-open="#confirm-checkin-dlg">Confirm check-in</button>
          <button class="btn ghost" type="button" id="btn-trigger-mismatch">Plate does not match</button>
        </div>
      </div>
    </div>
  </div>

  <!-- POP-UP BA-32: Confirm Check-in Modal -->
  <dialog class="badlg" id="confirm-checkin-dlg">
    <div class="in">
      <button class="x" type="button" data-close aria-label="Close">&times;</button>
      <h2>Confirm check-in</h2>

      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Reservation</span>
          <b id="dlg-rsv-code"></b>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Driver</span>
          <b id="dlg-rsv-driver"></b>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="color:var(--muted);">Plate</span>
          <b id="dlg-rsv-plate"></b>
        </div>
      </div>

      <div class="ba-form-group">
        <label class="ba-label">Assigned slot</label>
        <input class="ba-input" id="dlg-rsv-slot" type="text" readonly style="font-weight:700;">
        <p style="font-size:12px;color:var(--muted);margin-top:4px;">Pre-assigned when the driver booked.</p>
      </div>

      <div style="background:var(--warn-bg);border:1px solid var(--paint-deep);border-radius:6px;padding:12px;font-size:13px;color:var(--ink);margin-bottom:18px;">
        <b>Slot cannot be changed</b><br>
        The attendant can change the slot only when this slot is Out of service.
      </div>

      <div class="actions">
        <button class="btn ghost" type="button" data-close>Back</button>
        <button class="btn primary" type="submit">Confirm and create ticket</button>
      </div>
    </div>
  </dialog>
</form>

<!-- POP-UP BA-28: Plate Does Not Match -->
<dialog class="badlg {{ (session('error_modal') && session('error_modal')['type'] === 'plate_mismatch') ? 'auto-open' : '' }}" id="plate-mismatch-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Plate does not match</h2>

    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="color:var(--muted);">Reservation plate</span>
        <b id="mismatch-exp">{{ session('error_modal')['expected'] ?? '' }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--muted);">Plate you entered</span>
        <b id="mismatch-got" style="color:var(--stop);">{{ session('error_modal')['entered'] ?? '' }}</b>
      </div>
    </div>

    <p>Do not check in this vehicle. Ask the driver to show the registration or the app profile. A reservation cannot be moved to another plate at the booth.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel check-in</button>
      <button class="btn primary" type="button" data-close onclick="document.getElementById('plate-seen-input').focus()">Check plate again</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-29: Reservation Expired -->
<dialog class="badlg" id="rsv-expired-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Reservation expired</h2>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Code</span><b id="exp-code"></b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Driver</span><b id="exp-driver"></b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Scheduled</span><b id="exp-sched"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Grace ended</span><b id="exp-grace"></b></div>
    </div>

    <p>The QR code and the code are no longer valid. Slot was released and the driver was notified.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Back</button>
      <a class="btn primary" href="{{ route('attendant.entry.walk-in') }}">Start walk-in entry</a>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-30: Reservation Invalid or Canceled -->
<dialog class="badlg" id="rsv-canceled-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Reservation not valid</h2>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Code</span><b id="canc-code"></b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Status</span><b style="color:var(--stop);" id="canc-st"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Details</span><span id="canc-by"></span></div>
    </div>

    <p>A canceled or unknown reservation cannot create a ticket. The driver can still enter as a walk-in if a slot is Available.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Back</button>
      <a class="btn primary" href="{{ route('attendant.entry.walk-in') }}">Start walk-in entry</a>
    </div>
  </div>
</dialog>

<!-- Not approved yet, or turned down -->
<dialog class="badlg" id="rsv-appr-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 id="appr-title">Reservation is not approved yet</h2>
    <p id="appr-msg"></p>
    <div class="actions">
      <button class="btn ghost" type="button" data-close>Close</button>
      <a class="btn primary" id="appr-link" href="{{ route('attendant.reservations.index') }}">Open reservations</a>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-31: Too Early to Check In -->
<dialog class="badlg" id="rsv-early-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Too early to check in</h2>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Driver</span><b id="early-driver"></b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Scheduled</span><b id="early-sched"></b></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--muted);">Check-in opens</span><b id="early-opens" style="color:var(--paint-deep);"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Slot</span><b id="early-slot"></b></div>
    </div>

    <p>Check-in opens 30 minutes before the scheduled time. Slot stays reserved for this driver.</p>

    <div class="actions">
      <button class="btn primary" type="button" data-close>OK</button>
    </div>
  </div>
</dialog>

@push('scripts')
<script>
let activeReservation = null;
let html5QrScanner = null;

const rsvLookupUrl = "{{ route('attendant.entry.reserved.lookup') }}";
const rsvCsrf = "{{ csrf_token() }}";

function playScanBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.2, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
  } catch (e) {}
}

async function startCamera(deviceId = null) {
  const statusEl = document.getElementById('qr-status-msg');
  if (typeof Html5Qrcode === 'undefined') {
    if (statusEl) statusEl.textContent = 'Camera scanner library not loaded.';
    return;
  }

  try {
    if (html5QrScanner) {
      await html5QrScanner.stop().catch(() => {});
    }

    html5QrScanner = new Html5Qrcode('qr-reader');

    const camSelect = document.getElementById('camera-select');
    const cameras = await Html5Qrcode.getCameras().catch(() => []);
    if (camSelect && cameras && cameras.length > 0) {
      camSelect.innerHTML = '';
      cameras.forEach((cam, idx) => {
        const opt = document.createElement('option');
        opt.value = cam.id;
        opt.textContent = cam.label || `Camera ${idx + 1}`;
        camSelect.appendChild(opt);
      });
      if (deviceId) {
        camSelect.value = deviceId;
      }
    }

    const config = {
      fps: 10,
      qrbox: { width: 220, height: 220 },
      aspectRatio: 1.0
    };

    const targetSource = deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' };

    if (statusEl) statusEl.textContent = 'Camera active. Point at reservation QR code...';

    await html5QrScanner.start(
      targetSource,
      config,
      (decodedText) => {
        playScanBeep();
        if (statusEl) statusEl.textContent = `Scanned: ${decodedText}! Finding reservation...`;

        // Stop camera after successful detection
        html5QrScanner.stop().catch(() => {});
        html5QrScanner = null;

        // Switch to code view and execute lookup
        document.getElementById('rsv-code-input').value = decodedText.trim().toUpperCase();
        document.getElementById('viewfinder-box').style.display = 'none';
        document.getElementById('manual-code-box').style.display = 'block';
        document.getElementById('tab-manual').classList.replace('ghost', 'primary');
        document.getElementById('tab-scan').classList.replace('primary', 'ghost');

        doRsvLookup();
      },
      () => {}
    );
  } catch (err) {
    console.error('Camera error:', err);
    if (statusEl) {
      statusEl.textContent = 'Camera access blocked or not available. You can type the code below.';
    }
  }
}

async function stopCamera() {
  if (html5QrScanner) {
    try {
      await html5QrScanner.stop();
    } catch (e) {}
    html5QrScanner = null;
  }
}

// Toggle between Scan and Manual
document.getElementById('tab-scan').addEventListener('click', () => {
  document.getElementById('viewfinder-box').style.display = 'block';
  document.getElementById('manual-code-box').style.display = 'none';
  document.getElementById('tab-scan').classList.replace('ghost', 'primary');
  document.getElementById('tab-manual').classList.replace('primary', 'ghost');
  startCamera();
});

document.getElementById('tab-manual').addEventListener('click', () => {
  stopCamera();
  document.getElementById('viewfinder-box').style.display = 'none';
  document.getElementById('manual-code-box').style.display = 'block';
  document.getElementById('tab-manual').classList.replace('ghost', 'primary');
  document.getElementById('tab-scan').classList.replace('primary', 'ghost');
});

document.getElementById('btn-stop-cam').addEventListener('click', () => {
  stopCamera();
  const statusEl = document.getElementById('qr-status-msg');
  if (statusEl) statusEl.textContent = 'Camera paused.';
});

const camSelect = document.getElementById('camera-select');
if (camSelect) {
  camSelect.addEventListener('change', (e) => {
    if (e.target.value) {
      startCamera(e.target.value);
    }
  });
}

// Lookup Reservation
async function doRsvLookup() {
  const code = document.getElementById('rsv-code-input').value.trim();
  const errEl = document.getElementById('rsv-err');
  errEl.hidden = true;

  if (!code) {
    errEl.textContent = 'Enter the reservation code.';
    errEl.hidden = false;
    return;
  }

  try {
    const res = await fetch(rsvLookupUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': rsvCsrf, 'Accept': 'application/json' },
      body: JSON.stringify({ code })
    });
    const data = await res.json();

    if (data.status === 'not_found' || data.status === 'invalid_format') {
      errEl.textContent = data.message;
      errEl.hidden = false;
      return;
    }

    if (data.status === 'expired') {
      document.getElementById('exp-code').textContent = data.reservation.code;
      document.getElementById('exp-driver').textContent = `${data.reservation.driver}, ${data.reservation.plate}`;
      document.getElementById('exp-sched').textContent = data.reservation.scheduled;
      document.getElementById('exp-grace').textContent = data.reservation.grace_ended;
      document.getElementById('rsv-expired-dlg').showModal();
      return;
    }

    if (data.status === 'canceled') {
      document.getElementById('canc-code').textContent = data.reservation.code;
      document.getElementById('canc-st').textContent = data.reservation.status;
      document.getElementById('canc-by').textContent = data.reservation.canceled_by;
      document.getElementById('rsv-canceled-dlg').showModal();
      return;
    }

    if (data.status === 'too_early') {
      document.getElementById('early-driver').textContent = `${data.reservation.driver}, ${data.reservation.plate}`;
      document.getElementById('early-sched').textContent = data.reservation.scheduled;
      document.getElementById('early-opens').textContent = data.reservation.check_in_opens;
      document.getElementById('early-slot').textContent = `${data.reservation.slot}, held`;
      document.getElementById('rsv-early-dlg').showModal();
      return;
    }

    if (data.status === 'confirmed') {
      activeReservation = data.reservation;
      showVerifyScreen(data.reservation);
    }
  } catch (e) {
    console.error(e);
  }
}

document.getElementById('btn-find-rsv').addEventListener('click', doRsvLookup);
document.getElementById('rsv-code-input').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); doRsvLookup(); }
});

// Auto-lookup if code prefilled
if (document.getElementById('rsv-code-input').value.trim() !== '') {
  doRsvLookup();
}

function showVerifyScreen(r) {
  document.getElementById('section-rsv-lookup').style.display = 'none';
  document.getElementById('section-rsv-verify').style.display = 'block';
  document.getElementById('step-rsv-code').classList.remove('active');
  document.getElementById('step-rsv-verify').classList.add('active');

  // Fill details
  document.getElementById('hidden-rsv-id').value = r.id;
  document.getElementById('v-code').textContent = r.code;
  document.getElementById('v-driver').textContent = `${r.driver}, ${r.phone}`;
  document.getElementById('v-sched').textContent = r.scheduled;
  document.getElementById('v-window').textContent = r.check_in_window;
  document.getElementById('v-slot').textContent = `${r.slot_code}, ${r.slot_zone}, ${r.slot_type}`;

  document.getElementById('v-plate').textContent = r.plate;
  document.getElementById('v-color').textContent = r.color;
  document.getElementById('v-type').textContent = r.type;

  const plateSeen = document.getElementById('plate-seen-input');
  plateSeen.value = r.plate;
  document.getElementById('hidden-rsv-plate').value = r.plate;

  // Fill confirm modal
  document.getElementById('dlg-rsv-code').textContent = r.code;
  document.getElementById('dlg-rsv-driver').textContent = r.driver;
  document.getElementById('dlg-rsv-plate').textContent = `${r.plate}, ${r.color} ${r.type}`;
  document.getElementById('dlg-rsv-slot').value = `${r.slot_code}, ${r.slot_zone}, ${r.slot_type}`;
}

// Plate seen change
document.getElementById('plate-seen-input').addEventListener('input', (e) => {
  document.getElementById('hidden-rsv-plate').value = e.target.value;
  const match = e.target.value.replace(/[\s-]+/g, '').toUpperCase() === activeReservation.plate_raw.replace(/[\s-]+/g, '').toUpperCase();
  document.getElementById('plate-mismatch-msg').hidden = match;
  document.getElementById('btn-open-confirm-checkin').disabled = !match;
});

// Trigger Plate Does Not Match Modal (BA-28)
document.getElementById('btn-trigger-mismatch').addEventListener('click', () => {
  const entered = document.getElementById('plate-seen-input').value;
  document.getElementById('mismatch-exp').textContent = activeReservation.plate;
  document.getElementById('mismatch-got').textContent = entered || 'None';
  document.getElementById('plate-mismatch-dlg').showModal();
});
</script>
@endpush
@endsection
