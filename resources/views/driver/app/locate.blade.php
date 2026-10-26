@extends('layouts.driver-app')

@section('title', 'Locate my car - Bayline')
@section('tab', 'session')
@section('heading', 'Locate my car')
@section('back', route('driver.session'))

@section('content')
@php
    $mine = $session->slot?->code ?? $session->slot_code;
@endphp
<section class="dcard paint">
  <p class="lab">Your car is in</p>
  <h3>Slot {{ $mine }}</h3>
  <p>{{ $session->slot?->zone }}. {{ $vehicle ? $vehicle->describe() : \App\Support\Lot::plate($session->plate_number) }}.</p>
</section>
<div style="margin-top:14px">
@include('partials.slot-layout', ['zones' => $zones, 'item' => 'driver.app.partials.slot-item-locate', 'label' => 'Lot map. Your car is in slot '.$mine.'.'])
</div>
<p class="fine" style="margin-top:10px">Ticket {{ $session->ticket_number }}</p>
@endsection
