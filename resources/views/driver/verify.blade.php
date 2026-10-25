@extends('layouts.app')

@section('title', 'Confirm your email - Bayline')

@section('body')
<div class="pagebar">
  <a class="back" href="{{ route('driver.login') }}">&larr; Back to sign in</a>
  <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
</div>

<div class="appcard">
  <div class="apphero">
    <a class="brand" href="{{ route('driver.landing') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
    </a>
    <p class="lead">One last step before you sign in.</p>
  </div>
  <div class="stripe" aria-hidden="true"></div>

  <div class="appbody">
    <h1>Confirm your email</h1>
    <p class="lede">We sent a 6 digit code to <b>{{ $maskedEmail }}</b>. Enter it below, or tap the link in the same email. The code works for {{ $minutes }} minutes.</p>

    @if (session('status'))
      <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
    @endif
    @if ($logHint)
      <div class="alert" role="note"><b>Mail is set to log on this computer.</b>The code is written to storage/logs/laravel.log instead of being sent. Set the Brevo details in .env to send real emails.</div>
    @endif

    <form method="POST" action="{{ route('driver.verify.check') }}" data-busy data-busy-label="Checking" novalidate style="margin-top:16px">
      @csrf
      <div class="fld">
        <label class="f" for="code">6 digit code</label>
        <input id="code" name="code" type="text" class="codein" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus required @error('code') aria-invalid="true" @enderror>
        @error('code')<p class="err">{{ $message }}</p>@enderror
      </div>
      <button class="btn block" type="submit">Confirm email</button>
    </form>

    <div class="verify-more">
      <form method="POST" action="{{ route('driver.verify.resend') }}" data-busy data-busy-label="Sending">
        @csrf
        <button class="btn ghost block" type="submit" id="resend" data-wait="{{ $wait }}">Send a new code</button>
      </form>
      <button class="lnk linkbtn" type="button" data-open-dialog="restart-dlg">Wrong Gmail address? Start again</button>
    </div>
  </div>
</div>

<dialog class="vdlg" id="restart-dlg" aria-labelledby="rs-t">
  <form class="in" method="POST" action="{{ route('driver.verify.restart') }}">
    @csrf
    <h2 id="rs-t">Start again?</h2>
    <p>This removes the account you have not confirmed yet, so you can sign up again with the right Gmail address.</p>
    <div class="row">
      <button class="btn block" type="submit">Yes, start again</button>
      <button class="btn ghost block" type="button" data-close-dialog>Keep waiting for the code</button>
    </div>
  </form>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
  var b = document.getElementById('resend');
  if (!b) return;
  var left = parseInt(b.getAttribute('data-wait') || '0', 10);
  if (left < 1) return;
  var label = b.textContent;
  function tick() {
    if (left < 1) { b.disabled = false; b.textContent = label; return; }
    b.disabled = true; b.textContent = 'Send a new code in ' + left + 's'; left -= 1; setTimeout(tick, 1000);
  }
  tick();
})();
</script>
@endpush
