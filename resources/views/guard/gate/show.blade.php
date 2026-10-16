@extends('layouts.guard')

@section('title', 'Exit request '.$session->ticket_number.' - Bayline')
@section('tab', 'gate')

@section('content')
@php
    $s = $session;
    $vehicle = strtolower(trim(($s->vehicle_color ?? '').' '.($s->vehicle_type ?? '')));
    $plate = \App\Support\Lot::plate($s->plate_number);
    $bad = $errors->has('checks') ? $missing : [];
@endphp
<h1 class="g-title">Exit request {{ $s->ticket_number }}</h1>
<p class="g-sub">Compare the vehicle at the gate with the record on this screen.</p>

@if ($errors->has('checks'))
  <div class="g-banner stop" role="alert" style="margin-top:14px"><b>Not approved</b>{{ $errors->first('checks') }}</div>
@endif

<div class="g-two" style="margin-top:16px">
  <section class="g-card" aria-labelledby="vs">
    <p class="g-lab" id="vs">Vehicle and session</p>
    <div class="g-plate" style="margin-top:10px"><b>{{ $plate }}</b> <span class="g-tag ok">Paid</span></div>
    <dl class="g-kv">
      <dt>Color</dt><dd>{{ ucfirst($s->vehicle_color ?? '-') }}</dd>
      <dt>Type</dt><dd>{{ $s->vehicle_type ?? '-' }}</dd>
      <dt>Slot</dt><dd>{{ $s->slot?->code ?? $s->slot_code }}@if ($s->slot), {{ $s->slot->zone }}@endif</dd>
      <dt>Ticket</dt><dd>{{ $s->ticket_number }}</dd>
      <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($s->entered_at) }}</dd>
      <dt>Exit requested</dt><dd>{{ \App\Support\Lot::stamp($s->payment->paid_at) }}</dd>
      <dt>Payment status</dt><dd>Paid, {{ \App\Support\Lot::peso($s->payment->total) }} {{ $s->payment->method ?? 'cash' }}</dd>
      <dt>Receipt</dt><dd>{{ $s->payment->receipt_number }}</dd>
      <dt>Exit window</dt><dd>Until {{ \App\Support\Lot::time($windowEnd) }}, {{ $minutesLeft < 1 ? 'ending now' : $minutesLeft.' min left' }}</dd>
    </dl>
  </section>

  <section class="g-card" aria-labelledby="vg">
    <form id="verify" method="POST" action="{{ route('guard.gate.approve', $s) }}" novalidate>
      @csrf
      <p class="g-lab" id="vg" style="margin-bottom:10px">Verify at the gate</p>
      <label class="g-check {{ in_array('plate', $bad, true) ? 'bad' : '' }}"><input type="checkbox" name="checks[plate]" value="1"><span>Plate on the vehicle reads {{ $plate }}</span></label>
      <label class="g-check {{ in_array('details', $bad, true) ? 'bad' : '' }}"><input type="checkbox" name="checks[details]" value="1"><span>Color and type match the record: {{ $vehicle ?: 'see record' }}</span></label>
      <label class="g-check {{ in_array('ticket', $bad, true) ? 'bad' : '' }}"><input type="checkbox" name="checks[ticket]" value="1"><span>Ticket number shown by the driver is {{ $s->ticket_number }}</span></label>
      <p class="g-progress" id="progress" role="status" aria-live="polite">0 of 3 checks done</p>
      <div class="g-actions" style="margin-top:8px">
        <button class="btn" type="submit">Good to Go</button>
        <button class="btn ghost" type="button" data-open="#mismatch">Details do not match</button>
      </div>
    </form>
  </section>
</div>
<p style="margin-top:16px"><a class="lnk" href="{{ route('guard.gate') }}">Back to queue</a></p>

@push('dialogs')
<dialog class="gdlg" id="confirm-go" aria-labelledby="cg-t">
  <form class="in" id="confirm-form" method="POST" action="{{ route('guard.gate.approve', $s) }}">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="cg-t">Confirm Good to Go</h2>
    <p>Release slot {{ $s->slot?->code ?? $s->slot_code }} for {{ $plate }}? The gate opens and the slot becomes available.</p>
    <p class="note">This is logged under your name with the time.</p>
    <div class="actions"><button class="btn" type="submit">Good to Go</button><button class="btn ghost" type="button" data-close>Not yet</button></div>
  </form>
</dialog>

<dialog class="gdlg stop" id="mismatch" aria-labelledby="mm-t">
  <form class="in g-form" method="POST" action="{{ route('guard.gate.mismatch', $s) }}">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="mm-t">Plate or details do not match</h2>
    <p>Tick what does not match. The slot stays Pending exit and you can write an incident report next.</p>
    <div style="margin-top:12px">
      <label class="g-check"><input type="checkbox" name="reasons[]" value="plate"><span>Plate number</span></label>
      <label class="g-check"><input type="checkbox" name="reasons[]" value="details"><span>Color or vehicle type</span></label>
      <label class="g-check"><input type="checkbox" name="reasons[]" value="ticket"><span>Ticket number</span></label>
    </div>
    <div class="actions"><button class="btn" type="submit">Write incident report</button><button class="btn ghost" type="button" data-close>Back</button></div>
  </form>
</dialog>
@endpush
@endsection
