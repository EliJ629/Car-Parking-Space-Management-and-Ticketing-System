@extends('layouts.app')

@section('title', 'Create an account - Bayline')

@section('body')
<div class="appcard">
  <div class="apphero">
    <a class="brand" href="{{ route('driver.landing') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
    </a>
    <p class="lead">One account for your reservations, your cars and your receipts. Reservations are free.</p>
  </div>
  <div class="stripe" aria-hidden="true"></div>

  <div class="appbody">
    <form method="POST" action="{{ route('driver.google.cancel') }}" style="margin:0">
      @csrf
      <button class="tt backico" type="submit" aria-label="Cancel and go back" title="Cancel and go back"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></button>
    </form>
    <h1>Create an account</h1>
    <p class="lede">It takes a minute. You pay cash at the booth when you leave.</p>

    <div class="alert ok" role="status"><b>Signed in with Google.</b> We filled in your name and Gmail address. Add your mobile number and vehicle to finish.</div>

    @if ($errors->any())
      <div class="alert stop" role="alert"><b>Please fix the {{ $errors->count() === 1 ? 'highlighted field' : 'highlighted fields' }} below.</b></div>
    @endif

    <form method="POST" action="{{ route('driver.google.store') }}" data-busy data-busy-label="Creating your account" novalidate style="margin-top:16px">
      @csrf

      <h2 class="sec-t">About you</h2>
      <div class="fld">
        <label class="f" for="name">Full name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $googleName) }}" maxlength="120" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="err">{{ $message }}</p>@else<p class="hint">From your Google account. You can change it.</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="email">Gmail address</label>
        <input id="email" type="email" value="{{ $email }}" readonly aria-readonly="true" tabindex="-1">
        <p class="hint">Confirmed by Google, so no code is needed.</p>
      </div>
      <div class="fld">
        <label class="f" for="phone">Mobile number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="09171234567" maxlength="13" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" @enderror>
        @error('phone')<p class="err">{{ $message }}</p>@else<p class="hint">Digits only. Start with 09 (11 digits) or +63 (13 characters). Used only to tell you about your reservation.</p>@enderror
      </div>

      <h2 class="sec-t">Your vehicle</h2>
      <div class="fld">
        <label class="f" for="plate_number">Plate number</label>
        <input id="plate_number" name="plate_number" type="text" class="tin" value="{{ old('plate_number') }}" placeholder="ABC 1234" maxlength="12" autocapitalize="characters" autocomplete="off" required @error('plate_number') aria-invalid="true" @enderror>
        @error('plate_number')<p class="err">{{ $message }}</p>@else<p class="hint">Short plates of high offices, such as 5 or SENATE 3, are accepted.</p>@enderror
      </div>
      @include('partials.vehicle-fields', ['type' => old('vehicle_type')])
      <p class="hint" style="margin:-4px 0 14px">You can add more vehicles after you sign in.</p>

      <button class="btn block" type="submit">Create account</button>
      <p class="fine" style="text-align:center;margin-top:12px">You sign in with Google, so there is no password to remember.</p>
    </form>
  </div>
  <div class="cardfoot">@include('partials.theme-toggle')</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
@endpush
