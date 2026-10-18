@extends('layouts.attendant')

@section('title', 'Payment success and receipt - Booth Attendant')
@section('tab', 'exit')

@section('content')
<h1 class="ba-page-title">Exit and payment</h1>
<p class="ba-sub">Give the printed receipt to the driver.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step">1. Search</span>
  <span class="ba-step">2. Fee</span>
  <span class="ba-step">3. Payment</span>
  <span class="ba-step active">4. Receipt</span>
  <span class="ba-step">5. Guard approval</span>
</div>

<!-- Success Banner (BA-46) -->
<div style="background:#F0FDF4;border-left:5px solid #22C55E;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;">
  <b style="color:#15803D;font-size:16px;display:block;">Payment recorded, receipt {{ $payment->receipt_number }}</b>
  <p style="color:#166534;margin:4px 0 0;font-size:14px;">
    The exit request for {{ \App\Support\Lot::plate($session->plate_number) }} was sent to the Guard. Slot {{ $session->slot_code }} stays Pending exit until the Guard taps Good to Go.
  </p>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Progress & Payment Summary -->
  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="ba-card">
      <h2>Progress</h2>
      <div style="display:flex;flex-direction:column;gap:16px;font-size:14px;">
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Payment received</b>
            <small style="color:var(--muted);display:block;">{{ \App\Support\Lot::time($payment->paid_at) }}, receipt {{ $payment->receipt_number }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--ok);"></div>
          <div>
            <b>Sent to the Guard</b>
            <small style="color:var(--muted);display:block;">Gate validation, {{ \App\Support\Lot::time($payment->paid_at) }}</small>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:12px;height:12px;border-radius:99px;background:var(--paint-deep);"></div>
          <div>
            <b>Guard taps Good to Go</b>
            <small style="color:var(--muted);display:block;">Waiting. Exit window ends {{ \App\Support\Lot::time($payment->paid_at->copy()->addMinutes(15)) }}</small>
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
    </div>

    <div class="ba-card">
      <h2>Payment</h2>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Final amount</span>
          <b>{{ \App\Support\Lot::peso($payment->total) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Cash received</span>
          <b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['cash_received'] ?? $payment->total) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:8px;">
          <span style="color:var(--muted);">Change given</span>
          <b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['change_given'] ?? 0) }}</b>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--muted);">Received by</span>
          <b>{{ $payment->attendant?->name ?? auth()->user()->name }}</b>
        </div>
      </div>

      <div style="display:flex;gap:12px;">
        <button class="btn primary" type="button" data-open="#receipt-preview-dlg">Print receipt</button>
        <a class="btn ghost" href="{{ route('attendant.exit.index') }}">Search another vehicle</a>
      </div>
    </div>
  </div>

  <!-- Right Column: Thermal Receipt Preview (BA-46, BA-47) -->
  <div>
    <div class="ba-thermal">
      <h4>Bayline</h4>
      <p class="subt">Official receipt</p>
      <div class="t-div"></div>
      <div class="t-row"><span>Receipt</span><b>{{ $payment->receipt_number }}</b></div>
      <div class="t-row"><span>Ticket</span><b>{{ $session->ticket_number }}</b></div>
      <div class="t-row"><span>Plate</span><b>{{ \App\Support\Lot::plate($session->plate_number) }}</b></div>
      <div class="t-row"><span>Date</span><b>{{ \App\Support\Lot::stamp($payment->paid_at) }}</b></div>
      <div class="t-div"></div>
      <div class="t-row"><span>Base fee (first hour)</span><b>{{ \App\Support\Lot::peso($payment->base_fee) }}</b></div>
      @if ($payment->additional_fee > 0)
        <div class="t-row"><span>Additional hours</span><b>{{ \App\Support\Lot::peso($payment->additional_fee) }}</b></div>
      @endif
      @if ($payment->penalty > 0)
        <div class="t-row"><span>Penalties</span><b>{{ \App\Support\Lot::peso($payment->penalty) }}</b></div>
      @endif
      @if ($payment->discount > 0)
        <div class="t-row" style="color:#15803D;">
          <span>Discount ({{ $payment->receipt_snapshot['discount_name'] ?? 'Discount' }})</span>
          <b>-{{ \App\Support\Lot::peso($payment->discount) }}</b>
        </div>
      @endif
      <div class="t-div"></div>
      <div class="t-row" style="font-size:16px;"><span>Total paid</span><b>{{ \App\Support\Lot::peso($payment->total) }}</b></div>
      <div class="t-row"><span>Cash received</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['cash_received'] ?? $payment->total) }}</b></div>
      <div class="t-row"><span>Change</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['change_given'] ?? 0) }}</b></div>
      <div class="t-div"></div>
      <p class="foot">
        Exit gate approval is required before you leave.<br>
        Valid for 15 minutes.
      </p>
    </div>
  </div>
</div>

<!-- POP-UP BA-47: Receipt Print Preview Modal -->
<dialog class="badlg" id="receipt-preview-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2>Receipt preview</h2>
    <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:-6px;">Booth thermal printer, 58 mm paper</p>

    <div class="ba-thermal" id="receipt-print-slip" style="box-shadow:none;border:1px solid #CBD5E1;width:100%;max-width:260px;">
      <h4>Bayline</h4>
      <p class="subt">Official receipt</p>
      <div class="t-div"></div>
      <div class="t-row"><span>Receipt</span><b>{{ $payment->receipt_number }}</b></div>
      <div class="t-row"><span>Ticket</span><b>{{ $session->ticket_number }}</b></div>
      <div class="t-row"><span>Plate</span><b>{{ \App\Support\Lot::plate($session->plate_number) }}</b></div>
      <div class="t-row"><span>Date</span><b>{{ \App\Support\Lot::stamp($payment->paid_at) }}</b></div>
      <div class="t-div"></div>
      <div class="t-row"><span>Base fee</span><b>{{ \App\Support\Lot::peso($payment->base_fee) }}</b></div>
      <div class="t-row"><span>Additional</span><b>{{ \App\Support\Lot::peso($payment->additional_fee) }}</b></div>
      @if ($payment->discount > 0)
        <div class="t-row"><span>Discount</span><b>-{{ \App\Support\Lot::peso($payment->discount) }}</b></div>
      @endif
      <div class="t-div"></div>
      <div class="t-row" style="font-size:15px;"><span>Total paid</span><b>{{ \App\Support\Lot::peso($payment->total) }}</b></div>
      <div class="t-row"><span>Cash received</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['cash_received'] ?? $payment->total) }}</b></div>
      <div class="t-row"><span>Change</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['change_given'] ?? 0) }}</b></div>
      <p class="foot">Exit gate approval is required before you leave. Valid for 15 minutes.</p>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" onclick="window.baPrint('#receipt-print-slip')">Print</button>
    </div>
  </div>
</dialog>
@endsection
