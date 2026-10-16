@extends('layouts.app')

@section('title', 'Access denied - Bayline')

@section('body')
<main style="min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div style="max-width:520px;width:100%;background:var(--surface);border:1px solid var(--line);border-top:5px solid var(--stop);border-radius:12px;padding:28px">
    <p class="eyebrow">Error 403</p>
    <h1 style="font-size:56px;margin:6px 0 10px">Access denied</h1>
    <p style="color:var(--muted)">Your account does not have access to this page. Go back to your own workspace.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
      @auth
        <a class="btn" href="{{ auth()->user()->isDriver() ? route('driver.home') : route('staff.home') }}">Back to my workspace</a>
      @else
        <a class="btn" href="{{ route('staff.login') }}">Sign in</a>
      @endauth
    </div>
  </div>
</main>
@endsection
