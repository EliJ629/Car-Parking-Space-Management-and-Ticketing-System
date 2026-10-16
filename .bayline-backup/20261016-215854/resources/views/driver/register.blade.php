@extends('layouts.app')

@section('title', 'Create an account - Bayline')

@section('body')
<div class="pagebar">
  <a class="back" href="{{ route('driver.landing') }}">&larr; Back to home</a>
  <button class="tt" id="theme" type="button">Dark mode</button>
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
        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autocapitalize="none" spellcheck="false" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="phone">Mobile number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="0917 123 4567" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" @enderror>
        @error('phone')<p class="err">{{ $message }}</p>@else<p class="hint">Used only to tell you about your reservation.</p>@enderror
      </div>

      <h2 class="sec-t">Your car</h2>
      <div class="grid2">
        <div class="fld">
          <label class="f" for="plate_number">Plate number</label>
          <input id="plate_number" name="plate_number" type="text" class="tin" value="{{ old('plate_number') }}" placeholder="ABC 1234" maxlength="12" autocapitalize="characters" autocomplete="off" required @error('plate_number') aria-invalid="true" @enderror>
          @error('plate_number')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div class="fld">
          <label class="f" for="vehicle_type">Vehicle type</label>
          <select id="vehicle_type" name="vehicle_type" required @error('vehicle_type') aria-invalid="true" @enderror>
            <option value="">Choose</option>
            @foreach ($vehicleTypes as $type)
              <option value="{{ $type }}" @selected(old('vehicle_type') === $type)>{{ $type }}</option>
            @endforeach
          </select>
          @error('vehicle_type')<p class="err">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="fld">
        <label class="f" for="make_model">Car model</label>
        <input id="make_model" name="make_model" type="text" value="{{ old('make_model') }}" placeholder="Toyota Vios" maxlength="60" autocomplete="off" required @error('make_model') aria-invalid="true" @enderror>
        @error('make_model')<p class="err">{{ $message }}</p>@else<p class="hint">Make and model, for example Toyota Vios.</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="color">Colour</label>
        <input id="color" name="color" type="text" value="{{ old('color') }}" placeholder="Black" autocomplete="off" required @error('color') aria-invalid="true" @enderror>
        @error('color')<p class="err">{{ $message }}</p>@enderror
        <p class="hint">You can add more cars after you sign in.</p>
      </div>

      <h2 class="sec-t">Password</h2>
      <div class="fld">
        <label class="f" for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
        @error('password')<p class="err">{{ $message }}</p>@else<p class="hint">At least 8 characters, with letters and numbers.</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <div class="frow">
        <label class="ck"><input type="checkbox" data-toggle-password="#password,#password_confirmation"> Show passwords</label>
      </div>

      <button class="btn block" type="submit">Create account</button>
    </form>

    <hr class="rule">
    <p class="fine" style="text-align:center">Already have an account? <a class="lnk" href="{{ route('driver.login') }}">Sign in</a></p>
  </div>
</div>
@endsection
