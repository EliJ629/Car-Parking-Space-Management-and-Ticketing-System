@extends('layouts.app')

@section('title', 'Sign in - Bayline')

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
    <p class="lead">Reserve a bay, follow your parking and keep your receipts.</p>
  </div>
  <div class="stripe" aria-hidden="true"></div>

  <div class="appbody">
    <h1>Sign in</h1>
    <p class="lede">Use the email you signed up with.</p>

    @if (session('status'))
      <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
    @endif
    @error('identity')
      <div class="alert stop" role="alert"><b>{{ $message }}</b></div>
    @enderror

    <form method="POST" action="{{ route('driver.login') }}" data-busy data-busy-label="Signing in" novalidate style="margin-top:16px">
      @csrf
      <div class="fld">
        <label class="f" for="identity">Email</label>
        <input id="identity" name="identity" type="email" value="{{ old('identity') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required @error('identity') aria-invalid="true" @enderror>
      </div>
      <div class="fld">
        <label class="f" for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" @enderror>
        @error('password')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="frow" style="justify-content:flex-end">
        <a class="lnk" href="{{ route('driver.password.request') }}">Forgot password?</a>
      </div>
      <button class="btn block" type="submit">Sign in</button>
    </form>

    <p class="fine" style="text-align:center;margin:18px 0 10px">New to Bayline?</p>
    <a class="btn ghost block" href="{{ route('driver.register') }}">Create an account</a>
  </div>

  @include('partials.find-compact')
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/find-my-car.js') }}?v={{ filemtime(public_path('js/find-my-car.js')) }}"></script>
@endpush
