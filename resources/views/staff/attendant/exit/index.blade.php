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
          <button class="btn ghost" type="button" id="btn-open-exit-scanner" data-open="#scan-ticket-dlg" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M21 14v3"/><path d="M14 21h7"/></svg>
            Scan ticket QR
          </button>
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
<!-- Scan Ticket QR Code Modal -->
<dialog class="badlg" id="scan-ticket-dlg">
  <div class="in" style="background:#0F172A;color:#fff;">
    <button class="x" type="button" data-close aria-label="Close" style="color:#94A3B8;">&times;</button>
    <h2 style="color:#fff;margin-bottom:6px;">Scan ticket QR</h2>
    <p style="color:#94A3B8;font-size:13px;margin-bottom:14px;">Hold the printed ticket up to the camera or USB webcam.</p>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;">
      <label for="exit-cam-select" style="font-size:12px;color:#94A3B8;white-space:nowrap;">Camera:</label>
      <select id="exit-cam-select" class="ba-input sm" style="background:#1E293B;color:#fff;border-color:#334155;max-width:240px;font-size:12px;padding:4px 8px;min-height:32px;">
        <option value="">Detecting cameras...</option>
      </select>
    </div>

    <div id="exit-qr-reader" style="width:100%;max-width:320px;margin:0 auto;border-radius:8px;overflow:hidden;background:#000;min-height:220px;display:flex;align-items:center;justify-content:center;">
      <span style="font-size:12px;color:#64748B;">Starting camera feed...</span>
    </div>

    <p style="margin:12px 0 0;font-size:12px;color:#94A3B8;text-align:center;" id="exit-qr-status">
      Point camera at the ticket's QR code.
    </p>

    <div class="actions" style="margin-top:16px;">
      <button class="btn ghost" type="button" data-close style="color:#fff;border-color:#475569;">Cancel</button>
    </div>
  </div>
</dialog>

@push('scripts')
<script>
let exitQrScanner = null;

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

async function startExitCamera(deviceId = null) {
  const statusEl = document.getElementById('exit-qr-status');
  if (typeof Html5Qrcode === 'undefined') {
    if (statusEl) statusEl.textContent = 'Camera scanner library not loaded.';
    return;
  }

  try {
    if (exitQrScanner) {
      await exitQrScanner.stop().catch(() => {});
    }

    exitQrScanner = new Html5Qrcode('exit-qr-reader');

    const camSelect = document.getElementById('exit-cam-select');
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

    if (statusEl) statusEl.textContent = 'Camera active. Point at ticket QR code...';

    await exitQrScanner.start(
      targetSource,
      config,
      (decodedText) => {
        playScanBeep();
        if (statusEl) statusEl.textContent = `Scanned ${decodedText}! Searching...`;

        exitQrScanner.stop().catch(() => {});
        exitQrScanner = null;

        const dlg = document.getElementById('scan-ticket-dlg');
        if (dlg && dlg.close) dlg.close();

        // Populate search input and submit form
        const qInput = document.getElementById('exit-q');
        if (qInput) {
          qInput.value = decodedText.trim().toUpperCase();
          qInput.closest('form').submit();
        }
      },
      () => {}
    );
  } catch (err) {
    console.error('Exit camera error:', err);
    if (statusEl) {
      statusEl.textContent = 'Camera access blocked or not available.';
    }
  }
}

async function stopExitCamera() {
  if (exitQrScanner) {
    try {
      await exitQrScanner.stop();
    } catch (e) {}
    exitQrScanner = null;
  }
}

const btnOpenExitScanner = document.getElementById('btn-open-exit-scanner');
if (btnOpenExitScanner) {
  btnOpenExitScanner.addEventListener('click', () => {
    startExitCamera();
  });
}

const exitCamSelect = document.getElementById('exit-cam-select');
if (exitCamSelect) {
  exitCamSelect.addEventListener('change', (e) => {
    if (e.target.value) {
      startExitCamera(e.target.value);
    }
  });
}

const scanTicketDlg = document.getElementById('scan-ticket-dlg');
if (scanTicketDlg) {
  scanTicketDlg.addEventListener('close', () => {
    stopExitCamera();
  });
}
</script>
@endpush
@endsection
