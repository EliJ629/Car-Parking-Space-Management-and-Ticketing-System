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

@if (! empty($blocking))
  <section class="dcard" aria-labelledby="busy-t">
    <h2 id="busy-t" style="margin:0 0 6px;font-size:18px">
      {{ $blocking['kind'] === 'parked' ? 'Your car is still parked' : ($blocking['kind'] === 'checked_in' ? 'You are already checked in' : 'You already have a reservation') }}
    </h2>
    <p style="margin:0 0 14px">{{ $blocking['message'] }}</p>
    <div class="actions">
      @if ($blocking['kind'] === 'parked')
        <a class="btn" href="{{ route('driver.session') }}">View my ticket</a>
      @else
        <a class="btn" href="{{ route('driver.bookings.show', $blocking['reservation']) }}">View reservation</a>
      @endif
      <a class="btn ghost" href="{{ route('driver.home') }}">Back to home</a>
    </div>
  </section>
@elseif ($step === 1)
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
    <section class="dcard paint" style="margin-top:6px" aria-labelledby="ontime-1">
      <p class="lab" id="ontime-1">Arrive on time</p>
      <p style="margin:4px 0 0;font-size:14px">Your slot is held for only <b>{{ \App\Support\Lot::graceMinutes() }} minutes</b> after the arrival time you pick. If you have not checked in by then, the reservation expires and the slot is given to other drivers. Pick a time you can keep.</p>
    </section>
    <ul class="checks">
      <li>Reservation is free. Pay the parking fee in cash at the booth.</li>
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
    <h2 class="f" style="font-size:15px;margin:4px 0 8px">1. Pick a slot <small style="font-weight:400;color:var(--muted)">({{ $zones->sum(fn ($group) => $group->where('state', 'available')->count()) }} free at this time)</small></h2>
    <p class="fine" style="margin:0 0 10px">Only free slots can be picked. A slot marked <b>PWD</b> needs a photo of your PWD ID, which the booth attendant checks before approving.</p>
    <div class="lot driver-layout" data-layout-readonly>
      <div class="driver-layout-canvas" data-layout-canvas>
        @foreach ($zones as $zone => $slots)
          @php $zonePosition = $zonePositions[$zone] ?? null; @endphp
          <span class="driver-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
            data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">{{ $zone }}</span>
          @foreach ($slots as $slot)
            @php $free = $slot->state === 'available'; @endphp
            <label class="sl pick driver-layout-slot {{ $cls[$slot->state] ?? '' }} {{ $free ? '' : 'off dim' }}"
                   data-layout-slot data-slot="{{ $slot->code }}" data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
                   data-type="{{ $slot->type }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}">
              <input type="radio" name="slot" value="{{ $slot->id }}" @disabled(! $free) @checked($free && $slotId === $slot->id) aria-label="Slot {{ $slot->code }}, {{ $label[$slot->state] ?? $slot->state }}">
              <b>{{ $slot->code }}</b><span>{{ $label[$slot->state] ?? '' }}</span>
              @if ($slot->type === 'PWD')<em class="pwdtag" title="PWD slot: needs a PWD ID photo">PWD</em>@endif
            </label>
          @endforeach
        @endforeach
      </div>
      @include('driver.app.partials.legend')
    </div>

    <fieldset class="fld" style="border:0;padding:0;margin:22px 0 0">
      <legend class="f" style="font-size:15px;font-weight:700;margin-bottom:8px">2. Pick the vehicle you will use</legend>
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
    <p class="fine" style="margin-top:12px">The next screen asks you to confirm that you will arrive on time.</p>
    <div class="actions"><button class="btn" type="submit">Review reservation</button></div>
  </form>

@else
  @if ($errors->has('understand'))
    <div class="err-box" role="alert"><b>Confirm that you will arrive on time</b>{{ $errors->first('understand') }}</div>
  @endif
  <section class="dcard">
    <p class="lab">Review</p>
    <h3>Slot {{ $slot->code }}</h3>
    <dl class="kv">
      <div><dt>Zone</dt><dd>{{ $slot->zone }}</dd></div>
      <div><dt>Arrival</dt><dd>{{ \App\Support\Lot::when($at) }}</dd></div>
      <div><dt>Vehicle</dt><dd>{{ $vehicle->displayPlate() }}@if ($vehicle->describe()) <small style="color:var(--muted)">{{ $vehicle->describe() }}</small>@endif</dd></div>
      <div><dt>Cost</dt><dd>Free</dd></div>
    </dl>
  </section>
  @if ($slot->type === 'PWD')
    <section class="dcard" style="margin-top:14px"><p class="lab">PWD slot</p>
      <p style="margin:4px 0 0;font-size:14px">This is a PWD slot. You will upload a photo of your PWD ID on the next step. The booth attendant checks it. If it is not accepted, you are notified and must choose a new slot.</p>
    </section>
  @endif
  <section class="dcard paint" style="margin-top:14px" aria-labelledby="ontime-3">
    <p class="lab" id="ontime-3">Before you reserve: arrive on time</p>
    <p style="margin:4px 0 10px;font-size:14px">Your slot is held only for a short grace period. If you do not check in at the booth in time, you lose both the reservation and the slot.</p>
    <ul class="tl">
      <li class="now"><b>Check in opens at {{ \App\Support\Lot::time($opens) }}</b><span>{{ \App\Support\Lot::earlyMinutes() }} minutes before your arrival time</span></li>
      <li class="todo"><b>Arrival time {{ \App\Support\Lot::time($at) }}</b><span>The time you chose</span></li>
      <li class="todo"><b>Last check in {{ \App\Support\Lot::time($holdEnds) }}</b><span>{{ \App\Support\Lot::graceMinutes() }} minutes of grace. After this the reservation expires and slot {{ $slot->code }} is released.</span></li>
    </ul>
  </section>
  <ul class="checks" style="margin-top:16px">
    @foreach ($policies as $p)<li>{{ $p }}</li>@endforeach
  </ul>
  <div class="actions">
    <button class="btn" type="button" data-open="#confirm-res">Confirm reservation</button>
    <a class="btn ghost" href="{{ route('driver.reserve', ['step' => 2, 'date' => $date, 'time' => $time, 'vehicle' => $vehicleId, 'slot' => $slot->id]) }}">Change slot</a>
  </div>

  @push('dialogs')
  <dialog class="dsheet" id="confirm-res" aria-labelledby="cr-t" @if ($errors->has('id_photo') || $errors->has('understand') || ! empty($photoTooLarge)) data-autoopen @endif>
    <form class="in" method="POST" action="{{ route('driver.reserve.store') }}" enctype="multipart/form-data" data-busy data-busy-label="Sending">
      @csrf
      <input type="hidden" name="date" value="{{ $date }}">
      <input type="hidden" name="time" value="{{ $time }}">
      <input type="hidden" name="slot" value="{{ $slot->id }}">
      <input type="hidden" name="vehicle" value="{{ $vehicle->id }}">
      <button class="x" type="button" data-close aria-label="Close">X</button>
      <h2 id="cr-t">Reserve slot {{ $slot->code }}?</h2>
      <p class="sub" style="color:var(--ink)">{{ \App\Support\Lot::when($at) }} for {{ $vehicle->displayPlate() }}. The reservation is free.@if ($slot->type === 'PWD') The booth attendant checks your PWD ID and approves it before your QR code appears.@else It is confirmed right away and your QR code appears as soon as you reserve.@endif</p>
      @if ($slot->type === 'PWD')
        <div class="fld">
          <label class="f" for="id_photo">Photo of your PWD ID</label>
          @php $photoWarning = $errors->first('id_photo') ?: (! empty($photoTooLarge) ? \App\Support\PwdPhoto::tooLargeMessage() : ''); @endphp
          <div class="err-box" id="id-photo-warning" role="alert" style="margin:0 0 10px" @if (! $photoWarning) hidden @endif><b>Photo not accepted</b><span data-text>{{ $photoWarning }}</span></div>
          <input id="id_photo" name="id_photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required data-max-bytes="{{ $photoLimitBytes }}" data-max-label="{{ $photoLimitLabel }}" @if ($photoWarning) aria-invalid="true" @endif>
          <p class="fine" style="margin-top:6px">A clear, readable photo (JPG, PNG or WEBP, up to {{ $photoLimitLabel }}). Only the booth attendant sees it, and it is deleted if the request is turned down.</p>
          <img id="id-preview" alt="Preview of your ID photo" hidden style="max-width:100%;max-height:180px;margin-top:8px;border-radius:6px">
        </div>
      @endif
      <label style="display:flex;gap:10px;align-items:flex-start;margin:12px 0;font-size:14px">
        <input type="checkbox" name="understand" value="1" required style="margin-top:3px;flex:none">
        <span>I will arrive by <b>{{ \App\Support\Lot::time($holdEnds) }}</b>. I understand that if I am not checked in by then, the reservation and the slot are no longer valid.</span>
      </label>
      <div class="actions">
        <button class="btn" type="submit">{{ $slot->type === 'PWD' ? 'Send for approval' : 'Reserve slot' }}</button>
        <button class="btn ghost" type="button" data-close>Not yet</button>
      </div>
    </form>
  </dialog>
  @endpush
@endif
@endsection
