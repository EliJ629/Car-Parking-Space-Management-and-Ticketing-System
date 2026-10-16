@extends('layouts.app')

@section('title', 'Choose a new password - Bayline')

@section('body')
<div class="pagebar">
  <a class="back" href="{{ route('driver.login') }}">&larr; Back to sign in</a>
  <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
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
      <button class="btn block" type="submit">Save new password</button>
    </form>
  </div>
</div>
@endsection
