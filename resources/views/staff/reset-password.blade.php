@extends('layouts.app')

@section('title', 'Choose a new password - Bayline')

@section('body')
<div class="auth-split">
  <aside class="auth-side">
    <div class="in">
      <a class="brand" href="{{ route('staff.landing') }}">
        <span class="logo">@include('partials.logo')</span>
        <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
      </a>
      <div class="lockbox" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f2b705" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.2" fill="#f2b705"/></svg>
      </div>
      <h1>New<br>password</h1>
      <p class="lede">Choose a password you have not used before.</p>
    </div>
    <div class="bays" aria-hidden="true"><span>A-01</span><span>A-02</span><span class="on">A-03</span><span>A-04</span><span>A-05</span></div>
  </aside>

  <main class="auth-main">
    <div class="mid">
      <div class="card">
        <h1>New password</h1>
        <p class="lede">For <b>{{ $maskedEmail }}</b>. Choose a new password, then confirm it.</p>

        @error('email')
          <div class="alert stop" role="alert"><b>{{ $message }}</b></div>
        @enderror

        <form method="POST" action="{{ route('staff.password.update') }}" data-busy data-busy-label="Saving" novalidate style="margin-top:16px">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          <input type="hidden" name="email" value="{{ $email }}">
          <div class="fld">
            <label class="f" for="password">New password</label>
            <input id="password" name="password" type="password" minlength="12" maxlength="64" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
            @error('password')<p class="err">{{ $message }}</p>@else<p class="hint">At least 12 characters with a letter and a number.</p>@enderror
          </div>
          <div class="fld">
            <label class="f" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="64" autocomplete="new-password" required>
          </div>
          <button class="btn block" type="submit">Save new password</button>
        </form>
        <div class="cardfoot">@include('partials.theme-toggle')</div>
      </div>
    </div>
  </main>
</div>
@endsection
