@extends('layouts.guard')

@section('title', ($justApproved ? 'Exit approved' : 'Exited').' - Bayline')
@section('tab', 'gate')

@section('content')
@php $s = $session; @endphp
@if ($justApproved)
  <div class="g-bigcheck" aria-hidden="true"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg></div>
  <h1 class="g-title">Exit approved</h1>
  <p class="g-sub">Open the gate for {{ \App\Support\Lot::plate($s->plate_number) }}. Slot {{ $s->slot?->code ?? $s->slot_code }} is available again.</p>
@else
  <h1 class="g-title">Already exited</h1>
  <p class="g-sub">This session is closed.</p>
@endif

<section class="g-card" style="margin-top:16px;max-width:640px">
  <dl class="g-kv">
    <dt>Plate</dt><dd>{{ \App\Support\Lot::plate($s->plate_number) }}</dd>
    <dt>Ticket</dt><dd>{{ $s->ticket_number }}</dd>
    <dt>Slot</dt><dd>{{ $s->slot?->code ?? $s->slot_code }}</dd>
    <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($s->entered_at) }}</dd>
    <dt>Exit</dt><dd>{{ \App\Support\Lot::stamp($s->exited_at) }}</dd>
    <dt>Approved by</dt><dd>{{ $s->exitApprover?->name ?? '-' }}</dd>
    @if ($s->payment)<dt>Receipt</dt><dd>{{ $s->payment->receipt_number }}, {{ \App\Support\Lot::peso($s->payment->total) }}</dd>@endif
  </dl>
  <div class="g-actions">
    <a class="btn" href="{{ route('guard.gate') }}">Back to queue</a>
    <a class="btn ghost" href="{{ route('guard.gate', ['tab' => 'search']) }}">Search a vehicle</a>
  </div>
</section>
@endsection
