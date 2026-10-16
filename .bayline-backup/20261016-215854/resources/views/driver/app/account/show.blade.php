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
  <li><button class="tt" id="theme" type="button" style="border:0;border-radius:0;background:transparent;color:var(--ink);min-height:52px;width:100%;text-align:left;padding:0 16px;font:600 15px var(--f-b)">Dark mode</button></li>
</ul>
<form method="POST" action="{{ route('driver.logout') }}" class="actions">
  @csrf
  <button class="btn ghost" type="submit">Sign out</button>
</form>
@endsection
