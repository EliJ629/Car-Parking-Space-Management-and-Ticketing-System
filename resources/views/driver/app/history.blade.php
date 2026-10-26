@extends('layouts.driver-app')

@section('title', 'Parking history - Bayline')
@section('tab', 'account')
@section('heading', 'History')
@section('back', route('driver.account'))

@section('content')
<form method="GET" action="{{ route('driver.history') }}" class="actions two" style="margin:0 0 16px" aria-label="Filter history">
  <div class="fld" style="margin:0">
    <label class="f" for="range">Period</label>
    <select id="range" name="range" data-autosubmit>
      @foreach (['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'] as $v => $l)
        <option value="{{ $v }}" @selected($range === (string) $v)>{{ $l }}</option>
      @endforeach
    </select>
  </div>
  <div class="fld" style="margin:0">
    <label class="f" for="vehicle">Vehicle</label>
    <select id="vehicle" name="vehicle" data-autosubmit>
      <option value="0">All vehicles</option>
      @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected($vehicleId === $v->id)>{{ $v->displayPlate() }}</option>@endforeach
    </select>
  </div>
  @if ($range !== '30' || $vehicleId)
    <div class="fld" style="margin:0;grid-column:1/-1"><a class="btn ghost sm" href="{{ route('driver.history') }}">Clear filters</a></div>
  @endif
  <noscript><button class="btn sm" type="submit">Apply</button></noscript>
</form>

@if ($payments->isEmpty())
  @include('driver.app.partials.empty', ['title' => 'No parking history', 'text' => 'Completed visits and their receipts appear here after you leave the lot.'])
@else
  <p class="fine" style="margin:0 0 10px">{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('visit', $payments->count()) }}, {{ \App\Support\Lot::peso($payments->sum('total')) }} in total</p>
  <ul class="dlist">
    @foreach ($payments as $p)
      <li>
        <a class="ditem" href="{{ route('driver.receipt', $p) }}">
          <div class="top"><span class="code">{{ \App\Support\Lot::peso($p->total) }}</span><span class="meta">{{ \App\Support\Lot::stamp($p->paid_at) }}</span></div>
          <p>Slot {{ $p->session?->slot?->code ?? $p->session?->slot_code }}, {{ \App\Support\Lot::plate($p->session?->plate_number) }}</p>
          <p class="meta">{{ $p->receipt_number }} - ticket {{ $p->session?->ticket_number }}</p>
        </a>
      </li>
    @endforeach
  </ul>
@endif
@endsection
