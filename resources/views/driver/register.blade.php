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
    <a class="tt backico" href="{{ route('driver.landing') }}" aria-label="Back to home" title="Back to home"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></a>
    <h1>Create an account</h1>
    <p class="lede">It takes a minute. You pay cash at the booth when you leave.</p>

    @include('partials.google-button')
    <p class="fine" style="text-align:center;margin:10px 0 0">Or fill in the form below.</p>

    @if ($errors->any())
      <div class="alert stop" role="alert"><b>Please fix the {{ $errors->count() === 1 ? 'highlighted field' : 'highlighted fields' }} below.</b></div>
    @endif

    <form method="POST" action="{{ route('driver.register') }}" data-busy data-ajax data-busy-label="Creating your account" data-confirm-details="#details-confirm" data-confirm-always novalidate style="margin-top:16px">
      @csrf
      <input type="hidden" name="details_confirmed" value="">

      <h2 class="sec-t">About you</h2>
      @include('driver.partials.name-fields')
      <div class="fld">
        <label class="f" for="email">Gmail address</label>
        <input id="email" data-check-url="{{ route('driver.register.check-email') }}" name="email" type="email" value="{{ old('email') }}" maxlength="255" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="maria.santos@gmail.com" data-live-gmail data-live-target="#email-note" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
        <p class="hint" id="email-note">We send a confirmation link to this Gmail address.</p>
      </div>
      <div class="fld">
        <label class="f" for="phone">Mobile number</label>
        <input id="phone" name="phone" type="tel" data-mobile value="{{ old('phone') }}" placeholder="09171234567" maxlength="13" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" @enderror>
        @error('phone')<p class="err">{{ $message }}</p>@else<p class="hint">Digits only. Start with 09 (11 digits), 63 (12 digits) or +63 (13 characters). Used only to tell you about your reservation.</p>@enderror
      </div>

      <h2 class="sec-t">Your vehicle</h2>
      @include('partials.vehicle-fields', ['type' => old('vehicle_type')])
      <div class="fld">
        <label class="f" for="plate_number">Plate number</label>
        <input id="plate_number" name="plate_number" type="text" data-plate data-plate-type="#vehicle_type" data-plate-hint="#plate-hint" data-plate-temp="#no_plate" class="tin" value="{{ old('plate_number') }}" placeholder="Choose the vehicle type first" maxlength="20" autocapitalize="characters" autocomplete="off" @if (! old('no_plate')) required @endif @error('plate_number') aria-invalid="true" @enderror>
        @error('plate_number')<p class="err">{{ $message }}</p>@else<p class="hint" id="plate-hint">Choose the vehicle type first. The plate formats depend on it.</p>@enderror
      </div>
      @include('partials.no-plate-check')
      <p class="hint" style="margin:-4px 0 14px">You can add more vehicles after you sign in.</p>

      <h2 class="sec-t">Password</h2>
      <div class="fld">
        <label class="f" for="password">Password</label>
        <input id="password" name="password" type="password" maxlength="64" autocomplete="new-password" data-pwcheck="#pwrules" data-pwconfirm="#password_confirmation" data-pwmatch="#pw-match" required @error('password') aria-invalid="true" @enderror>
        @error('password')<p class="err">{{ $message }}</p>@enderror
        @include('partials.password-rules')
      </div>
      <div class="fld">
        <label class="f" for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" maxlength="64" autocomplete="new-password" required>
        <p class="hint" id="pw-match" aria-live="polite"></p>
      </div>

      <button class="btn block" type="submit">Create account</button>
    </form>

    <hr class="rule">
    <p class="fine" style="text-align:center">Already have an account? <a class="lnk" href="{{ route('driver.login') }}">Sign in</a></p>
  </div>
  <div class="cardfoot">@include('partials.theme-toggle')</div>
</div>
@include('partials.details-confirm', ['note' => 'Check every line. We send a confirmation link to your Gmail address when you confirm.'])
@endsection

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
<script src="{{ asset('js/details-confirm.js') }}?v={{ filemtime(public_path('js/details-confirm.js')) }}"></script>
@endpush
