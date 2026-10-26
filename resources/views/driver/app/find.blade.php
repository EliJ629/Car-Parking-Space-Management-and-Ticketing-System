@extends('layouts.driver-app')

@section('title', 'Find parking - Bayline')
@section('tab', 'find')
@section('heading', 'Find parking')

@section('content')
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

@include('partials.slot-layout', ['zones' => $zones, 'zonePositions' => $zonePositions, 'item' => 'driver.app.partials.slot-item-view', 'label' => 'Parking lot map, tap a slot'])
@include('driver.app.partials.legend')

<div class="actions"><a class="btn" href="{{ route('driver.reserve') }}">Reserve a slot</a></div>

@push('dialogs')
<dialog class="dsheet" id="slot-sheet" aria-labelledby="ss-t">
  <div class="in">
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
