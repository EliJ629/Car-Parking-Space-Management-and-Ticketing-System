@extends('layouts.driver-app')

@section('title', 'My reservations - Bayline')
@section('tab', 'bookings')
@section('heading', 'Reservations')

@section('content')
<nav class="dtabs" aria-label="Reservations">
  <a href="{{ route('driver.bookings') }}" @if ($tab === 'upcoming') aria-current="page" @endif>Upcoming</a>
  <a href="{{ route('driver.bookings', ['tab' => 'past']) }}" @if ($tab === 'past') aria-current="page" @endif>Past</a>
</nav>

@if ($items->isEmpty())
  @if ($tab === 'upcoming')
    @include('driver.app.partials.empty', [
      'title' => $neverBooked ? 'No reservations yet' : 'Nothing coming up',
      'text' => 'Reserving a slot is free. Pick a day, a time and a slot, and show the QR code to the booth attendant when you arrive.',
      'cta' => ['Reserve a slot', route('driver.reserve')],
    ])
  @else
    @include('driver.app.partials.empty', ['title' => 'No past reservations', 'text' => 'Completed, expired and canceled reservations appear here.'])
  @endif
@else
  <ul class="dlist">
    @foreach ($items as $r)
      <li>
        <a class="ditem" href="{{ route('driver.bookings.show', $r) }}">
          <div class="top"><span class="code">{{ $r->code }}</span> @include('driver.app.partials.status', ['reservation' => $r])</div>
          <p>Slot {{ $r->slot?->code ?? $r->slot_code }}, {{ \App\Support\Lot::when($r->scheduled_at) }}</p>
          <p class="meta">{{ \App\Support\Lot::plate($r->plate_number) }}</p>
        </a>
      </li>
    @endforeach
  </ul>
@endif
@endsection
