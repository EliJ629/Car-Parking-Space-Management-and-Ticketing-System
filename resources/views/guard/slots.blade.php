@extends('layouts.guard')

@section('title', 'Slots view - Bayline')
@section('tab', 'slots')

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

@include('partials.slot-layout', ['zones' => $zones, 'zonePositions' => $zonePositions, 'item' => 'guard.partials.slot-item', 'scale' => 1.25, 'label' => 'Slot board, '.$total.' slots, same map as the Parking Manager'])
<div class="g-legend"><span><i></i>Available</span><span><i class="r"></i>Reserved</span><span><i class="o"></i>Occupied</span><span><i class="p"></i>Pending exit</span><span><i class="x"></i>Out of service</span><span><i class="lg"></i>Long parked ({{ $longHours }} h or more)</span></div>

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
