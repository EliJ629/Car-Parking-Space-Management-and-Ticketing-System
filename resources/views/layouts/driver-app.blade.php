@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="/css/driver-app.css?v={{ filemtime(public_path('css/driver-app.css')) }}">
@endpush

@section('body')
@php
    $me = auth()->user();
    $unreadCount = \App\Models\Notice::query()->forDriver($me)->unread()->count();
    $latestUnread = (int) \App\Models\Notice::query()->forDriver($me)->unread()->max('id');
    $tab = trim($__env->yieldContent('tab'));
    $tabs = [
        'home' => ['Home', route('driver.home')],
        'find' => ['Find', route('driver.find')],
        'bookings' => ['Bookings', route('driver.bookings')],
        'session' => ['Session', route('driver.session')],
        'account' => ['Account', route('driver.account')],
    ];
    $flashModal = session('modal');
    // A short message is shown as a pop-up the driver closes, not as a toast that disappears by itself.
    if (! $flashModal && ($flashToast = session('toast'))) {
        $flashModal = ['title' => $flashToast[0], 'body' => $flashToast[1] ?? '', 'primary' => ['label' => 'OK', 'close' => true]];
    }
@endphp
<div class="dapp-body">
<div class="dapp" id="dapp" data-count-url="{{ route('driver.notices.count') }}" data-latest="{{ $latestUnread }}">
  <header class="dapp-top">
    @hasSection('back')
      <a class="dapp-back" href="@yield('back')"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px"><path d="M10 3L5 8l5 5"/></svg>Back</a>
    @else
      <span class="logo" aria-hidden="true">@include('partials.logo')</span>
    @endif
    <h1>@yield('heading', 'Bayline')</h1>
    <button class="tt dapp-theme" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
    <a class="dapp-bell" id="bell" href="{{ route('driver.notices') }}" data-box="{{ route('driver.notices.box') }}" data-read="{{ route('driver.notices.read-all') }}" data-csrf="{{ csrf_token() }}" aria-haspopup="dialog" aria-label="Notifications, {{ $unreadCount }} unread">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
      <span class="dapp-badge" id="bell-count" @if ($unreadCount < 1) hidden @endif>{{ $unreadCount }}</span>
    </a>
  </header>

  <main class="dapp-main" id="main">
    @yield('content')
  </main>

  <nav class="dapp-tabs" aria-label="Main">
    @foreach ($tabs as $key => [$label, $url])
      <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </nav>
</div>
</div>

@if ($flashModal)
  @include('driver.app.partials.modal', ['modal' => $flashModal, 'id' => 'flash-modal', 'auto' => true])
@endif
{{-- The notifications box: opens over the page, so the driver does not leave what they are doing. --}}
<dialog class="dsheet notices" id="notice-box" aria-labelledby="nb-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="nb-t">Notifications</h2>
    <div id="notice-box-body" aria-live="polite"><p class="sub">Loading</p></div>
  </div>
</dialog>

{{-- A new notification arriving while the app is open shows here, as a pop-up. --}}
<dialog class="dsheet" id="notice-live" aria-labelledby="nl-t">
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="nl-t">New notification</h2>
    <p class="sub" id="nl-body" style="color:var(--ink)"></p>
    <div class="actions"><a class="btn" id="nl-open" href="#">Open</a><button class="btn ghost" type="button" data-close>Later</button></div>
  </div>
</dialog>
@stack('dialogs')
@endsection

@push('scripts')
<script src="{{ asset('js/qr.min.js') }}"></script>
<script src="{{ asset('js/driver-app.js') }}?v={{ filemtime(public_path('js/driver-app.js')) }}"></script>
@endpush
