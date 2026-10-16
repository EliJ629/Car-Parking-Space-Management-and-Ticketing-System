@extends('layouts.guard')

@section('title', 'Slots view - Bayline')
@section('tab', 'slots')

@section('content')
@php
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<h1 class="g-title">Slots view</h1>
<p class="g-sub">The live slot board. Read only: the Parking Manager changes slots.</p>

<div class="g-stats" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-top:16px">
  @foreach ($label as $key => $text)
    <div class="g-stat {{ $key === 'pending_exit' && ($counts[$key] ?? 0) ? 'warn' : '' }}"><b>{{ $counts[$key] ?? 0 }}</b><span>{{ $text }}</span></div>
  @endforeach
</div>

<div class="lot g-board" role="group" aria-label="Slot board, {{ $total }} slots">
  @foreach ($zones as $zone => $slots)
    <div class="zl">{{ strtoupper($zone) }}</div>
    <div class="g">
      @foreach ($slots as $slot)
        @php $in = $info[$slot->id]; @endphp
        <button type="button" class="sl {{ $cls[$slot->status] ?? '' }}" data-slot="{{ $slot->code }}"
          data-zone="{{ $slot->zone }}" data-type="{{ $slot->type }}" data-status="{{ $label[$slot->status] ?? $slot->status }}"
          data-vehicle="{{ $in['vehicle'] }}" data-since="{{ $in['since'] }}" data-booking="{{ $in['booking'] }}" data-reason="{{ $in['reason'] }}"
          aria-label="Slot {{ $slot->code }}, {{ $label[$slot->status] ?? $slot->status }}">
          <b>{{ $slot->code }}</b><span>{{ $label[$slot->status] ?? '' }}</span>
        </button>
      @endforeach
    </div>
  @endforeach
  <div class="g-legend"><span><i></i>Available</span><span><i class="r"></i>Reserved</span><span><i class="o"></i>Occupied</span><span><i class="p"></i>Pending exit</span><span><i class="x"></i>Out of service</span></div>
</div>

@push('dialogs')
<dialog class="gdlg" id="slot-dlg" aria-labelledby="sd-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
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
    <div class="actions"><button class="btn" type="button" data-close>Close</button></div>
  </div>
</dialog>
@endpush
@endsection
