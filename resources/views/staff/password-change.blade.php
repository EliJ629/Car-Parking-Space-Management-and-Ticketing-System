@extends('layouts.app')

@section('title', 'Change password - Bayline')

@section('body')
<div class="appcard">
  <div class="apphero">
    <a class="brand" href="{{ route('staff.landing') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
    </a>
  </div>
  <div class="stripe" aria-hidden="true"></div>
  <div class="appbody">
    <a class="tt backico" href="{{ route('staff.login') }}" aria-label="Back to sign in" title="Back to sign in"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></a>
    <h1>New password</h1>
    <p class="lede">Your temporary password must be replaced before you can continue.</p>
    @if ($errors->any())
      <div class="alert stop" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('staff.password.change.update') }}" data-busy data-busy-label="Saving" novalidate style="margin-top:16px">
      @csrf
      @method('PUT')
      <div class="fld">
        <label class="f" for="password">New password</label>
        <input id="password" name="password" type="password" maxlength="64" autocomplete="new-password" data-pwcheck="#pwrules" data-pwconfirm="#password_confirmation" data-pwmatch="#pw-match" required @error('password') aria-invalid="true" @enderror>
        @error('password')<p class="err">{{ $message }}</p>@else<p class="hint">Use a password you have not used before.</p>@enderror
        @include('partials.password-rules')
      </div>
      <div class="fld">
        <label class="f" for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" maxlength="64" autocomplete="new-password" required>
        <p class="hint" id="pw-match" aria-live="polite"></p>
      </div>
      <button class="btn block" type="submit">Save new password</button>
    </form>
  </div>
  <div class="cardfoot">@include('partials.theme-toggle')</div>
</div>
@endsection
