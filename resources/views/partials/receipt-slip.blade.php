{{-- The official parking receipt, exactly as the booth prints it. The booth screens and the driver's digital receipt both use this
     one view, so the two can never differ. $payment and $session are required; $slipId and $slipStyle are optional. --}}
@php $snap = (array) $payment->receipt_snapshot; $earlier = (float) ($snap['previously_paid'] ?? 0); @endphp
<div class="ba-thermal receipt-slip" @isset($slipId) id="{{ $slipId }}" @endisset @isset($slipStyle) style="{{ $slipStyle }}" @endisset>
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
    <div class="t-row" style="color:#15803D;"><span>Discount ({{ $snap['discount_name'] ?? 'Discount' }})</span><b>-{{ \App\Support\Lot::peso($payment->discount) }}</b></div>
  @endif
  <div class="t-div"></div>
  @if ($earlier > 0)
    <div class="t-row"><span>Bill total</span><b>{{ \App\Support\Lot::peso($snap['bill_total'] ?? 0) }}</b></div>
    <div class="t-row"><span>Paid earlier</span><b>-{{ \App\Support\Lot::peso($earlier) }}</b></div>
  @endif
  <div class="t-row" style="font-size:16px;"><span>{{ $earlier > 0 ? 'Paid now' : 'Total paid' }}</span><b>{{ \App\Support\Lot::peso($payment->total) }}</b></div>
  <div class="t-row"><span>Cash received</span><b>{{ \App\Support\Lot::peso($snap['cash_received'] ?? $payment->total) }}</b></div>
  <div class="t-row"><span>Change</span><b>{{ \App\Support\Lot::peso($snap['change_given'] ?? 0) }}</b></div>
  <div class="t-div"></div>
  <p class="foot">{!! nl2br(e(\App\Support\Lot::receiptFooter())) !!}</p>
</div>
