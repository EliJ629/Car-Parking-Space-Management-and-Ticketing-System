@extends('layouts.app')

@section('title', 'Forgot password - Bayline')

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
      <h1>Forgot<br>password</h1>
      <p class="lede">Booth attendants and guards can choose a new password by email.</p>
    </div>
    <div class="bays" aria-hidden="true"><span>A-01</span><span>A-02</span><span class="on">A-03</span><span>A-04</span><span>A-05</span></div>
  </aside>

  <main class="auth-main">
    <div class="mid">
      <div class="card">
        <h1>Forgot password</h1>
        <p class="lede">Enter the email on your staff account. We send a link to that address to choose a new password.</p>

        @if (session('status'))
          <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
        @endif

        <form method="POST" action="{{ route('staff.password.email') }}" data-busy data-busy-label="Sending" novalidate style="margin-top:16px">
          @csrf
          <div class="fld">
            <label class="f" for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" autocomplete="email" autocapitalize="none" spellcheck="false" autofocus required @error('email') aria-invalid="true" @enderror>
            @error('email')<p class="err">{{ $message }}</p>@enderror
          </div>
          <button class="btn block" type="submit">Send reset link</button>
        </form>

        <hr class="rule">
        <p class="fine">No email on your account, or you are a manager? Ask your supervisor to reset it.</p>
        <p class="fine" style="margin-top:8px"><a class="lnk" href="{{ route('staff.login') }}">&larr; Back to sign in</a></p>
        <div class="cardfoot">@include('partials.theme-toggle')</div>
      </div>
    </div>
  </main>
</div>
@endsection
