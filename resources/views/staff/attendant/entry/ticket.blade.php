@extends('layouts.attendant')

@section('title', 'Ticket generated - Booth Attendant')
@section('tab', 'entry')

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
  <h1 class="ba-page-title" style="margin:0;display:flex;align-items:center;gap:12px;">
    <span style="background:#15803D;color:#fff;border-radius:50%;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;">✓</span>
    Ticket generated &mdash; <span style="font-family:'Courier New',Courier,monospace;letter-spacing:1px;">{{ $session->ticket_number }}</span>
  </h1>
  <a class="btn ghost sm guard-unprinted-ticket" href="{{ route('attendant.entry.index') }}">+ Start next entry</a>
</div>
<p class="ba-sub">Entry complete. Hand the printed thermal ticket to the driver before the vehicle moves into slot <b>{{ $session->slot_code }}</b>.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Entry type</span>
  <span class="ba-step">2. {{ $isReserved ? 'Reservation code' : 'Plate' }}</span>
  <span class="ba-step">3. {{ $isReserved ? 'Verify' : 'Vehicle details' }}</span>
  <span class="ba-step">4. Slot</span>
  <span class="ba-step active" style="font-weight:700;">5. Ticket generated ✓</span>
</div>

<!-- Success Banner (BA-18, BA-33) -->
<div style="background:#F0FDF4;border-left:5px solid #22C55E;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;">
  <b style="color:#15803D;font-size:16px;display:block;">Ticket {{ $session->ticket_number }} successfully created!</b>
  <p style="color:#166534;margin:4px 0 0;font-size:14px;">
    @if ($isReserved)
      Reservation {{ $session->reservation->code }} is now Checked in. Slot {{ $session->slot_code }} is Occupied. The Bayline app shows the ticket and the slot.
    @else
      Slot {{ $session->slot_code }} is now Occupied. If the driver has a Bayline account, the app already shows the slot and the session.
    @endif
  </p>
</div>

<!-- Two Columns Layout (BA-18, BA-33) -->
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Summary -->
  <div class="ba-card">
    <h2>{{ $isReserved ? 'Check-in summary' : 'Entry summary' }}</h2>

    <div style="display:flex;flex-direction:column;gap:12px;font-size:14px;margin-bottom:24px;">
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Ticket</span>
        <b>{{ $session->ticket_number }}</b>
      </div>
      @if ($isReserved)
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
          <span style="color:var(--muted);">Reservation</span>
          <span><b>{{ $session->reservation->code }}</b> <span class="badge" style="background:#DCFCE7;color:#166534;font-size:11px;margin-left:4px;">CHECKED IN</span></span>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
          <span style="color:var(--muted);">Driver</span>
          <b>{{ $session->driver?->name ?? 'Reserved Driver' }}</b>
        </div>
      @endif
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Plate</span>
        <b>{{ \App\Support\Lot::plate($session->plate_number) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Vehicle</span>
        <b>{{ $session->vehicle_color }} {{ $session->vehicle_type }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Slot</span>
        <b>{{ $session->slot_code }}, {{ $session->slot?->zone }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Entry</span>
        <b>{{ \App\Support\Lot::stamp($session->entered_at) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <span style="color:var(--muted);">Entered by</span>
        <b>{{ $session->attendant?->name ?? auth()->user()->name }}</b>
      </div>
      @if ($isReserved)
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:10px;">
          <span style="color:var(--muted);">Fee rule</span>
          <b>Remaining balance at exit</b>
        </div>
        <p style="font-size:13px;color:var(--muted);margin:4px 0 0;">
          Nothing was collected at the booth. At exit the driver pays the parking fee minus anything already paid (₱0.00 for a free reservation).
        </p>
      @endif
    </div>

    <div style="display:flex;gap:12px;">
      <button class="btn primary" type="button" data-open="#print-preview-dlg" id="btn-open-ticket-print">Print ticket</button>
      <a class="btn ghost guard-unprinted-ticket" href="{{ route('attendant.entry.index') }}">New entry</a>
    </div>
  </div>

  <!-- Right Column: Thermal Ticket Preview -->
  <div>
    <div class="ba-thermal">
      <h4>{{ \App\Support\Lot::lotName() }}</h4>
      <p class="subt">{{ \App\Support\Lot::lotAddress() }}</p>
      <p class="subt" style="margin-top:2px;font-weight:700;">Parking ticket</p>
      <div class="t-div"></div>
      <div class="t-num-label">Ticket number</div>
      <div class="t-num">
        {{ $session->ticket_number }}
      </div>
      <div class="t-row">
        <span>Plate</span>
        <b>{{ \App\Support\Lot::plate($session->plate_number) }}</b>
      </div>
      <div class="t-row">
        <span>Slot</span>
        <b>{{ $session->slot_code }}</b>
      </div>
      <div class="t-row">
        <span>Entry</span>
        <b>{{ \App\Support\Lot::stamp($session->entered_at) }}</b>
      </div>
      <div class="t-div"></div>
      <p class="foot">
        Keep this ticket. Pay at the booth before the exit gate. Lost ticket fee ₱100.
      </p>
    </div>
  </div>
</div>

<!-- POP-UP BA-19: Print Ticket Preview Modal -->
<dialog class="badlg" id="print-preview-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Print preview</h2>
    <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:-6px;">Booth thermal printer, 58 mm paper</p>

    <div class="ba-thermal" id="ticket-print-slip" style="box-shadow:none;border:1px solid #CBD5E1;width:100%;max-width:260px;">
      <h4>{{ \App\Support\Lot::lotName() }}</h4>
      <p class="subt">{{ \App\Support\Lot::lotAddress() }}</p>
      <p class="subt" style="margin-top:2px;font-weight:700;">Parking ticket</p>
      <div class="t-div"></div>
      <div class="t-num-label">Ticket number</div>
      <div class="t-num">
        {{ $session->ticket_number }}
      </div>
      <div class="t-row"><span>Plate</span><b>{{ \App\Support\Lot::plate($session->plate_number) }}</b></div>
      <div class="t-row"><span>Slot</span><b>{{ $session->slot_code }}</b></div>
      <div class="t-row"><span>Entry</span><b>{{ \App\Support\Lot::stamp($session->entered_at) }}</b></div>
      <div class="t-div"></div>
      <p class="foot">Keep this ticket. Pay at the booth before the exit gate. Lost ticket fee ₱100.</p>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-do-print-ticket" onclick="markTicketPrinted(); window.baPrint('#ticket-print-slip');">Print</button>
    </div>
  </div>
</dialog>

<!-- Unprinted Ticket Warning Modal (Option A) -->
<dialog class="badlg" id="unprinted-ticket-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
      <span style="font-size:26px;">⚠️</span>
      <h2 style="margin:0;">Ticket not printed</h2>
    </div>
    <p>
      The thermal ticket for slot <b>{{ $session->slot_code }}</b> (Ticket <b>{{ $session->ticket_number }}</b>) has not been printed yet.
    </p>
    <div style="font-size:13px;color:var(--ink);background:var(--bg);padding:12px 14px;border-radius:6px;border-left:3px solid var(--paint);margin-bottom:16px;">
      Drivers need their physical ticket to verify their assigned slot and present it to the booth upon exit.
    </div>
    <div class="actions" style="justify-content:flex-end;">
      <a class="btn ghost" id="unprinted-ticket-proceed-btn" href="{{ route('attendant.entry.index') }}">Proceed without printing</a>
      <button class="btn primary" type="button" onclick="document.getElementById('unprinted-ticket-dlg').close(); document.getElementById('print-preview-dlg').showModal();">Print ticket now</button>
    </div>
  </div>
</dialog>

<script>
  let hasPrintedTicket = false;
  let pendingTicketUrl = '{{ route('attendant.entry.index') }}';

  function markTicketPrinted() {
    hasPrintedTicket = true;
    const btn = document.getElementById('btn-open-ticket-print');
    if (btn) {
      btn.innerHTML = 'Reprint ticket ✓';
      btn.classList.remove('primary');
      btn.classList.add('ghost');
    }
  }

  function setupTicketNavigationGuard() {
    const selector = '.guard-unprinted-ticket, .ba-tabs a, header.ba-head a, a.btn';
    document.querySelectorAll(selector).forEach(el => {
      const href = el.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

      el.addEventListener('click', (e) => {
        if (!hasPrintedTicket) {
          e.preventDefault();
          pendingTicketUrl = el.href;
          const proceedBtn = document.getElementById('unprinted-ticket-proceed-btn');
          if (proceedBtn) {
            proceedBtn.href = pendingTicketUrl;
          }
          const dlg = document.getElementById('unprinted-ticket-dlg');
          if (dlg && typeof dlg.showModal === 'function') {
            dlg.showModal();
          }
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', setupTicketNavigationGuard);
  setupTicketNavigationGuard();
</script>
@endsection
