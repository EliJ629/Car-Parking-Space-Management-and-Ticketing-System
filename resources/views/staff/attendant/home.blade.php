@extends('layouts.attendant')

@section('title', 'Home - Booth Attendant')
@section('tab', 'home')

@section('content')
<h1 class="ba-page-title">Home</h1>
<p class="ba-sub">Shift started at {{ $shiftStartTime }}. Slots update on their own when vehicles enter, pay and exit.</p>

<!-- Top Status Tiles (BA-01) -->
<div class="ba-tiles">
  <div class="ba-tile">
    <b>{{ $vehiclesInLot }}</b>
    <span>Vehicles in lot</span>
  </div>
  <div class="ba-tile @if($pendingExits > 0) warn @endif">
    <b>{{ $pendingExits }}</b>
    <span>Pending exits</span>
  </div>
  <div class="ba-tile">
    <b>{{ $availableSlots }}</b>
    <span>Available slots</span>
  </div>
  <div class="ba-tile">
    <b>{{ \App\Support\Lot::peso($shiftCash) }}</b>
    <span>Shift cash</span>
  </div>
</div>

<!-- Quick Action Cards (BA-01) -->
<div class="ba-actions">
  <a class="ba-action-card" href="{{ route('attendant.entry.walk-in') }}">
    <h3>New walk-in entry</h3>
    <p>Look up a plate and issue a ticket</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.entry.reserved') }}">
    <h3>Check in a reservation</h3>
    <p>Scan the QR code or type the RSV code</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.exit.index') }}">
    <h3>Exit and payment</h3>
    <p>Search by ticket or plate and take cash</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.slots.index') }}">
    <h3>Parking slots</h3>
    <p>View the lot and correct a status</p>
  </a>
</div>

<!-- Split Content: Reserved Arrivals & Alerts (BA-01) -->
<div class="ba-split">
  <!-- Left: Reserved Arrivals -->
  <div class="ba-card">
    <h2>Reserved arrivals</h2>

    <h3>Next 30 minutes</h3>
    <div class="ba-list-group">
      @forelse ($next30MinReservations as $r)
        @php
            $opens = $r->scheduled_at->copy()->subMinutes(\App\Support\Lot::earlyMinutes());
            $ends = $r->scheduled_at->copy()->addMinutes(\App\Support\Lot::graceMinutes());
        @endphp
        <div class="ba-item">
          <div class="details">
            <b>{{ $r->driver?->name ?? 'Reserved Driver' }}, {{ \App\Support\Lot::time($r->scheduled_at) }}, slot {{ $r->slot_code }}</b>
            <p>{{ \App\Support\Lot::plate($r->plate_number) }}, {{ $r->vehicle?->color }} {{ $r->vehicle?->vehicle_type ?? 'car' }}, {{ $r->code }}. Check-in is open now. Held until {{ \App\Support\Lot::time($ends) }}.</p>
          </div>
          <span class="badge reserved">Reserved</span>
        </div>
      @empty
        <p style="color:var(--muted);font-size:14px;margin:0 0 10px;">No reserved arrivals in the next 30 minutes.</p>
      @endforelse
    </div>

    <h3>Later today</h3>
    <div class="ba-list-group">
      @forelse ($laterTodayReservations as $r)
        @php
            $opens = $r->scheduled_at->copy()->subMinutes(\App\Support\Lot::earlyMinutes());
        @endphp
        <div class="ba-item">
          <div class="details">
            <b>{{ $r->driver?->name ?? 'Reserved Driver' }}, {{ \App\Support\Lot::time($r->scheduled_at) }}, slot {{ $r->slot_code }}</b>
            <p>{{ \App\Support\Lot::plate($r->plate_number) }}, {{ $r->vehicle?->color }} {{ $r->vehicle?->vehicle_type ?? 'car' }}, {{ $r->code }}. Check-in opens at {{ \App\Support\Lot::time($opens) }}.</p>
          </div>
          <span class="badge reserved">Reserved</span>
        </div>
      @empty
        <p style="color:var(--muted);font-size:14px;margin:0;">No more reservations scheduled for today.</p>
      @endforelse
    </div>
  </div>

  <!-- Right: Alerts Widget -->
  <div class="ba-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h2 style="margin:0;">Alerts</h2>
      <button type="button" data-open="#alerts-dlg" class="btn ghost sm" style="font-size:12px;padding:4px 10px;min-height:30px;">View all</button>
    </div>
    <ul class="ba-alerts" style="margin:0;">
      @forelse ($alertItems as $a)
        <li class="{{ $a['unread'] ? 'unread' : '' }}">
          <a href="{{ $a['url'] }}">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
              <b>{{ $a['title'] }}</b>
              <small>{{ $a['time'] }}</small>
            </div>
            <span>{{ $a['body'] }}</span>
          </a>
        </li>
      @empty
        <li class="none">No active alerts right now.</li>
      @endforelse
    </ul>
  </div>
</div>
@endsection
