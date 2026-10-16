@extends('layouts.guard')

@section('title', 'Exit blocked - Bayline')
@section('tab', 'gate')

@section('content')
@php $s = $session; $plate = \App\Support\Lot::plate($s->plate_number); @endphp
<h1 class="g-title">Exit request {{ $s->ticket_number }}</h1>
<p class="g-sub">This vehicle cannot be released yet.</p>

<div class="g-banner stop" role="alert" style="margin-top:14px">
  <b>Exit approval blocked</b>
  @if ($windowEnded)The exit window ended, so the session is open again. @else {{ $plate }} has not paid. @endif
  The amount due now is {{ \App\Support\Lot::peso($due) }}. The slot stays {{ $s->slot ? 'occupied' : 'taken' }} until the booth records a payment.
</div>

<section class="g-card" style="margin-top:14px">
  <div class="g-plate"><b>{{ $plate }}</b> <span class="g-tag bad">Unpaid</span></div>
  <dl class="g-kv cols">
    <dt>Ticket</dt><dd>{{ $s->ticket_number }}</dd>
    <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($s->entered_at) }}</dd>
    <dt>Slot</dt><dd>{{ $s->slot?->code ?? $s->slot_code }}</dd>
    <dt>Amount due</dt><dd>{{ \App\Support\Lot::peso($due) }}</dd>
  </dl>
  <div class="g-actions">
    <a class="btn" href="{{ route('guard.gate') }}">Back to queue</a>
    <a class="btn ghost" href="{{ route('guard.incidents.create', ['category' => 'Unpaid vehicle attempting exit', 'plate' => $s->plate_number, 'location' => $s->slot?->code ?? $s->slot_code, 'session' => $s->id]) }}">Report incident</a>
  </div>
</section>
@endsection
