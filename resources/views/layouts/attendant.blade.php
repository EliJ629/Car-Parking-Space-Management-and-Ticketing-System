@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="/css/bayline.css?v={{ filemtime(public_path('css/bayline.css')) }}">
<link rel="stylesheet" href="/css/attendant.css?v={{ filemtime(public_path('css/attendant.css')) }}">
@endpush

@section('body')
@php
    $me = auth()->user();
    $alertItems = app(\App\Services\AttendantAlerts::class)->items();
    $alertCount = $alertItems->where('unread', true)->count();
    $pendingApprovals = app(\App\Services\AttendantAlerts::class)->pendingApprovals();
    $tab = trim($__env->yieldContent('tab'));
    $tabs = [
        'home' => ['Home', route('attendant.home')],
        'entry' => ['New entry', route('attendant.entry.index')],
        'reservations' => ['Reservations', route('attendant.reservations.index')],
        'exit' => ['Exit and payment', route('attendant.exit.index')],
        'slots' => ['Parking slots', route('attendant.slots.index')],
        'records' => ['Parking records', route('attendant.records.index')],
        'shift' => ['Shift summary', route('attendant.shift.index')],
    ];
    $idleMinutes = (int) \App\Models\SystemSetting::value('idle_timeout_minutes', 15);
    $flashModal = session('modal');
    // A short message is shown as a pop-up the attendant closes, not as a toast that disappears by itself.
    if (! $flashModal && ($flashToast = session('toast'))) {
        $flashModal = ['title' => $flashToast[0], 'body' => $flashToast[1] ?? '', 'primary' => ['label' => 'OK', 'close' => true]];
    }
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
      <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
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
        <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}@if ($key === 'reservations' && $pendingApprovals > 0) <span class="ba-badge" title="Waiting for approval">{{ $pendingApprovals }}</span>@endif</a>
      @endforeach
    </nav>

    <main id="main" tabindex="-1">
      @yield('content')
    </main>
  </div>
</div>

@if ($flashModal)
  @include('partials.ba-modal', ['modal' => $flashModal, 'id' => 'flash-modal', 'auto' => true])
@endif

<!-- Alerts Panel Modal (BA-65) -->
<dialog class="badlg wide" id="alerts-dlg" aria-labelledby="alerts-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="alerts-t">Alerts</h2>
    <ul class="ba-alerts" id="alert-list" data-keys="{{ $alertItems->pluck('key')->implode(',') }}">
      @forelse ($alertItems as $a)
        <li class="{{ $a['unread'] ? 'unread' : '' }}">
          <a href="{{ $a['url'] }}">
            <b>{{ $a['title'] }}</b>
            <span>{{ $a['body'] }}</span>
            <small>{{ $a['time'] }}</small>
          </a>
        </li>
      @empty
        <li class="none">No alerts right now.</li>
      @endforelse
    </ul>
    <div class="actions">
      <button class="btn ghost sm" type="button" id="alerts-mark-read">Mark all as read</button>
      <button class="btn sm" type="button" data-close>Close</button>
    </div>
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
