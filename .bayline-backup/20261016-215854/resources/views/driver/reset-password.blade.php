@extends('layouts.app')

@section('title', 'Choose a new password - Bayline')

@section('body')
<div class="pagebar">
  <a class="back" href="{{ route('driver.login') }}">&larr; Back to sign in</a>
  <button class="tt" id="theme" type="button">Dark mode</button>
</div>

<div class="appcard">
  <div class="apphero">
    <a class="brand" href="{{ route('driver.landing') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
    </a>
  </div>
  <div class="stripe" aria-hidden="true"></div>

  <div class="appbody">
    <h1>New password</h1>
    <p class="lede">Choose a password you have not used before.</p>

    <form method="POST" action="{{ route('driver.password.update') }}" data-busy data-busy-label="Saving" novalidate style="margin-top:16px">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <div class="fld">
        <label class="f" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" autocapitalize="none" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
        @error('password')<p class="err">{{ $message }}</p>@else<p class="hint">At least 8 characters, with letters and numbers.</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <div class="frow">
        <label class="ck"><input type="checkbox" data-toggle-password="#password,#password_confirmation"> Show passwords</label>
      </div>
      <button class="btn block" type="submit">Save new password</button>
    </form>
  </div>
</div>
@endsection
