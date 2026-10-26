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

@php $isFresh = session('fresh_payment', false); @endphp

@if ($isFresh)
<!-- Success Banner (BA-46) -->
<div style="background:#F0FDF4;border-left:5px solid #22C55E;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;">
  <b style="color:#15803D;font-size:16px;display:block;">Payment recorded, receipt {{ $payment->receipt_number }}</b>
  <p style="color:#166534;margin:4px 0 0;font-size:14px;">
    The exit request for {{ \App\Support\Lot::plate($session->plate_number) }} was sent to the Guard. Slot {{ $session->slot_code }} stays Pending exit until the Guard taps Good to Go.
  </p>
</div>
@else
<!-- Reprint Banner -->
<div style="background:var(--surface);border:1px solid var(--line);border-left:5px solid var(--paint);border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;color:var(--ink);">
  <b style="font-size:16px;display:block;">Official receipt {{ $payment->receipt_number }} &mdash; Reprint</b>
  <p style="color:var(--muted);margin:4px 0 0;font-size:14px;">
    Paid on {{ \App\Support\Lot::stamp($payment->paid_at) }} for vehicle {{ \App\Support\Lot::plate($session->plate_number) }} in slot {{ $session->slot_code }}. You can reprint the thermal slip or navigate back freely.
  </p>
</div>
@endif

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

      <div style="display:flex;gap:12px;align-items:center;">
        <button class="btn {{ $isFresh ? 'primary' : 'ghost' }}" type="button" data-open="#receipt-preview-dlg" id="btn-open-receipt-print" style="padding:0 24px;font-size:15px;display:inline-flex;align-items:center;gap:8px;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
          <span>{{ $isFresh ? 'Print receipt' : 'Reprint receipt' }}</span>
        </button>
        <a class="btn {{ $isFresh ? 'ghost guard-unprinted-receipt' : 'primary' }}" href="{{ route('attendant.exit.index') }}">
          {{ $isFresh ? 'Search another vehicle' : 'Back to Exit & payment' }}
        </a>
      </div>
    </div>
  </div>

  <!-- Right Column: Thermal Receipt Preview (BA-46, BA-47) -->
  <div>
    <div class="ba-thermal">
      <h4>{{ \App\Support\Lot::lotName() }}</h4>
      <p class="subt">{{ \App\Support\Lot::lotAddress() }}</p>
      <p class="subt" style="margin-top:2px;font-weight:700;">Official receipt</p>
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
      <p class="foot">{!! nl2br(e(\App\Support\Lot::receiptFooter())) !!}</p>
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
      <h4>{{ \App\Support\Lot::lotName() }}</h4>
      <p class="subt">{{ \App\Support\Lot::lotAddress() }}</p>
      <p class="subt" style="margin-top:2px;font-weight:700;">Official receipt</p>
      <div class="t-div"></div>
      <div class="t-row"><span>Receipt</span><b>{{ $payment->receipt_number }}</b></div>
      <div class="t-row"><span>Ticket</span><b>{{ $session->ticket_number }}</b></div>
      <div class="t-row"><span>Plate</span><b>{{ \App\Support\Lot::plate($session->plate_number) }}</b></div>
      <div class="t-row"><span>Date</span><b>{{ \App\Support\Lot::stamp($payment->paid_at) }}</b></div>
      <div class="t-div"></div>
      <div class="t-row"><span>Base fee</span><b>{{ \App\Support\Lot::peso($payment->base_fee) }}</b></div>
      @if ($payment->additional_fee > 0)
        <div class="t-row"><span>Additional</span><b>{{ \App\Support\Lot::peso($payment->additional_fee) }}</b></div>
      @endif
      @if ($payment->penalty > 0)
        <div class="t-row"><span>Penalties</span><b>{{ \App\Support\Lot::peso($payment->penalty) }}</b></div>
      @endif
      @if ($payment->discount > 0)
        <div class="t-row" style="color:#15803D;"><span>Discount</span><b>-{{ \App\Support\Lot::peso($payment->discount) }}</b></div>
      @endif
      <div class="t-div"></div>
      <div class="t-row" style="font-size:15px;"><span>Total paid</span><b>{{ \App\Support\Lot::peso($payment->total) }}</b></div>
      <div class="t-row"><span>Cash received</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['cash_received'] ?? $payment->total) }}</b></div>
      <div class="t-row"><span>Change</span><b>{{ \App\Support\Lot::peso($payment->receipt_snapshot['change_given'] ?? 0) }}</b></div>
      <div class="t-div"></div>
      <p class="foot">{!! nl2br(e(\App\Support\Lot::receiptFooter())) !!}</p>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="button" id="btn-do-print-receipt" onclick="triggerReceiptPrint();">Print</button>
    </div>
  </div>
</dialog>

<!-- Confirmation Modal to verify physical printout -->
<dialog class="badlg" id="print-confirm-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
      <span style="font-size:26px;">🖨️</span>
      <h2 style="margin:0;">Did the receipt print?</h2>
    </div>
    <p>Please check your thermal printer. Did receipt <b>{{ $payment->receipt_number }}</b> print successfully?</p>
    <div style="font-size:13px;color:var(--ink);background:var(--bg);padding:12px 14px;border-radius:6px;border-left:3px solid var(--paint);margin-bottom:16px;">
      If printing was cancelled or jammed, click <strong>Cancel / Retry</strong> so the receipt remains marked as unprinted.
    </div>
    <div class="actions" style="justify-content:flex-end;">
      <button class="btn ghost" type="button" onclick="cancelReceiptPrint();">Cancel / Retry</button>
      <button class="btn primary" type="button" id="btn-confirm-printed" onclick="confirmReceiptPrinted();">✓ Yes, receipt printed</button>
    </div>
  </div>
</dialog>

<!-- Unprinted Receipt Warning Modal -->
<dialog class="badlg" id="unprinted-receipt-dlg">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
      <span style="font-size:26px;">⚠️</span>
      <h2 style="margin:0;">Receipt print required</h2>
    </div>
    <p>
      The official receipt for slot <b>{{ $session->slot_code }}</b> (Receipt <b>{{ $payment->receipt_number }}</b>) has not been printed yet.
    </p>
    <div style="font-size:13px;color:var(--ink);background:var(--bg);padding:12px 14px;border-radius:6px;border-left:3px solid var(--paint);margin-bottom:16px;">
      Printing is required so the driver has their physical receipt for proof of payment before moving to the exit gate.
    </div>
    <div class="actions" style="justify-content:flex-end;">
      <button class="btn ghost" type="button" data-close>Back</button>
      <button class="btn primary" type="button" onclick="document.getElementById('unprinted-receipt-dlg').close(); document.getElementById('receipt-preview-dlg').showModal();">Print receipt now</button>
    </div>
  </div>
</dialog>

<script>
  let isFreshPayment = {{ $isFresh ? 'true' : 'false' }};
  let hasPrintedReceipt = !isFreshPayment;
  let pendingReceiptUrl = '{{ route('attendant.exit.index') }}';

  function triggerReceiptPrint() {
    const previewDlg = document.getElementById('receipt-preview-dlg');
    if (previewDlg && typeof previewDlg.close === 'function') {
      previewDlg.close();
    }

    // Send to thermal printer
    window.baPrint('#receipt-print-slip');

    // Prompt attendant to confirm physical printout
    setTimeout(() => {
      const confirmDlg = document.getElementById('print-confirm-dlg');
      if (confirmDlg && typeof confirmDlg.showModal === 'function') {
        confirmDlg.showModal();
        const yesBtn = document.getElementById('btn-confirm-printed');
        if (yesBtn) yesBtn.focus();
      }
    }, 700);
  }

  function confirmReceiptPrinted() {
    const confirmDlg = document.getElementById('print-confirm-dlg');
    if (confirmDlg && typeof confirmDlg.close === 'function') {
      confirmDlg.close();
    }
    markReceiptPrinted();
  }

  function cancelReceiptPrint() {
    const confirmDlg = document.getElementById('print-confirm-dlg');
    if (confirmDlg && typeof confirmDlg.close === 'function') {
      confirmDlg.close();
    }
    if (isFreshPayment) {
      hasPrintedReceipt = false;
      const btn = document.getElementById('btn-open-receipt-print');
      if (btn) {
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg> <span>Print receipt</span>`;
        btn.classList.remove('ghost');
        btn.classList.add('primary');
      }
    }
  }

  function markReceiptPrinted() {
    hasPrintedReceipt = true;
    const btn = document.getElementById('btn-open-receipt-print');
    if (btn) {
      btn.innerHTML = 'Reprint receipt ✓';
      btn.classList.remove('primary');
      btn.classList.add('ghost');
    }
  }

  function setupReceiptNavigationGuard() {
    if (!isFreshPayment) return; // Reprints are optional and do NOT block navigation

    const selector = '.guard-unprinted-receipt, .ba-tabs a, header.ba-head a, a.btn';
    document.querySelectorAll(selector).forEach(el => {
      const href = el.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

      el.addEventListener('click', (e) => {
        if (!hasPrintedReceipt) {
          e.preventDefault();
          pendingReceiptUrl = el.href;
          const dlg = document.getElementById('unprinted-receipt-dlg');
          if (dlg && typeof dlg.showModal === 'function') {
            dlg.showModal();
          }
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    setupReceiptNavigationGuard();
    // Pressing 'p' or 'Enter' opens the print preview directly if no other dialog is open
    document.addEventListener('keydown', (e) => {
      if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
      if (document.querySelector('dialog[open]')) return;
      if (e.key === 'p' || e.key === 'P' || e.key === 'Enter') {
        const btn = document.getElementById('btn-open-receipt-print');
        if (btn) {
          e.preventDefault();
          btn.click();
        }
      }
    });
  });
  setupReceiptNavigationGuard();
</script>
@endsection
