@extends('layouts.driver-app')

@section('title', 'Edit profile - Bayline')
@section('tab', 'account')
@section('heading', 'Edit profile')
@section('back', route('driver.account'))

@section('content')
@if ($user->pending_email || $user->pending_phone)
  <div class="alert" role="status" style="margin-bottom:14px"><b>A change is waiting for your code</b>
    @if ($user->pending_email)New email {{ $user->pending_email }}. @endif @if ($user->pending_phone)New number {{ \App\Support\Lot::phone($user->pending_phone) }}. @endif
    <a class="lnk" href="{{ route('driver.account.confirm') }}">Enter the code</a></div>
@endif
<form method="POST" action="{{ route('driver.account.profile.update') }}" data-busy data-busy-label="Saving" novalidate>
  @csrf @method('PUT')
  <div class="fld">
    <label class="f" for="name">Full name</label>
    <input id="name" name="name" type="text" maxlength="120" autocomplete="name" value="{{ old('name', $user->name) }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
    @error('name')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="email">Gmail address</label>
    <input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email', $user->email) }}" autocomplete="email" autocapitalize="none" required @error('email') aria-invalid="true" @enderror>
    @error('email')<p class="err">{{ $message }}</p>@else<p class="fine" style="margin-top:6px">If you change your email or phone number, we email a 6 digit code to confirm it. Nothing changes until you enter it.</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="phone">Mobile number</label>
    <input id="phone" name="phone" type="tel" inputmode="tel" maxlength="13" placeholder="09171234567" value="{{ old('phone', $user->phone) }}" autocomplete="tel" required @error('phone') aria-invalid="true" @enderror>
    @error('phone')<p class="err">{{ $message }}</p>@else<p class="hint">Digits only. Start with 09 (11 digits) or +63 (13 characters).</p>@enderror
  </div>
  <div class="actions"><button class="btn" type="submit">Save changes</button><a class="btn ghost" href="{{ route('driver.account') }}">Cancel</a></div>
</form>
@endsection
