@extends('layouts.driver-app')

@section('title', 'My vehicles - Bayline')
@section('tab', 'account')
@section('heading', 'My vehicles')
@section('back', route('driver.account'))

@section('content')
@if ($vehicles->isEmpty())
  @include('driver.app.partials.empty', [
    'title' => 'Add your first vehicle',
    'text' => 'Save your plate number once. Reservations and your parking ticket use it, and the booth attendant finds you faster.',
    'cta' => ['Add a vehicle', route('driver.vehicles.create')],
  ])
@else
  <ul class="dlist">
    @foreach ($vehicles as $v)
      <li class="ditem">
        <div style="display:flex;gap:14px;align-items:center">
          <div class="plate"><small>{{ strtoupper($v->vehicle_type) }}</small><b>{{ $v->displayPlate() }}</b></div>
          <div style="min-width:0">
            <p style="font-weight:700;margin:0">{{ $v->make_model ?: $v->color.' '.strtolower($v->vehicle_type) }}</p>
            <p class="meta" style="margin:2px 0 0">{{ $v->color }}@if ($v->contact_number), {{ \App\Support\Lot::phone($v->contact_number) }}@endif</p>
            @unless ($v->hasPlate())<a class="status-tag" style="margin-top:6px;display:inline-block" href="{{ route('driver.vehicles.edit', $v) }}">No plate yet - add plate number</a>@endunless
            @if ($v->hasTemporaryPlate())<a class="status-tag" style="margin-top:6px;display:inline-block" href="{{ route('driver.vehicles.edit', $v) }}">Temporary plate - add permanent plate</a>@endif
            @if ($v->in_use)<span class="status-tag st-inuse" style="margin-top:6px">{{ $v->in_use['type'] === 'reservation' ? 'Reserved' : 'Parked now' }}</span>@endif
          </div>
        </div>
        <div class="actions two">
          <a class="btn ghost sm" href="{{ route('driver.vehicles.edit', $v) }}">Edit</a>
          <button class="btn ghost sm" type="button" data-open="#rm-{{ $v->id }}">Remove</button>
        </div>
      </li>
    @endforeach
  </ul>
  <div class="actions"><a class="btn" href="{{ route('driver.vehicles.create') }}">Add a vehicle</a></div>

  @push('dialogs')
  @foreach ($vehicles as $v)
    <dialog class="dsheet stop" id="rm-{{ $v->id }}" aria-labelledby="rm-{{ $v->id }}-t">
      <form class="in" method="POST" action="{{ route('driver.vehicles.destroy', $v) }}" data-busy data-busy-label="Removing">
        @csrf @method('DELETE')
        <h2 id="rm-{{ $v->id }}-t">Remove {{ $v->displayPlate() }}?</h2>
        <p class="sub" style="color:var(--ink)">The vehicle leaves your list. Your parking history keeps its plate number.</p>
        <div class="actions">
          <button class="btn stop" type="submit">Remove vehicle</button>
          <button class="btn ghost" type="button" data-close>Keep it</button>
        </div>
      </form>
    </dialog>
  @endforeach
  @endpush
@endif
@endsection
