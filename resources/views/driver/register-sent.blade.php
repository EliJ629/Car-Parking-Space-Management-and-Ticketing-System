@extends('layouts.app')

@section('title', 'Check your email - Bayline')

@section('body')
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
    <a class="tt backico" href="{{ route('driver.login') }}" aria-label="Back to sign in" title="Back to sign in"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></a>
    <h1>Check your email</h1>
    <p class="lede">We sent a link to <b>{{ $maskedEmail }}</b>. Click it to finish creating your account. Your details are saved only after you click, and the link works for {{ $hours }} hours.</p>

    @if (session('status'))
      <div class="alert ok" role="status"><b>{{ session('status') }}</b></div>
    @endif
    @error('link')
      <div class="alert stop" role="alert"><b>{{ $message }}</b></div>
    @enderror
    @if ($logHint)
      <div class="alert" role="note"><b>Mail is set to log on this computer.</b>The link is written to storage/logs/laravel.log instead of being sent. Set the Brevo details in .env to send real emails.</div>
    @endif

    <p class="fine" style="margin-top:16px">Nothing in your inbox? Check the spam folder, then ask for a new link.</p>

    <div class="verify-more">
      <form method="POST" action="{{ route('driver.register.resend') }}" data-busy data-busy-label="Sending">
        @csrf
        <button class="btn block" type="submit" id="resend" data-wait="{{ $wait }}">Send a new link</button>
      </form>
      <button class="lnk linkbtn" type="button" data-open-dialog="restart-dlg">Wrong Gmail address? Start again</button>
    </div>
  </div>
  <div class="cardfoot">@include('partials.theme-toggle')</div>
</div>

<dialog class="vdlg" id="restart-dlg" aria-labelledby="rs-t">
  <form class="in" method="POST" action="{{ route('driver.register.restart') }}">
    @csrf
    <h2 id="rs-t">Start again?</h2>
    <p>This clears the sign up you have not confirmed yet, so you can fill the form again with the right Gmail address.</p>
    <div class="row">
      <button class="btn block" type="submit">Yes, start again</button>
      <button class="btn ghost block" type="button" data-close-dialog>Keep waiting for the link</button>
    </div>
  </form>
</dialog>
@endsection

@push('scripts')
<script>
/* The link may be opened on another device. Once the account exists, this page goes to the sign-in page by itself. */
(function () {
  var url = @json(route('driver.register.status'));
  var stop = false;
  function check() {
    if (stop || document.hidden) return;
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.done && d.url) { stop = true; window.location.href = d.url; } })
      .catch(function () {});
  }
  setInterval(check, 4000);
  document.addEventListener('visibilitychange', check);
  window.addEventListener('focus', check);
})();
(function () {
  var b = document.getElementById('resend');
  if (!b) return;
  var left = parseInt(b.getAttribute('data-wait') || '0', 10);
  if (left < 1) return;
  var label = b.textContent;
  function tick() {
    if (left < 1) { b.disabled = false; b.textContent = label; return; }
    b.disabled = true; b.textContent = 'Send a new link in ' + left + 's'; left -= 1; setTimeout(tick, 1000);
  }
  tick();
})();
</script>
@endpush
