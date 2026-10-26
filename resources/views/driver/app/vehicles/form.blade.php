@extends('layouts.driver-app')

@section('title', ($editing ? 'Edit vehicle' : 'Add vehicle').' - Bayline')
@section('tab', 'account')
@section('heading', $editing ? 'Edit vehicle' : 'Add vehicle')
@section('back', route('driver.vehicles'))

@section('content')
@if ($errors->any())
  <div class="err-box" role="alert"><b>Check the highlighted fields</b>Fix the {{ $errors->count() === 1 ? 'problem' : $errors->count().' problems' }} below and save again.</div>
@endif
<form method="POST" action="{{ $editing ? route('driver.vehicles.update', $vehicle) : route('driver.vehicles.store') }}" data-busy data-busy-label="Saving" novalidate>
  @csrf
  @if ($editing) @method('PUT') @endif

  <div class="fld">
    <label class="f" for="plate_number">Plate number</label>
    <input id="plate_number" name="plate_number" type="text" value="{{ old('plate_number', $vehicle->plate_number) }}" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="12" placeholder="ABC 1234" required @error('plate_number') aria-invalid="true" @enderror>
    @error('plate_number')<p class="err">{{ $message }}</p>@enderror
    @if ($locked)<p class="fine" style="margin-top:6px">The plate cannot change while this vehicle has a reservation or a parking session.</p>@endif
  </div>
  @include('partials.vehicle-fields', ['type' => old('vehicle_type', $vehicle->vehicle_type)])
  <div class="fld">
    <label class="f" for="contact_number">Contact number <span class="fine">(optional)</span></label>
    <input id="contact_number" name="contact_number" type="tel" inputmode="tel" maxlength="13" value="{{ old('contact_number', $vehicle->contact_number) }}" placeholder="09175550142" @error('contact_number') aria-invalid="true" @enderror>
    @error('contact_number')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="actions"><button class="btn" type="submit">Save vehicle</button><a class="btn ghost" href="{{ route('driver.vehicles') }}">Cancel</a></div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
@endpush
