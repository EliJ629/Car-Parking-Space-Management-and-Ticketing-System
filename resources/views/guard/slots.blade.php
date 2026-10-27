@extends('layouts.guard')

@section('title', 'Slots view - Bayline')
@section('tab', 'slots')

@push('head')
<link rel="stylesheet" href="/css/manager.css?v={{ filemtime(public_path('css/manager.css')) }}">
@endpush

@section('content')
@php
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<h1 class="g-title">Slots view</h1>
<p class="g-sub">The live slot board. Read only: the Parking Manager changes slots.</p>
@if ($longCount > 0)
  <div class="g-banner stop" role="status"><b>{{ $longCount }} long-parked {{ \Illuminate\Support\Str::plural('vehicle', $longCount) }}</b>Shown in red with a clock label. Open the slot for its plate and how long it has been parked.</div>
@endif

<div class="g-stats" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-top:16px">
  @foreach ($label as $key => $text)
    <div class="g-stat {{ $key === 'pending_exit' && ($counts[$key] ?? 0) ? 'warn' : '' }}"><b>{{ $counts[$key] ?? 0 }}</b><span>{{ $text }}</span></div>
  @endforeach
</div>

@if ($floors !== [])
  <nav class="manager-tabs" aria-label="Parking floors">
    @foreach ($floors as $floor)
      <a href="{{ route('guard.slots', ['floor' => $floor]) }}" class="{{ (int) $selectedFloor === (int) $floor ? 'active' : '' }}" @if ((int) $selectedFloor === (int) $floor) aria-current="page" @endif>Floor {{ $floor }}</a>
    @endforeach
  </nav>
@endif

<section class="manager-panel manager-lot-panel guard-layout">
  <div class="manager-panel-heading">
    <div><p class="eyebrow">Current layout</p><h2>Floor {{ $selectedFloor }}</h2></div>
    <span class="manager-muted">Read only</span>
  </div>
  @if ($floorSlots->isEmpty())
    <div class="manager-empty"><h3>This floor has no parking slots yet</h3></div>
  @else
    <div class="manager-lot manager-layout" data-layout-editor>
      <div class="manager-layout-canvas" data-layout-canvas>
        @foreach ($slotsByZone as $zone => $zoneSlots)
          @php($zonePosition = $zonePositions[$zone] ?? null)
          <span class="manager-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
                data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">
            {{ $zone }} <small>({{ $zonePosition?->vehicle_type ?? \App\Models\ParkingLayoutZone::VEHICLE_CAR }})</small>
          </span>
        @endforeach
        @foreach ($slotsByZone as $zone => $zoneSlots)
          @foreach ($zoneSlots as $slot)
            @php($in = $info[$slot->id])
            @php($slotClass = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'][$slot->status] ?? '')
            <button type="button" class="sl slot-layout-slot {{ $slotClass }} {{ $in['long'] ? 'long' : '' }}"
              data-slot="{{ $slot->code }}" data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
              data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
              data-type="{{ $slot->type }}" data-status="{{ $in['long'] ? 'Long parked' : $slot->statusLabel() }}"
              data-vehicle="{{ $in['vehicle'] }}" data-since="{{ $in['since'] }}" data-booking="{{ $in['booking'] }}" data-reason="{{ $in['reason'] }}" data-report="{{ $in['report'] }}"
              aria-label="Slot {{ $slot->code }}, {{ $in['long'] ? 'long parked' : $slot->statusLabel() }}{{ $slot->type === \App\Models\ParkingSlot::TYPE_PWD ? ', PWD slot' : '' }}">
              <strong>{{ $slot->code }}</strong>
              @if ($in['long'])<span>Long parked</span>@else<span>{{ $slot->statusLabel() }}</span>@endif
              @if ($in['plate'] && in_array($slot->status, [\App\Models\ParkingSlot::STATUS_OCCUPIED, \App\Models\ParkingSlot::STATUS_PENDING_EXIT], true))
                <small class="manager-slot-extra">{{ $in['plate'] }}</small><small class="manager-slot-extra">{{ $in['duration'] }}</small>
              @endif
              @if ($in['booked'] && $slot->status === \App\Models\ParkingSlot::STATUS_RESERVED)
                <small class="manager-slot-extra">{{ $in['booked_plate'] }}</small><small class="manager-slot-extra">{{ $in['booked'] }}</small>
              @endif
              @if ($slot->type === \App\Models\ParkingSlot::TYPE_PWD)<small class="manager-pwd-marker" aria-label="PWD parking slot">PWD</small>@endif
            </button>
          @endforeach
        @endforeach
      </div>
      <div class="manager-lane" aria-label="Vehicle lane"><span>Entrance</span><i aria-hidden="true"></i><span>Exit</span></div>
    </div>
    <div class="manager-legend" aria-label="Slot status legend">
      @foreach (\App\Models\ParkingSlot::statuses() as $status => $text)
        <span><i class="manager-legend-{{ str_replace('_', '-', $status) }}" aria-hidden="true"></i>{{ $text }}</span>
      @endforeach
      <span><i class="guard-legend-long" aria-hidden="true"></i>Long parked</span>
    </div>
  @endif
</section>

@push('dialogs')
<dialog class="gdlg" id="slot-dlg" aria-labelledby="sd-t">
  <div class="in">
    <h2 id="sd-t" data-f="title">Slot</h2>
    <dl class="g-kv">
      <div style="display:contents" data-row="zone"><dt>Zone</dt><dd></dd></div>
      <div style="display:contents" data-row="type"><dt>Type</dt><dd></dd></div>
      <div style="display:contents" data-row="status"><dt>Status</dt><dd></dd></div>
      <div style="display:contents" data-row="vehicle"><dt>Vehicle</dt><dd></dd></div>
      <div style="display:contents" data-row="since"><dt>Since</dt><dd></dd></div>
      <div style="display:contents" data-row="booking"><dt>Reservation</dt><dd></dd></div>
      <div style="display:contents" data-row="reason"><dt>Reason</dt><dd></dd></div>
    </dl>
    <div class="actions"><a class="btn" data-f="report" href="#" hidden>Report wrong slot</a><button class="btn ghost" type="button" data-close>Close</button></div>
  </div>
</dialog>
@endpush
@endsection
