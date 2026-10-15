@extends('layouts.app')

@section('title', 'Forgot password - Bayline')

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
    <h1>Forgot password</h1>
    <p class="lede">Enter the email you signed up with. We send a link to choose a new password.</p>

    @if (session('status'))
      <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
    @endif

    <form method="POST" action="{{ route('driver.password.email') }}" data-busy data-busy-label="Sending" novalidate style="margin-top:16px">
      @csrf
      <div class="fld">
        <label class="f" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autocapitalize="none" required @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="err">{{ $message }}</p>@enderror
      </div>
      <button class="btn block" type="submit">Send reset link</button>
    </form>
  </div>
</div>
@endsection
