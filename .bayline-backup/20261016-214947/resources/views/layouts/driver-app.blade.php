@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset('css/driver-app.css') }}?v={{ filemtime(public_path('css/driver-app.css')) }}">
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
    $flashToast = session('toast');
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
    <a class="dapp-bell" id="bell" href="{{ route('driver.notices') }}" aria-label="Notifications, {{ $unreadCount }} unread">
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

@if ($flashToast)
  <div class="dtoast" role="status"><b>{{ $flashToast[0] }}</b>{{ $flashToast[1] ?? '' }}</div>
@endif

@if ($flashModal)
  @include('driver.app.partials.modal', ['modal' => $flashModal, 'id' => 'flash-modal', 'auto' => true])
@endif
@stack('dialogs')
@endsection

@push('scripts')
<script src="{{ asset('js/qr.min.js') }}"></script>
<script src="{{ asset('js/driver-app.js') }}?v={{ filemtime(public_path('js/driver-app.js')) }}"></script>
@endpush
