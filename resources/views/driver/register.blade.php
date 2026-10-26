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

    <form method="POST" action="{{ route('driver.register') }}" data-busy data-busy-label="Creating your account" novalidate style="margin-top:16px">
      @csrf

      <h2 class="sec-t">About you</h2>
      <div class="fld">
        <label class="f" for="name">Full name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="email">Gmail address</label>
        <input id="email" data-check-url="{{ route('driver.register.check-email') }}" name="email" type="email" value="{{ old('email') }}" maxlength="255" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="maria.santos@gmail.com" data-live-gmail data-live-target="#email-note" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
        <p class="hint" id="email-note">We send a confirmation link to this Gmail address.</p>
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
@endsection

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
@endpush
