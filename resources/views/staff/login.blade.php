@extends('layouts.app')

@section('title', 'Staff sign in - Bayline')

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
      <h1>Staff<br>sign in</h1>
      <p class="lede">Sign in to open your workspace.</p>
    </div>
    <div class="bays" aria-hidden="true"><span>A-01</span><span>A-02</span><span class="on">A-03</span><span>A-04</span><span>A-05</span></div>
  </aside>

  <main class="auth-main">
    <div class="bar"><button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button></div>
    <div class="mid">
      <div class="card">
        <h1>Sign in</h1>
        <p class="lede">Use the username and password you were given.</p>

        @if (session('status'))
          <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
        @endif
        @error('identity')
          <div class="alert stop" role="alert"><b>{{ $message }}</b></div>
        @enderror

        <form method="POST" action="{{ route('staff.login') }}" data-busy data-busy-label="Signing in" novalidate style="margin-top:16px">
          @csrf
          <div class="fld">
            <label class="f" for="identity">Username or email</label>
            <input id="identity" name="identity" type="text" maxlength="255" value="{{ old('identity') }}" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required @error('identity') aria-invalid="true" @enderror>
          </div>
          <div class="fld">
            <label class="f" for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="255" autocomplete="current-password" required @error('password') aria-invalid="true" @enderror>
            @error('password')<p class="err">{{ $message }}</p>@enderror
          </div>
          <div class="frow">
            <span class="fine">Forgot your password? Ask your supervisor.</span>
          </div>
          <button class="btn block" type="submit">Sign in</button>
        </form>

        <hr class="rule">
        <p class="fine">Looking for the driver app? <a class="lnk" href="{{ route('driver.login') }}">Open the Bayline app</a></p>
        <p class="fine" style="margin-top:8px">Sessions end after 15 minutes without activity.</p>
      </div>
    </div>
  </main>
</div>
@endsection
