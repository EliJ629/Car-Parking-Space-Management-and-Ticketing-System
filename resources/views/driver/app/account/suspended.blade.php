@extends('layouts.app')

@section('title', 'Account suspended - Bayline')

@section('body')
<div class="dapp-body">
<div class="dapp suspended">
  <div class="suspended-hero">
    <a class="brand" href="{{ route('driver.suspended') }}"><span class="logo">@include('partials.logo')</span><span><b>Bayline</b><small>Every bay, every ticket.</small></span></a>
  </div>
  <div class="stripe-row" aria-hidden="true"></div>
  <main class="dapp-main" id="main">
    <h2>Your account is suspended</h2>
    <p class="sub">You cannot reserve slots right now. Ask the Parking Manager at the lot office to restore your account.</p>
    <div class="alert" style="margin-top:16px"><b>Already parked?</b>Your current ticket is still valid. Pay at the booth and leave as usual.</div>
    <dl class="kv rule-top" style="margin-top:16px">
      <div><dt>Account</dt><dd>{{ $user->email }}</dd></div>
      <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('driver.logout') }}" class="actions">
      @csrf
      <button class="btn ghost" type="submit">Sign out</button>
    </form>
  </main>
</div>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="/css/driver-app.css?v={{ filemtime(public_path('css/driver-app.css')) }}">
@endpush
