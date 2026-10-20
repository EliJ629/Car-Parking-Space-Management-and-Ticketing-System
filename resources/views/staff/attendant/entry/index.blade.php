@extends('layouts.attendant')

@section('title', 'New entry - Booth Attendant')
@section('tab', 'entry')

@section('content')
<h1 class="ba-page-title">New entry</h1>
<p class="ba-sub">Choose how the driver is arriving.</p>

<!-- Step Bar -->
<div class="ba-steps">
  <span class="ba-step active">1. Entry type</span>
  <span class="ba-step">2. Plate</span>
  <span class="ba-step">3. Vehicle details</span>
  <span class="ba-step">4. Slot</span>
  <span class="ba-step">5. Ticket</span>
</div>

<!-- Lot Counts Pills -->
<div class="ba-pills">
  <span class="ba-pill available"><i></i> {{ $counts['available'] }} Available</span>
  <span class="ba-pill reserved"><i class="r"></i> {{ $counts['reserved'] }} Reserved</span>
  <span class="ba-pill occupied"><i class="o"></i> {{ $counts['occupied'] }} Occupied</span>
  <span class="ba-pill pending"><i class="p"></i> {{ $counts['pending_exit'] }} Pending exit</span>
  <span class="ba-pill out"><i class="x"></i> {{ $counts['out_of_service'] }} Out of service</span>
</div>

<!-- Two Choice Cards (BA-11) -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:20px;">
  <!-- Walk-in Card -->
  <div class="ba-card ba-entry-choice-card">
    <h2 style="font-size:26px;margin-bottom:8px;">Walk-in</h2>
    <p style="color:var(--muted);margin-bottom:20px;">The driver has no reservation.</p>
    <ul style="padding-left:20px;line-height:2;color:var(--ink);margin-bottom:28px;">
      <li>Look up or enter the plate</li>
      <li>Pick an Available slot on the lot map</li>
      <li>Print the parking ticket</li>
    </ul>
    <a class="btn primary" href="{{ route('attendant.entry.walk-in') }}" style="min-height:48px;font-size:16px;">
      Start walk-in entry
    </a>
  </div>

  <!-- Reserved Card -->
  <div class="ba-card ba-entry-choice-card">
    <h2 style="font-size:26px;margin-bottom:8px;">Reserved</h2>
    <p style="color:var(--muted);margin-bottom:20px;">The driver has an RSV code or a QR code from the Bayline app.</p>
    <ul style="padding-left:20px;line-height:2;color:var(--ink);margin-bottom:28px;">
      <li>Scan the QR code or type the code</li>
      <li>Verify the vehicle</li>
      <li>Confirm the pre-assigned slot and print the ticket</li>
    </ul>
    <a class="btn primary" href="{{ route('attendant.entry.reserved') }}" style="min-height:48px;font-size:16px;">
      Check in a reservation
    </a>
  </div>
</div>
@endsection
