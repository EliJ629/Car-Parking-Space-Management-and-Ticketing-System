@extends('layouts.driver-app')

@section('title', 'Locate my car - Bayline')
@section('tab', 'session')
@section('heading', 'Locate my car')
@section('back', route('driver.session'))

@section('content')
@php
    $mine = $session->slot?->code ?? $session->slot_code;
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
@endphp
<section class="dcard paint">
  <p class="lab">Your car is in</p>
  <h3>Slot {{ $mine }}</h3>
  <p>{{ $session->slot?->zone }}. {{ $vehicle ? $vehicle->describe() : \App\Support\Lot::plate($session->plate_number) }}.</p>
</section>
<div class="lot plain" style="margin-top:14px" role="img" aria-label="Lot map. Your car is in slot {{ $mine }}.">
  @foreach ($zones as $zone => $slots)
    <div class="zl">{{ strtoupper($zone) }}</div>
    <div class="g">
      @foreach ($slots as $slot)
        <div class="sl {{ $slot->code === $mine ? 'me' : '' }}"><b>{{ $slot->code }}</b>@if ($slot->code === $mine)<span>You are here</span>@endif</div>
      @endforeach
    </div>
  @endforeach
</div>
<p class="fine" style="margin-top:10px">Ticket {{ $session->ticket_number }}</p>
@endsection
