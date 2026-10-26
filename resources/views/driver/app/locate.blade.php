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
<div class="lot driver-layout" data-layout-readonly style="margin-top:14px" role="img" aria-label="Lot map. Your car is in slot {{ $mine }}.">
  <div class="driver-layout-canvas" data-layout-canvas>
    @foreach ($zones as $zone => $slots)
      @php $zonePosition = $zonePositions[$zone] ?? null; @endphp
      <span class="driver-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
        data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">{{ $zone }}</span>
      @foreach ($slots as $slot)
        <div class="sl driver-layout-slot {{ $slot->code === $mine ? 'me' : '' }}"
             data-layout-slot data-slot="{{ $slot->code }}" data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
             data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}">
          <b>{{ $slot->code }}</b>
          @if ($slot->code === $mine)<span>You are here</span>@endif
        </div>
      @endforeach
    @endforeach
  </div>
</div>
<p class="fine" style="margin-top:10px">Ticket {{ $session->ticket_number }}</p>
@endsection
