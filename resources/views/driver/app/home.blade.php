@extends('layouts.driver-app')

@section('title', 'Home - Bayline')
@section('tab', 'home')
@section('heading', 'Home')

@section('content')
@php
    $first = \Illuminate\Support\Str::of($user->name)->before(' ');
    $full = $lot['available'] === 0;
@endphp
<h2>{{ $greeting }}, {{ $first }}.</h2>
<p class="sub">{{ \App\Support\Lot::date(now()) }}</p>

<div class="stack-l" style="margin-top:16px">

  @if ($session && $session->status === 'paid')
    <section class="dcard pending" aria-labelledby="hp">
      <p class="lab">Pending exit</p>
      <h3 id="hp">Payment received</h3>
      <p>Drive to the exit gate. The guard checks your ticket and opens the gate.</p>
      <dl class="kv" style="margin-top:10px">
        <div><dt>Ticket</dt><dd>{{ $session->ticket_number }}</dd></div>
        <div><dt>Slot</dt><dd>{{ $session->slot?->code ?? $session->slot_code }}</dd></div>
        <div><dt>Leave by</dt><dd>{{ \App\Support\Lot::time($session->payment?->paid_at?->copy()->addMinutes(\App\Support\Lot::exitWindowMinutes())) }}</dd></div>
      </dl>
      <div class="actions"><a class="btn dark" href="{{ route('driver.session') }}">View session</a></div>
    </section>
  @elseif ($session)
    <section class="dcard dark" aria-labelledby="hs">
      <p class="lab">Parking now</p>
      <p class="ticket-no" id="hs">{{ $session->ticket_number }}</p>
      <div class="cols3">
        <div><span>Slot</span><b>{{ $session->slot?->code ?? $session->slot_code }}</b></div>
        <div><span>Parked</span><b>{{ \App\Support\Lot::duration($estimate['minutes'] ?? 0) }}</b></div>
        <div><span>Due now</span><b>{{ \App\Support\Lot::peso($estimate['total'] ?? 0) }}</b></div>
      </div>
      <div class="actions"><a class="btn" href="{{ route('driver.session') }}">View session</a></div>
    </section>
  @elseif ($reservation)
    <section class="dcard paint" aria-labelledby="hr">
      <p class="lab">Your next reservation</p>
      <h3 id="hr">Slot {{ $reservation->slot?->code ?? $reservation->slot_code }}</h3>
      <p>{{ \App\Support\Lot::when($reservation->scheduled_at) }}</p>
      <dl class="kv" style="margin-top:8px">
        <div><dt>Code</dt><dd>{{ $reservation->code }}</dd></div>
        <div><dt>Vehicle</dt><dd>{{ \App\Support\Lot::plate($reservation->plate_number) }}</dd></div>
      </dl>
      <div class="actions"><a class="btn" href="{{ route('driver.bookings.show', $reservation) }}">View reservation</a></div>
    </section>
  @elseif (! $hasVehicle)
    <section class="dcard paint" aria-labelledby="hv">
      <p class="lab">Get started</p>
      <h3 id="hv">Add your vehicle</h3>
      <p>Save your plate number once. Then you can reserve a slot, and your parking ticket shows up here when you arrive.</p>
      <div class="actions"><a class="btn" href="{{ route('driver.vehicles.create') }}">Add a vehicle</a></div>
    </section>
  @else
    <section class="dcard" aria-labelledby="hn">
      <p class="lab">No parking right now</p>
      <h3 id="hn">Plan your visit</h3>
      <p>Reserving a slot is free. You pay the parking fee in cash at the booth when you leave.</p>
      <div class="actions"><a class="btn" href="{{ route('driver.reserve') }}">Reserve a slot</a></div>
    </section>
  @endif

  @if ($session && $reservation)
    <a class="ditem" href="{{ route('driver.bookings.show', $reservation) }}">
      <div class="top"><span class="code">{{ $reservation->code }}</span> @include('driver.app.partials.status', ['reservation' => $reservation])</div>
      <p class="meta">Also booked: slot {{ $reservation->slot?->code ?? $reservation->slot_code }}, {{ \App\Support\Lot::when($reservation->scheduled_at) }}</p>
    </a>
  @endif

  <section class="dcard" aria-labelledby="hl">
    <div class="bigcount">
      <span class="n" id="hl">{{ $lot['available'] }}</span>
      <div><b>{{ $full ? 'No free slots right now' : 'slots free of '.$lot['total'] }}</b><span>Updated {{ $lot['updated'] }}</span></div>
      @if ($full)<span class="full-tag">LOT FULL</span>@endif
    </div>
    <div class="links"><a class="lnk" href="{{ route('driver.find') }}">Find parking</a>@if ($session)<a class="lnk" href="{{ route('driver.session.locate') }}">Locate my car</a>@endif</div>
  </section>

  <section class="dcard driver-map-preview" aria-labelledby="home-map-title">
    <div class="driver-map-heading">
      <div>
        <p class="lab">Live parking map</p>
        <h3 id="home-map-title">Floor {{ $map['selectedFloor'] }}</h3>
      </div>
      <a class="lnk" href="{{ route('driver.find', ['floor' => $map['selectedFloor']]) }}">Open map</a>
    </div>
    @include('driver.app.partials.floor-tabs', ['floors' => $map['floors'], 'selectedFloor' => $map['selectedFloor'], 'route' => 'driver.home'])
    @include('partials.slot-layout', ['zones' => $map['zones'], 'zonePositions' => $map['zonePositions'], 'item' => 'driver.app.partials.slot-item-view', 'label' => 'Live parking map preview'])
    @include('driver.app.partials.legend')
  </section>
</div>
@endsection
