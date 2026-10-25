@extends('layouts.driver-app')

@section('title', 'Change password - Bayline')
@section('tab', 'account')
@section('heading', 'Change password')
@section('back', route('driver.account'))

@section('content')
<form method="POST" action="{{ route('driver.account.password.update') }}" data-busy data-busy-label="Saving" novalidate>
  @csrf @method('PUT')
  <div class="fld">
    <label class="f" for="current_password">Current password</label>
    <input id="current_password" name="current_password" type="password" maxlength="255" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
    @error('current_password')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="password">New password</label>
    <input id="password" name="password" type="password" maxlength="64" autocomplete="new-password" data-pwcheck="#pwrules" data-pwconfirm="#password_confirmation" data-pwmatch="#pw-match" required @error('password') aria-invalid="true" @enderror>
    @error('password')<p class="err">{{ $message }}</p>@enderror
    @include('partials.password-rules')
    <p class="fine" style="margin-top:6px">Different from your last 3 passwords.</p>
  </div>
  <div class="fld">
    <label class="f" for="password_confirmation">Confirm new password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" maxlength="64" autocomplete="new-password" required>
    <p class="hint" id="pw-match" aria-live="polite"></p>
  </div>
  <div class="actions"><button class="btn" type="submit">Update password</button><a class="btn ghost" href="{{ route('driver.account') }}">Cancel</a></div>
</form>
@endsection
