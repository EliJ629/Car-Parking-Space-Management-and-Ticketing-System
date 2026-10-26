@extends('layouts.attendant')

@section('title', 'Paid, waiting for the Guard - Booth Attendant')
@section('tab', 'exit')

@section('content')
<h1 class="ba-page-title">Exit and payment</h1>
<p class="ba-sub">This vehicle already paid. No payment is taken again unless the exit window ends.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Search</span>
  <span class="ba-step">2. Fee</span>
  <span class="ba-step">3. Payment</span>
  <span class="ba-step">4. Receipt</span>
  <span class="ba-step active">5. Guard approval</span>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1.4fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Session Card (BA-48) -->
  <div class="ba-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <h2 style="margin:0;">Parking session</h2>
      <span class="badge pending">PENDING EXIT</span>
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
        <b>{{ $session->slot_code }}, {{ $session->slot?->zone }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Entry</span>
        <b>{{ \App\Support\Lot::stamp($session->entered_at) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
        <span style="color:var(--muted);">Exit time</span>
        <b>{{ \App\Support\Lot::time($session->payment?->paid_at) }} (paid)</b>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--muted);">Duration</span>
        <b>{{ \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($session->entered_at, $session->payment?->paid_at)) }}</b>
      </div>
    </div>
  </div>

  <!-- Right Column: Status & Progress Pipeline (BA-48) -->
  <div>
    <div class="ba-card" style="margin-bottom:20px;">
      <h2 style="color:var(--paint-deep);">Paid and waiting for the Guard</h2>
      <p style="color:var(--muted);font-size:14px;">Slot {{ $session->slot_code }} stays Pending exit and cannot be given to another vehicle.</p>

      <div class="ba-detail-box" style="padding:14px;background:var(--bg);border:1px solid var(--line);border-radius:8px;margin-bottom:18px;color:var(--ink);">
        <div style="display:flex;justify-content:space-between;font-size:14px;">
          <span>Paid in cash</span>
          <b style="font-size:20px;font-family:var(--f-d);">{{ \App\Support\Lot::peso($session->payment?->total) }}</b>
        </div>
      </div>

      <!-- Progress -->
      <h3 style="margin-top:20px;">Progress</h3>
      <div style="display:flex;flex-direction:column;gap:14px;margin-top:12px;font-size:14px;">
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Payment received</b>
            <small style="color:var(--muted);display:block;">{{ \App\Support\Lot::time($session->payment?->paid_at) }}, receipt {{ $session->payment?->receipt_number }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Sent to the Guard</b>
            <small style="color:var(--muted);display:block;">Gate validation queue</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--paint-deep);"></div>
          <div>
            <b>Guard taps Good to Go</b>
            <small style="color:var(--muted);display:block;">Waiting. Exit window ends {{ $windowEndsAt }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;opacity:.5;">
          <div style="width:12px;height:12px;border-radius:99px;background:#CBD5E1;"></div>
          <div>
            <b>Slot {{ $session->slot_code }} Available</b>
            <small style="color:var(--muted);display:block;">After approval</small>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:24px;">
        <a class="btn ghost" href="{{ route('attendant.exit.index') }}">Back to search</a>
        @if ($session->payment)
          <a class="btn primary" href="{{ route('attendant.exit.receipt', $session->payment) }}">Reprint receipt</a>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- POP-UP BA-50: Exit Window Expired Modal -->
@if ($isExpired && $recalc)
<dialog class="badlg auto-open" id="exit-expired-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 style="color:var(--stop);">Exit window ended</h2>
    <p>{{ $session->ticket_number }} paid at {{ \App\Support\Lot::time($session->payment?->paid_at) }}. The 15 minute exit window ended at {{ $windowEndsAt }} and the vehicle is still in {{ $session->slot_code }}.</p>

    <div class="ba-detail-box" style="background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:16px;font-size:14px;color:var(--ink);">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:var(--muted);">Stay now</span>
        <b>{{ \App\Support\Lot::duration($recalc['minutes']) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:var(--muted);">Fee for this stay</span>
        <b>{{ \App\Support\Lot::peso($recalc['parking_charge']) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:var(--ok);">
        <span>Already paid</span>
        <b>-{{ \App\Support\Lot::peso($recalc['already_paid']) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:var(--muted);">Overstay penalty</span>
        <b>{{ \App\Support\Lot::peso($recalc['overstay_penalty']) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:8px;margin-top:8px;">
        <b style="font-size:16px;">Amount to collect</b>
        <b style="font-size:22px;color:var(--stop);font-family:var(--f-d);">{{ \App\Support\Lot::peso($recalc['amount_to_collect']) }}</b>
      </div>
    </div>

    <p style="font-size:12px;color:var(--muted);">The penalty can be waived with manager password approval at checkout.</p>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Close</button>
      <form method="POST" action="{{ route('attendant.exit.pay', $session) }}">
        @csrf
        <input type="hidden" name="cash_received" value="{{ $recalc['amount_to_collect'] }}">
        <input type="hidden" name="overstay" value="1">
        <button class="btn primary" type="submit">Take extra payment</button>
      </form>
    </div>
  </div>
</dialog>
@endif
@endsection
