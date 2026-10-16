@extends('layouts.driver-app')

@section('title', 'My session - Bayline')
@section('tab', 'session')
@section('heading', 'My session')

@section('content')
@if ($session)
  @php
      $slotCode = $session->slot?->code ?? $session->slot_code;
      $paid = $session->status === 'paid';
  @endphp

  @if ($paid)
    <section class="dcard pending">
      <p class="lab">Pending exit</p>
      <h3>Payment received</h3>
      <p>Drive to the exit gate and show your ticket. You have until <b>{{ \App\Support\Lot::time($windowEnd) }}</b> to leave.</p>
    </section>
    <div class="alert" style="margin-top:12px">If you do not leave by then, the exit window ends and the overstay penalty can apply. The attendant will tell you the new amount.</div>
  @endif

  <section class="dcard dark" style="margin-top:14px">
    <p class="lab">Parking ticket</p>
    <p class="ticket-no">{{ $session->ticket_number }}</p>
    <div class="cols3">
      <div><span>Slot</span><b>{{ $slotCode }}</b></div>
      <div><span>Plate</span><b>{{ \App\Support\Lot::plate($session->plate_number) }}</b></div>
      <div><span>In at</span><b>{{ \App\Support\Lot::time($session->entered_at) }}</b></div>
    </div>
  </section>

  @if ($estimate)
    <section class="dcard" style="margin-top:14px" aria-labelledby="fee-h">
      <p class="lab" id="fee-h">Amount due so far</p>
      <dl class="kv">
        <div><dt>Time parked</dt><dd>{{ \App\Support\Lot::duration($estimate['minutes']) }}</dd></div>
        <div><dt>First hour</dt><dd>{{ \App\Support\Lot::peso($estimate['first_hour']) }}</dd></div>
        <div><dt>Additional hours ({{ $estimate['additional_hours'] }} x {{ \App\Support\Lot::peso($estimate['additional_unit']) }})</dt><dd>{{ \App\Support\Lot::peso($estimate['additional']) }}</dd></div>
        @if ($estimate['capped'])<div><dt>Daily maximum applied</dt><dd>{{ \App\Support\Lot::peso($estimate['cap_adjustment']) }}</dd></div>@endif
        @if ($estimate['lost_ticket_penalty'] > 0)<div><dt>Lost ticket penalty</dt><dd>{{ \App\Support\Lot::peso($estimate['lost_ticket_penalty']) }}</dd></div>@endif
        @if ($estimate['overstay_penalty'] > 0)<div><dt>Overstay penalty</dt><dd>{{ \App\Support\Lot::peso($estimate['overstay_penalty']) }}</dd></div>@endif
        @if ($estimate['already_paid'] > 0)<div><dt>Already paid</dt><dd>-{{ \App\Support\Lot::peso($estimate['already_paid']) }}</dd></div>@endif
      </dl>
      <div class="total-row"><span>Total</span><b>{{ \App\Support\Lot::peso($estimate['total']) }}</b></div>
      <p class="fine" style="margin-top:8px">This is an estimate. Pay in cash at the booth before you leave. The final amount is on your receipt.</p>
    </section>
  @elseif ($paid && $session->payment)
    <section class="dcard" style="margin-top:14px">
      <p class="lab">Paid at the booth</p>
      <dl class="kv">
        <div><dt>Receipt</dt><dd>{{ $session->payment->receipt_number }}</dd></div>
        <div><dt>Paid at</dt><dd>{{ \App\Support\Lot::time($session->payment->paid_at) }}</dd></div>
      </dl>
      <div class="total-row"><span>Total paid</span><b>{{ \App\Support\Lot::peso($session->payment->total) }}</b></div>
    </section>
  @endif

  <div class="actions two">
    <a class="btn ghost" href="{{ route('driver.session.locate') }}">Locate my car</a>
    <a class="btn ghost" href="{{ route('driver.history') }}">History</a>
  </div>

@elseif ($completed)
  <section class="dcard">
    <p class="lab">Parking completed</p>
    <h3>Thank you, drive safe</h3>
    <dl class="kv">
      <div><dt>Ticket</dt><dd>{{ $completed->ticket_number }}</dd></div>
      <div><dt>Slot</dt><dd>{{ $completed->slot?->code ?? $completed->slot_code }}</dd></div>
      <div><dt>In</dt><dd>{{ \App\Support\Lot::stamp($completed->entered_at) }}</dd></div>
      <div><dt>Out</dt><dd>{{ \App\Support\Lot::stamp($completed->exited_at) }}</dd></div>
    </dl>
    @if ($completed->payment)
      <div class="total-row"><span>Total paid</span><b>{{ \App\Support\Lot::peso($completed->payment->total) }}</b></div>
      <div class="actions"><a class="btn" href="{{ route('driver.receipt', $completed->payment) }}">View receipt</a></div>
    @endif
  </section>
@else
  @include('driver.app.partials.empty', [
    'title' => 'No active parking',
    'text' => 'When the booth attendant issues your ticket, it shows up here with the amount due and your slot.',
    'cta' => ['Find parking', route('driver.find')],
  ])
@endif
@endsection
