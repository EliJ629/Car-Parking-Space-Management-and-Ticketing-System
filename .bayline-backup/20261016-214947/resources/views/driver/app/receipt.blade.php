@extends('layouts.driver-app')

@section('title', 'Receipt '.$payment->receipt_number.' - Bayline')
@section('tab', 'account')
@section('heading', 'Receipt')
@section('back', route('driver.history'))

@section('content')
<div class="receipt">
  <h3>{{ $lotName }}</h3>
  <p class="tag">Official parking receipt</p>
  <dl class="kv">
    <div><dt>Receipt</dt><dd>{{ $payment->receipt_number }}</dd></div>
    <div><dt>Paid</dt><dd>{{ \App\Support\Lot::stamp($payment->paid_at) }}</dd></div>
    <div><dt>Ticket</dt><dd>{{ $session->ticket_number }}</dd></div>
    <div><dt>Plate</dt><dd>{{ \App\Support\Lot::plate($session->plate_number) }}</dd></div>
    <div><dt>Slot</dt><dd>{{ $session->slot?->code ?? $session->slot_code }}</dd></div>
    <div><dt>Time in</dt><dd>{{ \App\Support\Lot::stamp($session->entered_at) }}</dd></div>
    <div><dt>Time out</dt><dd>{{ $session->exited_at ? \App\Support\Lot::stamp($session->exited_at) : '-' }}</dd></div>
  </dl>
  <hr>
  <dl class="kv">
    <div><dt>First hour</dt><dd>{{ \App\Support\Lot::peso($payment->base_fee) }}</dd></div>
    @if ((float) $payment->additional_fee > 0)<div><dt>Additional hours ({{ $additionalHours }} x {{ \App\Support\Lot::peso($unit) }})</dt><dd>{{ \App\Support\Lot::peso($payment->additional_fee) }}</dd></div>@endif
    @if ((float) $payment->discount > 0)<div><dt>Discount</dt><dd>-{{ \App\Support\Lot::peso($payment->discount) }}</dd></div>@endif
    @if ((float) $payment->penalty > 0)<div><dt>Penalties</dt><dd>{{ \App\Support\Lot::peso($payment->penalty) }}</dd></div>@endif
  </dl>
  <div class="total-row"><span>Total paid</span><b>{{ \App\Support\Lot::peso($payment->total) }}</b></div>
  <dl class="kv" style="margin-top:6px">
    <div><dt>Method</dt><dd>{{ ucfirst($payment->method ?? 'cash') }}</dd></div>
    @if ($cash !== null)<div><dt>Cash received</dt><dd>{{ \App\Support\Lot::peso($cash) }}</dd></div>@endif
    @if ($change !== null)<div><dt>Change</dt><dd>{{ \App\Support\Lot::peso($change) }}</dd></div>@endif
    <div><dt>Received by</dt><dd>{{ $payment->attendant?->name ?? 'Booth attendant' }}</dd></div>
  </dl>
</div>
<div class="actions"><button class="btn ghost" type="button" data-print>Print or save</button></div>
@endsection
