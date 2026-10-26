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
    <b class="cash-val">{{ \App\Support\Lot::peso($shiftCash) }}</b>
    <span>Shift cash</span>
  </div>
</div>

<!-- Quick Action Cards (BA-01) -->
<div class="ba-actions">
  <a class="ba-action-card" href="{{ route('attendant.entry.walk-in') }}">
    <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--paint-deep);flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      <h3>New walk-in entry</h3>
    </div>
    <p>Look up a plate and issue a ticket</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.entry.reserved') }}">
    <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--paint-deep);flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
      <h3>Check in a reservation</h3>
    </div>
    <p>Scan the QR code or type RSV code</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.exit.index') }}">
    <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--paint-deep);flex-shrink:0;"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <h3>Exit and payment</h3>
    </div>
    <p>Search by ticket/plate and take cash</p>
  </a>
  <a class="ba-action-card" href="{{ route('attendant.slots.index') }}">
    <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--paint-deep);flex-shrink:0;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <h3>Parking slots</h3>
    </div>
    <p>View live lot and correct a status</p>
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
