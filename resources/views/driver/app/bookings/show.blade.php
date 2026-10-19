@extends('layouts.driver-app')

@section('title', 'Reservation '.$reservation->code.' - Bayline')
@section('tab', 'bookings')
@section('heading', $fresh ? 'Reserved' : 'Reservation')
@section('back', route('driver.bookings'))

@section('content')
@php
    $r = $reservation;
    $open = $r->status === 'booked';
    $slotCode = $r->slot?->code ?? $r->slot_code;
@endphp

@if ($fresh)
  <div class="alert ok" role="status" style="margin-bottom:14px"><b>Your slot is reserved</b>Show this QR code to the booth attendant when you arrive. Check in by {{ \App\Support\Lot::time($holdEnds) }}, or the reservation expires and the slot is released.</div>
@endif
@if (session('canceled'))
  <div class="alert" role="status" style="margin-bottom:14px"><b>Reservation canceled</b>Slot {{ $slotCode }} is free again.</div>
@endif

<section class="dcard">
  <div class="top" style="display:flex;justify-content:space-between;align-items:center;gap:10px">
    <p class="lab" style="margin:0">Reservation code</p> @include('driver.app.partials.status', ['reservation' => $r])
  </div>
  <div class="qrbox {{ $open ? '' : 'void' }}" style="margin-top:12px">
    <div data-qr="{{ $r->code }}" aria-hidden="false"></div>
    <div class="rsv">{{ $r->code }}</div>
    @if ($r->status === 'expired')<span class="stamp">EXPIRED</span>@endif
    @if ($r->status === 'canceled')<span class="stamp">CANCELED</span>@endif
    @if (in_array($r->status, ['checked_in', 'completed'], true))<span class="stamp used">USED</span>@endif
  </div>
  <dl class="kv" style="margin-top:12px">
    <div><dt>Slot</dt><dd>{{ $slotCode }}</dd></div>
    <div><dt>Arrival</dt><dd>{{ \App\Support\Lot::when($r->scheduled_at) }}</dd></div>
    <div><dt>Vehicle</dt><dd>{{ \App\Support\Lot::plate($r->plate_number) }}</dd></div>
    <div><dt>Cost</dt><dd>Free</dd></div>
  </dl>
</section>

@if ($open)
  <section class="dcard" style="margin-top:14px">
    <p class="lab">When to arrive</p>
    <ul class="tl">
      <li class="now"><b>Check in opens at {{ \App\Support\Lot::time($opens) }}</b><span>{{ \App\Support\Lot::earlyMinutes() }} minutes before your arrival time</span></li>
      <li class="todo"><b>Arrival time {{ \App\Support\Lot::time($r->scheduled_at) }}</b><span>Your slot is held for you</span></li>
      <li class="todo"><b>Slot released after {{ \App\Support\Lot::time($holdEnds) }}</b><span>{{ \App\Support\Lot::graceMinutes() }} minutes of grace, then the reservation expires</span></li>
    </ul>
  </section>
  <div class="actions"><button class="btn stop" type="button" data-open="#cancel-res">Cancel reservation</button></div>

  @push('dialogs')
  <dialog class="dsheet stop" id="cancel-res" aria-labelledby="cx-t">
    <form class="in" method="POST" action="{{ route('driver.bookings.cancel', $r) }}" data-busy data-busy-label="Canceling">
      @csrf
      <button class="x" type="button" data-close aria-label="Close">X</button>
      <h2 id="cx-t">Cancel {{ $r->code }}?</h2>
      <p class="sub" style="color:var(--ink)">Slot {{ $slotCode }} will be released for other drivers. You can reserve again any time.</p>
      <div class="actions">
        <button class="btn stop" type="submit">Yes, cancel it</button>
        <button class="btn ghost" type="button" data-close>Keep reservation</button>
      </div>
    </form>
  </dialog>
  @endpush
@elseif ($r->status === 'expired')
  <div class="alert stop" style="margin-top:14px"><b>This reservation expired</b>The vehicle did not check in within {{ \App\Support\Lot::graceMinutes() }} minutes of the arrival time, so the slot was released and the code no longer works.</div>
  <div class="actions"><a class="btn" href="{{ route('driver.reserve') }}">Reserve again</a></div>
@elseif ($r->status === 'canceled')
  <div class="actions"><a class="btn" href="{{ route('driver.reserve') }}">Reserve again</a></div>
@elseif ($r->session)
  <div class="actions"><a class="btn" href="{{ route('driver.session') }}">View session</a></div>
@endif
@endsection
