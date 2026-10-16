@extends('layouts.driver-app')

@section('title', 'Reserve a slot - Bayline')
@section('tab', 'find')
@section('heading', 'Reserve a slot')
@section('back', $step === 1 ? route('driver.find') : route('driver.reserve', array_filter(['step' => $step - 1, 'date' => $date, 'time' => $time, 'slot' => $slotId ?: null, 'vehicle' => $vehicleId ?? null])))

@section('content')
@php
    $names = ['Date and time', 'Slot and vehicle', 'Review'];
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<div class="steps-bar" aria-label="Step {{ $step }} of 3">
  @foreach ($names as $i => $n)
    <span class="{{ $i + 1 < $step ? 'done' : ($i + 1 === $step ? 'now' : '') }}" @if ($i + 1 === $step) aria-current="step" @endif>{{ $i + 1 }}. {{ $n }}</span>
  @endforeach
</div>

@if ($errors->any() && $step === 1)
  <div class="err-box" role="alert"><b>Check your arrival time</b>{{ $errors->first() }}</div>
@endif

@if ($step === 1)
  <form method="GET" action="{{ route('driver.reserve') }}" novalidate>
    <input type="hidden" name="step" value="2">
    @if ($slotId)<input type="hidden" name="slot" value="{{ $slotId }}">@endif
    <div data-reserve-when data-today="{{ $today->format('Y-m-d') }}" data-now="{{ $nowTime }}">
      <fieldset class="fld" style="border:0;padding:0;margin:0 0 16px">
        <legend class="f" style="font-size:13px;font-weight:600;margin-bottom:6px">Which day?</legend>
        <div class="chipgrid">
          @foreach ($days->take(4) as $d)
            <label class="cp"><input type="radio" name="date" value="{{ $d->format('Y-m-d') }}" @checked($date === $d->format('Y-m-d'))><span><small>{{ $loop->first ? 'Today' : $d->format('D') }}</small><b>{{ $d->format('j') }}</b><small>{{ $d->format('M') }}</small></span></label>
          @endforeach
        </div>
      </fieldset>
      <div class="fld">
        <label class="f" for="other-date">Or pick another date</label>
        <input id="other-date" name="other_date" type="date" min="{{ $today->format('Y-m-d') }}" max="{{ $latest->format('Y-m-d') }}" value="{{ $days->take(4)->contains(fn ($d) => $d->format('Y-m-d') === $date) ? '' : $date }}">
        <p class="fine" style="margin-top:6px">You can reserve up to {{ $advanceDays }} days ahead.</p>
      </div>
      <fieldset class="fld" style="border:0;padding:0;margin:0 0 16px">
        <legend class="f" style="font-size:13px;font-weight:600;margin-bottom:6px">What time will you arrive?</legend>
        <div class="chipgrid t" id="timechips" aria-live="polite"></div>
      </fieldset>
      <div class="fld">
        <label class="f" for="time-input">Or enter a time</label>
        <input id="time-input" name="time" type="time" value="{{ $time }}" required>
      </div>
    </div>
    <ul class="checks">
      <li>Reservation is free. Pay the parking fee in cash at the booth.</li>
      <li>Your slot is held for {{ \App\Support\Lot::graceMinutes() }} minutes after your arrival time.</li>
    </ul>
    <div class="actions"><button class="btn" type="submit">Choose a slot</button></div>
  </form>

@elseif ($step === 2)
  <p class="sub" style="margin:0 0 12px">For <b>{{ \App\Support\Lot::when($at) }}</b>. <a class="lnk" href="{{ route('driver.reserve', ['step' => 1, 'date' => $date, 'time' => $time]) }}">Change</a></p>
  @if ($errors->has('slot') || $errors->has('vehicle'))
    <div class="err-box" role="alert"><b>One more thing</b>{{ $errors->first() }}</div>
  @endif
  <form method="GET" action="{{ route('driver.reserve') }}" novalidate>
    <input type="hidden" name="step" value="3">
    <input type="hidden" name="date" value="{{ $date }}">
    <input type="hidden" name="time" value="{{ $time }}">
    <div class="lot">
      @foreach ($zones as $zone => $slots)
        <div class="zl">{{ strtoupper($zone) }}</div>
        <div class="g">
          @foreach ($slots as $slot)
            @php $free = $slot->state === 'available'; @endphp
            <label class="sl pick {{ $cls[$slot->state] ?? '' }} {{ $free ? '' : 'off dim' }}">
              <input type="radio" name="slot" value="{{ $slot->id }}" @disabled(! $free) @checked($free && $slotId === $slot->id) aria-label="Slot {{ $slot->code }}, {{ $label[$slot->state] ?? $slot->state }}">
              <b>{{ $slot->code }}</b><span>{{ $label[$slot->state] ?? '' }}</span>
            </label>
          @endforeach
        </div>
      @endforeach
      @include('driver.app.partials.legend')
    </div>

    <fieldset class="fld" style="border:0;padding:0;margin:18px 0 0">
      <legend class="f" style="font-size:13px;font-weight:600;margin-bottom:6px">Which vehicle?</legend>
      @if ($noVehicle)
        <div class="alert">You have no saved vehicle. <a class="lnk" href="{{ route('driver.vehicles.create') }}">Add a vehicle</a></div>
      @else
        <div style="display:grid;gap:8px">
          @foreach ($vehicles as $v)
            <label class="cp vh"><input type="radio" name="vehicle" value="{{ $v->id }}" @checked((int) $vehicleId === $v->id || ($loop->first && ! $vehicleId))><span><b style="font-size:24px">{{ $v->displayPlate() }}</b><small style="align-self:center">{{ $v->describe() }}</small></span></label>
          @endforeach
        </div>
      @endif
    </fieldset>
    <div class="actions"><button class="btn" type="submit">Review reservation</button></div>
  </form>

@else
  <section class="dcard">
    <p class="lab">Review</p>
    <h3>Slot {{ $slot->code }}</h3>
    <dl class="kv">
      <div><dt>Zone</dt><dd>{{ $slot->zone }}</dd></div>
      <div><dt>Arrival</dt><dd>{{ \App\Support\Lot::when($at) }}</dd></div>
      <div><dt>Vehicle</dt><dd>{{ $vehicle->displayPlate() }}</dd></div>
      <div><dt>Cost</dt><dd>Free</dd></div>
    </dl>
  </section>
  <ul class="checks" style="margin-top:16px">
    @foreach ($policies as $p)<li>{{ $p }}</li>@endforeach
  </ul>
  <div class="actions">
    <button class="btn" type="button" data-open="#confirm-res">Confirm reservation</button>
    <a class="btn ghost" href="{{ route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'vehicle' => $vehicleId, 'slot' => $slot->id]) }}">Change slot</a>
  </div>

  @push('dialogs')
  <dialog class="dsheet" id="confirm-res" aria-labelledby="cr-t">
    <form class="in" method="POST" action="{{ route('driver.reserve.store') }}" data-busy data-busy-label="Reserving">
      @csrf
      <input type="hidden" name="date" value="{{ $date }}">
      <input type="hidden" name="time" value="{{ $time }}">
      <input type="hidden" name="slot" value="{{ $slot->id }}">
      <input type="hidden" name="vehicle" value="{{ $vehicle->id }}">
      <button class="x" type="button" data-close aria-label="Close">X</button>
      <h2 id="cr-t">Reserve slot {{ $slot->code }}?</h2>
      <p class="sub" style="color:var(--ink)">{{ \App\Support\Lot::when($at) }} for {{ $vehicle->displayPlate() }}. The reservation is free.</p>
      <div class="actions">
        <button class="btn" type="submit">Yes, reserve</button>
        <button class="btn ghost" type="button" data-close>Not yet</button>
      </div>
    </form>
  </dialog>
  @endpush
@endif
@endsection
