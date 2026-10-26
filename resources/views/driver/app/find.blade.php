@extends('layouts.driver-app')

@section('title', 'Find parking - Bayline')
@section('tab', 'find')
@section('heading', 'Find parking')

@section('content')
@php
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<section class="dcard">
  <div class="bigcount">
    <span class="n">{{ $lot['available'] }}</span>
    <div><b>{{ $lot['available'] === 0 ? 'No free slots right now' : 'slots free of '.$lot['total'] }}</b><span>Updated {{ $lot['updated'] }}</span></div>
    @if ($lot['available'] === 0)<span class="full-tag">LOT FULL</span>@endif
  </div>
  <p class="meta" style="margin-top:8px;font-size:13px;color:var(--muted)">Rate: {{ $rateText }}</p>
</section>

@if ($lot['available'] === 0)
  <div class="alert" role="status" style="margin-top:14px">
    <b>The lot is full</b>
    @if ($release)
      Slot {{ $release['slot'] }} is paid and should be free by {{ $release['time'] }}. You can still reserve a slot for a later time.
    @else
      You can still reserve a slot for a later time.
    @endif
  </div>
@endif

<div class="lot driver-layout" data-layout-readonly style="margin-top:14px">
  <div class="driver-layout-canvas" data-layout-canvas>
  @foreach ($zones as $zone => $slots)
    @php($zonePosition = $zonePositions[$zone] ?? null)
    <span class="driver-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
      data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">{{ $zone }}</span>
    @foreach ($slots as $slot)
      <button type="button" class="sl driver-layout-slot {{ $cls[$slot->status] ?? '' }}"
        data-layout-slot data-slot="{{ $slot->code }}" data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
        data-type="{{ $slot->type }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
        data-status="{{ $label[$slot->status] ?? ucfirst($slot->status) }}" data-free="{{ $slot->status === 'available' ? 1 : 0 }}"
        data-reserve="{{ route('driver.reserve', ['slot' => $slot->id]) }}"
        aria-label="Slot {{ $slot->code }}, {{ $label[$slot->status] ?? $slot->status }}">
        <b>{{ $slot->code }}</b><span>{{ $label[$slot->status] ?? '' }}</span>
      </button>
    @endforeach
  @endforeach
  </div>
  @include('driver.app.partials.legend')
</div>

<div class="actions"><a class="btn" href="{{ route('driver.reserve') }}">Reserve a slot</a></div>

@push('dialogs')
<dialog class="dsheet" id="slot-sheet" aria-labelledby="ss-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="ss-t" data-f="title">Slot</h2>
    <dl class="kv" style="margin-top:8px">
      <div><dt>Zone</dt><dd data-f="zone"></dd></div>
      <div><dt>Type</dt><dd data-f="type"></dd></div>
      <div><dt>Right now</dt><dd><span class="status-tag" data-f="status"></span></dd></div>
    </dl>
    <div class="actions">
      <a class="btn" data-f="reserve" href="#">Reserve this slot</a>
      <button class="btn ghost" type="button" data-close>Close</button>
    </div>
  </div>
</dialog>
@endpush
@endsection
