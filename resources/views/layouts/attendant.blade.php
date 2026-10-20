@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset('css/bayline.css') }}?v={{ filemtime(public_path('css/bayline.css')) }}">
<link rel="stylesheet" href="{{ asset('css/attendant.css') }}?v={{ filemtime(public_path('css/attendant.css')) }}">
@endpush

@section('body')
@php
    $me = auth()->user();
    $alertItems = app(\App\Services\AttendantAlerts::class)->items();
    $alertCount = $alertItems->where('unread', true)->count();
    $tab = trim($__env->yieldContent('tab'));
    $tabs = [
        'home' => ['Home', route('attendant.home')],
        'entry' => ['New entry', route('attendant.entry.index')],
        'exit' => ['Exit and payment', route('attendant.exit.index')],
        'slots' => ['Parking slots', route('attendant.slots.index')],
        'records' => ['Parking records', route('attendant.records.index')],
        'shift' => ['Shift summary', route('attendant.shift.index')],
    ];
    $idleMinutes = (int) \App\Models\SystemSetting::value('idle_timeout_minutes', 15);
    $flashToast = session('toast');
    $errorModal = session('error_modal');
@endphp

<a class="ba-skip" href="#main">Skip to content</a>

<div class="ba-shell" id="ba-shell"
     data-alerts-url="{{ route('attendant.alerts') }}"
     data-alerts-read="{{ route('attendant.alerts.read') }}"
     data-keepalive="{{ route('staff.keepalive') }}"
     data-login="{{ route('staff.login') }}"
     data-idle="{{ $idleMinutes }}"
     data-csrf="{{ csrf_token() }}">

  <!-- Top Header (BA-01) -->
  <header class="ba-head">
    <div class="ba-w">
      <a class="brand" href="{{ route('attendant.home') }}">
        <span class="logo">@include('partials.logo')</span>
        <span><b>Bayline</b><small>Booth attendant console</small></span>
      </a>
      <span class="sp"></span>
      <time class="ba-clock" id="clock" datetime="{{ now()->toIso8601String() }}">
        {{ \App\Support\Lot::now()->format('D, M j, Y') }} <b>{{ \App\Support\Lot::now()->format('g:i A') }}</b>
      </time>
      <span class="ba-chip">{{ $me->name }} <small>Booth attendant</small></span>
      <button class="ba-alertbtn" type="button" id="alert-btn" data-open="#alerts-dlg" aria-haspopup="dialog">
        Alerts <span class="ba-badge" id="alert-count" @if ($alertCount < 1) hidden @endif>{{ $alertCount }}</span>
      </button>
      <button class="btn ghost sm" type="button" data-open="#logout-dlg">Log out</button>
    </div>
  </header>

  <!-- Navigation Tabs -->
  <div class="ba-w">
    <nav class="ba-tabs" aria-label="Booth attendant console">
      @foreach ($tabs as $key => [$label, $url])
        <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>

    <main id="main" tabindex="-1">
      @yield('content')
    </main>
  </div>
</div>

<!-- Real-time Toasts (BA-56, BA-66) -->
<div class="ba-toasts" id="ba-toasts" aria-live="polite">
  @if ($flashToast)
    <div class="ba-toast" role="status">
      <b>{{ $flashToast[0] }}</b>
      <span>{{ $flashToast[1] ?? '' }}</span>
    </div>
  @endif
</div>

<!-- Alerts Panel Modal (BA-65) -->
<dialog class="badlg wide" id="alerts-dlg" aria-labelledby="alerts-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <div class="badlg-head">
      <h2 id="alerts-t">Alerts</h2>
      <button class="btn ghost sm" type="button" id="alerts-mark-read">Mark all as read</button>
    </div>
    <ul class="ba-alerts-list" id="alert-list">
      @forelse ($alertItems as $a)
        <li class="{{ $a['unread'] ? 'unread' : '' }}">
          <a href="{{ $a['url'] }}">
            <span class="dot"></span>
            <div class="meta">
              <b>{{ $a['title'] }}</b>
              <p>{{ $a['body'] }}</p>
            </div>
            <time>{{ $a['time'] }}</time>
          </a>
        </li>
      @empty
        <li class="none">No alerts right now.</li>
      @endforelse
    </ul>
    <p class="ba-alert-foot">Alerts older than today are in the activity log.</p>
  </div>
</dialog>

<!-- Log Out Confirmation Pop-up (BA-08) -->
<dialog class="badlg" id="logout-dlg" aria-labelledby="lo-t">
  <form class="in" method="POST" action="{{ route('staff.logout') }}">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
    <h2 id="lo-t">Log out?</h2>
    <p>You are signed in as {{ $me->name }}. Open work stays saved.</p>
    <div class="actions">
      <button class="btn ghost" type="button" data-close>Stay signed in</button>
      <button class="btn primary" type="submit">Log out</button>
    </div>
  </form>
</dialog>

<!-- Session Timeout Inactivity Modal (BA-09) -->
<dialog class="badlg" id="timeout-dlg" aria-labelledby="to-t">
  <div class="in">
    <h2 id="to-t">Signed out for inactivity</h2>
    <p>You were signed out after {{ $idleMinutes }} minutes without activity. Sign in again to continue.</p>
    <div class="actions">
      <a class="btn primary" href="{{ route('staff.login') }}">Sign in again</a>
    </div>
  </div>
</dialog>

<!-- Idle Warning Countdown Modal -->
<dialog class="badlg" id="idle-dlg" aria-labelledby="idle-t">
  <div class="in">
    <h2 id="idle-t">Still there?</h2>
    <p>You will be signed out in <b id="idle-left">60</b> seconds because there has been no activity.</p>
    <div class="actions">
      <button class="btn primary" type="button" id="idle-stay">Stay signed in</button>
    </div>
  </div>
</dialog>

@stack('dialogs')
@endsection

@push('scripts')
<script src="{{ asset('js/qr.min.js') }}"></script>
<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/attendant.js') }}?v={{ filemtime(public_path('js/attendant.js')) }}"></script>
@endpush
