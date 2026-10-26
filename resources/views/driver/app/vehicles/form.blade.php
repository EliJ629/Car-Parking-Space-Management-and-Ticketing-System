@extends('layouts.driver-app')

@section('title', ($editing ? 'Edit vehicle' : 'Add vehicle').' - Bayline')
@section('tab', 'account')
@section('heading', $editing ? 'Edit vehicle' : 'Add vehicle')
@section('back', route('driver.vehicles'))

@section('content')
@if ($errors->any())
  <div class="err-box" role="alert"><b>Check the highlighted fields</b>Fix the {{ $errors->count() === 1 ? 'problem' : $errors->count().' problems' }} below and save again.</div>
@endif
<form method="POST" action="{{ $editing ? route('driver.vehicles.update', $vehicle) : route('driver.vehicles.store') }}" data-busy data-ajax data-busy-label="Saving" data-confirm-details="#details-confirm" @unless ($editing) data-confirm-always @endunless data-plate-original="{{ $vehicle->plate_number }}" novalidate>
  @csrf
  @if ($editing) @method('PUT') @endif
  <input type="hidden" name="details_confirmed" value="">

  @include('partials.vehicle-fields', ['type' => old('vehicle_type', $vehicle->vehicle_type)])
  <div class="fld">
    <label class="f" for="plate_number">Plate number</label>
    @if ($permanent)
      {{-- A permanent plate is final. It is shown, not edited, and it is not checked by plate.js (an older plate may predate today's list). --}}
      <input id="plate_number" name="plate_number" type="text" value="{{ $vehicle->displayPlate() }}" readonly aria-readonly="true" @error('plate_number') aria-invalid="true" @enderror>
      @error('plate_number')<p class="err">{{ $message }}</p>@else<p class="hint">A saved plate cannot be changed. It stays with the vehicle.</p>@enderror
    @else
      <input id="plate_number" name="plate_number" type="text" data-plate data-plate-type="#vehicle_type" data-plate-hint="#plate-hint" data-plate-temp="#no_plate" @if ($temporary) data-plate-locked="{{ $vehicle->plate_number }}" @endif value="{{ old('plate_number', $vehicle->plate_number) }}" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="20" placeholder="Choose the vehicle type first" @if (! (count(old()) ? old('no_plate') : $editing)) required @endif @error('plate_number') aria-invalid="true" @enderror>
      @error('plate_number')<p class="err">{{ $message }}</p>@else<p class="hint" id="plate-hint">Choose the vehicle type first. The plate formats depend on it.</p>@enderror
      @if ($locked)<p class="fine" style="margin-top:6px">The plate cannot change while this vehicle has a reservation or a parking session.</p>@endif
      @if ($temporary)<p class="fine" style="margin-top:6px">This is a temporary plate. When the LTO issues the permanent plate, untick the box below and enter it. Violations on the temporary plate stay with this vehicle.</p>@endif
    @endif
  </div>
  @unless ($permanent)
    @include('partials.no-plate-check', ['checked' => $editing])
  @endunless
  <div class="fld">
    <label class="f" for="contact_number">Contact number <span class="fine">(optional)</span></label>
    <input id="contact_number" name="contact_number" type="tel" data-mobile data-optional inputmode="tel" maxlength="13" value="{{ old('contact_number', $vehicle->contact_number) }}" placeholder="09175550142" @error('contact_number') aria-invalid="true" @enderror>
    @error('contact_number')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="actions"><button class="btn" type="submit">Save vehicle</button><a class="btn ghost" href="{{ route('driver.vehicles') }}">Cancel</a></div>
</form>
@endsection

@push('dialogs')
@include('partials.details-confirm', ['note' => 'Check every line. A permanent plate cannot be changed after it is saved.'])
@endpush

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
<script src="{{ asset('js/details-confirm.js') }}?v={{ filemtime(public_path('js/details-confirm.js')) }}"></script>
@endpush
