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
    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
    @error('current_password')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
    <p class="fine" style="margin-top:6px">At least 8 characters with a letter and a number. It must differ from your last 3 passwords.</p>
    @error('password')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="password_confirmation">Confirm new password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
  </div>
  <div class="actions"><button class="btn" type="submit">Update password</button><a class="btn ghost" href="{{ route('driver.account') }}">Cancel</a></div>
</form>
@endsection
