@extends('layouts.app')

@section('title', 'Create an account - Bayline')

@section('body')
<div class="pagebar">
  <a class="back" href="{{ route('driver.landing') }}">&larr; Back to home</a>
  <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
</div>

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
    <h1>Create an account</h1>
    <p class="lede">It takes a minute. You pay cash at the booth when you leave.</p>

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
        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="maria.santos@gmail.com" data-live-gmail data-live-target="#email-note" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
        <p class="hint" id="email-note">We send a 6 digit code to this Gmail address.</p>
      </div>
      <div class="fld">
        <label class="f" for="phone">Mobile number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="0917 123 4567" maxlength="17" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" @enderror>
        @error('phone')<p class="err">{{ $message }}</p>@else<p class="hint">Used only to tell you about your reservation.</p>@enderror
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
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vehicle-form.js') }}?v={{ filemtime(public_path('js/vehicle-form.js')) }}"></script>
@endpush
