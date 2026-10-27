@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="/css/guard.css?v={{ filemtime(public_path('css/guard.css')) }}">
<link rel="stylesheet" href="/css/pager.css?v={{ filemtime(public_path('css/pager.css')) }}">
@endpush

@section('body')
@php
    $me = auth()->user();
    $alertItems = app(\App\Services\GuardAlerts::class)->items();
    $alertCount = $alertItems->where('unread', true)->count();
    $tab = trim($__env->yieldContent('tab'));
    $tabs = [
        'gate' => ['Gate validation', route('guard.gate')],
        'incidents' => ['Incident reporting', route('guard.incidents')],
        'records' => ['Records', route('guard.records')],
        'slots' => ['Slots view', route('guard.slots')],
        'bans' => ['Banned list', route('guard.bans')],
    ];
    $idleMinutes = (int) \App\Models\SystemSetting::value('idle_timeout_minutes', 15);
    $flashModal = session('modal');
    // A short message is shown as a pop-up the guard closes, not as a toast that disappears by itself.
    if (! $flashModal && ($flashToast = session('toast'))) {
        $flashModal = ['title' => $flashToast[0], 'body' => $flashToast[1] ?? '', 'primary' => ['label' => 'OK', 'close' => true]];
    }
@endphp
<a class="g-skip" href="#main">Skip to content</a>
<div class="g-shell" id="gshell"
     data-alerts-url="{{ route('guard.alerts') }}" data-alerts-read="{{ route('guard.alerts.read') }}"
     data-keepalive="{{ route('staff.keepalive') }}" data-login="{{ route('staff.login') }}" data-idle="{{ $idleMinutes }}"
     data-csrf="{{ csrf_token() }}">
  <header class="g-head"><div class="g-w">
    <a class="brand" href="{{ route('guard.gate') }}">
      <span class="logo">@include('partials.logo')</span>
      <span><b>Bayline</b><small>Security guard console</small></span>
    </a>
    <span class="sp"></span>
    <time class="g-clock" id="clock" datetime="{{ now()->toIso8601String() }}">{{ \App\Support\Lot::now()->format('D, M j, Y') }} <b>{{ \App\Support\Lot::now()->format('g:i A') }}</b></time>
    <span class="g-chip">{{ $me->name }} <small>Security guard</small></span>
    <button class="g-alertbtn" type="button" id="alert-btn" data-open="#alerts" aria-haspopup="dialog">Alerts <span class="g-badge" id="alert-count" @if ($alertCount < 1) hidden @endif>{{ $alertCount }}</span></button>
    <button class="tt g-theme" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button>
    <button class="btn ghost sm" type="button" data-open="#logout-dlg">Log out</button>
  </div></header>

  <div class="g-w">
    <nav class="g-tabs" aria-label="Guard console">
      @foreach ($tabs as $key => [$label, $url])
        <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
    <main id="main" tabindex="-1">
      @yield('content')
    </main>
  </div>
</div>

{{-- A new alert arriving while the guard works opens this pop-up (filled by guard.js). --}}
<dialog class="gdlg" id="live-dlg" aria-labelledby="live-t">
  <div class="in">
    <h2 id="live-t">New alert</h2>
    <p id="live-body"></p>
    <div class="actions"><a class="btn" id="live-open" href="#">Open</a><button class="btn ghost" type="button" data-close>Later</button></div>
  </div>
</dialog>

@if ($flashModal)
  @include('guard.partials.modal', ['modal' => $flashModal, 'id' => 'flash-modal', 'auto' => true])
@endif
@isset($modalNow)
  @include('guard.partials.modal', ['modal' => $modalNow, 'id' => 'now-modal', 'auto' => true])
@endisset

<dialog class="gdlg wide" id="alerts" aria-labelledby="alerts-t">
  <div class="in">
    <h2 id="alerts-t">Alerts</h2>
    <ul class="g-alerts" id="alert-list" data-keys="{{ $alertItems->pluck('key')->implode(',') }}">
      @forelse ($alertItems as $a)
        <li class="{{ $a['unread'] ? 'unread' : '' }}"><a href="{{ $a['url'] }}"><b>{{ $a['title'] }}</b><span>{{ $a['body'] }}</span><small>{{ $a['time'] }}</small></a></li>
      @empty
        <li class="none">No alerts right now.</li>
      @endforelse
    </ul>
    <div class="actions"><button class="btn ghost sm" type="button" id="alerts-read">Mark all as read</button><button class="btn sm" type="button" data-close>Close</button></div>
  </div>
</dialog>

<dialog class="gdlg" id="logout-dlg" aria-labelledby="lo-t">
  <form class="in" method="POST" action="{{ route('staff.logout') }}">
    @csrf
    <h2 id="lo-t">Log out?</h2>
    <p>Anything you have not submitted will be lost.</p>
    <div class="actions"><button class="btn" type="submit">Log out</button><button class="btn ghost" type="button" data-close>Stay signed in</button></div>
  </form>
</dialog>

<dialog class="gdlg" id="idle-dlg" aria-labelledby="idle-t">
  <div class="in">
    <h2 id="idle-t">Still there?</h2>
    <p>You will be signed out in <b id="idle-left">60</b> seconds because there has been no activity.</p>
    <div class="actions"><button class="btn" type="button" id="idle-stay">Stay signed in</button></div>
  </div>
</dialog>

@stack('dialogs')
@endsection

@push('scripts')
<script src="{{ asset('js/plate.js') }}?v={{ filemtime(public_path('js/plate.js')) }}"></script>
<script src="{{ asset('js/guard.js') }}?v={{ filemtime(public_path('js/guard.js')) }}"></script>
<script src="{{ asset('js/table-pager.js') }}?v={{ filemtime(public_path('js/table-pager.js')) }}"></script>
@endpush
