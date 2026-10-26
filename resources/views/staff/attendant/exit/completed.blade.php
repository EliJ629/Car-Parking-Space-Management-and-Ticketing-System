@extends('layouts.attendant')

@section('title', 'Exit completed - Booth Attendant')
@section('tab', 'exit')

@section('content')
<h1 class="ba-page-title">Exit and payment</h1>
<p class="ba-sub">This session is closed. It is shown for reference.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Search</span>
  <span class="ba-step">2. Fee</span>
  <span class="ba-step">3. Payment</span>
  <span class="ba-step">4. Receipt</span>
  <span class="ba-step active">5. Guard approval</span>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1.4fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Session Card (BA-49) -->
  <div class="ba-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <h2 style="margin:0;">Parking session</h2>
      <span class="badge completed">COMPLETED</span>
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
        <b>{{ \App\Support\Lot::stamp($session->exited_at) }}</b>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--muted);">Duration</span>
        <b>{{ \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($session->entered_at, $session->exited_at)) }}</b>
      </div>
    </div>
  </div>

  <!-- Right Column: Exit Completed & Progress (BA-49) -->
  <div>
    <div class="ba-card" style="margin-bottom:20px;">
      <h2 style="color:var(--ok);">Exit completed</h2>
      <p style="color:var(--muted);font-size:14px;">The Guard approved the exit. The session is closed and slot {{ $session->slot_code }} is Available.</p>

      <div class="ba-detail-box" style="padding:16px;background:var(--bg);border:1px solid var(--line);border-radius:8px;margin-bottom:18px;color:var(--ink);">
        <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px;">
          <span>Paid in cash</span>
          <b style="font-size:22px;font-family:var(--f-d);">{{ \App\Support\Lot::peso($session->payment?->total) }}</b>
        </div>
      </div>

      <!-- Full Progress Pipeline -->
      <h3>Progress</h3>
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
            <small style="color:var(--muted);display:block;">{{ \App\Support\Lot::time($session->payment?->paid_at) }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Guard approved, Good to Go</b>
            <small style="color:var(--muted);display:block;">{{ $session->exitApprover?->name ?? 'Security Guard' }}, {{ \App\Support\Lot::time($session->exited_at) }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Slot {{ $session->slot_code }} Available</b>
            <small style="color:var(--muted);display:block;">{{ \App\Support\Lot::time($session->exited_at) }}</small>
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
@endsection
