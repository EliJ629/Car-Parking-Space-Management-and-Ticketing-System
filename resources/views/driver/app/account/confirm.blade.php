@extends('layouts.driver-app')

@section('title', 'Confirm your change - Bayline')
@section('tab', 'account')
@section('heading', 'Confirm change')
@section('back', route('driver.account.profile'))

@section('content')
<section class="dcard">
  <p class="lab">Check your email</p>
  <p style="margin:4px 0 10px">We sent a 6 digit code to <b>{{ $sentTo }}</b>. The code works for {{ $minutes }} minutes.</p>
  <dl class="kv">
    @if ($user->pending_email)<div><dt>New email</dt><dd>{{ $user->pending_email }}</dd></div>@endif
    @if ($user->pending_phone)<div><dt>New mobile number</dt><dd>{{ \App\Support\Lot::phone($user->pending_phone) }}</dd></div>@endif
  </dl>
</section>

@if ($logHint)
  <div class="alert" role="note" style="margin-top:14px"><b>Mail is set to log on this computer.</b>The code is written to storage/logs/laravel.log instead of being sent.</div>
@endif

<form method="POST" action="{{ route('driver.account.confirm.check') }}" data-busy data-busy-label="Checking" novalidate style="margin-top:16px">
  @csrf
  <div class="fld">
    <label class="f" for="code">6 digit code</label>
    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus required @error('code') aria-invalid="true" @enderror>
    @error('code')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="actions"><button class="btn" type="submit">Confirm change</button></div>
</form>

<div class="actions two">
  <form method="POST" action="{{ route('driver.account.confirm.resend') }}" data-busy data-busy-label="Sending">@csrf<button class="btn ghost" type="submit" @if ($wait > 0) title="Wait {{ $wait }} seconds" @endif>Send a new code</button></form>
  <form method="POST" action="{{ route('driver.account.confirm.cancel') }}">@csrf<button class="btn ghost" type="submit">Cancel change</button></form>
</div>
@endsection
