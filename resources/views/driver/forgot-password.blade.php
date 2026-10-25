@extends('layouts.app')

@section('title', 'Forgot password - Bayline')

@section('body')
<div class="appcard">
  <div class="apphero">
    <a class="brand" href="{{ route('driver.landing') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
    </a>
  </div>
  <div class="stripe" aria-hidden="true"></div>

  <div class="appbody">
    <a class="tt backico" href="{{ route('driver.login') }}" aria-label="Back to sign in" title="Back to sign in"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></a>
    <h1>Forgot password</h1>
    <p class="lede">Enter the email you signed up with. We send a link to that address to choose a new password.</p>

    @if (session('status'))
      <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
    @endif

    <form method="POST" action="{{ route('driver.password.email') }}" data-busy data-busy-label="Sending" novalidate style="margin-top:16px">
      @csrf
      <div class="fld">
        <label class="f" for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" autocomplete="email" autocapitalize="none" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
      </div>
      <button class="btn block" type="submit">Send reset link</button>
    </form>

    <hr class="rule">
    <p class="fine" style="text-align:center">No account yet? <a class="lnk" href="{{ route('driver.register') }}">Create one</a></p>
  </div>
  <div class="cardfoot">@include('partials.theme-toggle')</div>
</div>
@endsection
