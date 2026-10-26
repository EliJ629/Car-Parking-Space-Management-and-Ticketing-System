@extends('layouts.driver-app')

@section('title', 'My account - Bayline')
@section('tab', 'account')
@section('heading', 'Account')

@section('content')
<div class="profile">
  <div class="avatar" aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
  <div style="min-width:0">
    <h2>{{ $user->name }}</h2>
    <p>{{ $user->email }}</p>
    <p>{{ \App\Support\Lot::phone($user->phone) }}</p>
  </div>
</div>

<ul class="menu" style="margin-top:18px">
  <li><a href="{{ route('driver.vehicles') }}">My vehicles <small>{{ $vehicleCount }} saved</small></a></li>
  <li><a href="{{ route('driver.history') }}">Parking history <small>{{ $sessionCount }} {{ \Illuminate\Support\Str::plural('visit', $sessionCount) }}</small></a></li>
  <li><a href="{{ route('driver.notices') }}">Notifications <small>{{ $unread > 0 ? $unread.' unread' : 'All read' }}</small></a></li>
</ul>
<ul class="menu" style="margin-top:14px">
  <li><a href="{{ route('driver.account.profile') }}">Edit profile</a></li>
  <li><a href="{{ route('driver.account.password') }}">Change password</a></li>
  <li><button class="tt tt-row" id="theme" type="button" title="Switch between light and dark mode"><span>Appearance <small>Light or dark</small></span><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button></li>
</ul>
<div class="actions">
  <button class="btn ghost" type="button" data-open="#signout-dlg" aria-haspopup="dialog">Sign out</button>
</div>

@push('dialogs')
<dialog class="dsheet" id="signout-dlg" aria-labelledby="so-t">
  <form class="in" method="POST" action="{{ route('driver.logout') }}">
    @csrf
    <h2 id="so-t">Sign out?</h2>
    <p class="sub" style="color:var(--ink)">You will need to sign in again to see your reservations and vehicles.</p>
    <div class="actions">
      <button class="btn" type="submit">Yes, sign out</button>
      <button class="btn ghost" type="button" data-close>Stay signed in</button>
    </div>
  </form>
</dialog>
@endpush
@endsection
