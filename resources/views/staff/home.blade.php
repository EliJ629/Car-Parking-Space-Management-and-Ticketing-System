@extends('layouts.app')

@section('title', 'Workspace - Bayline')

@section('body')
@include('partials.workspace-bar', ['home' => route('staff.home'), 'logout' => 'staff.logout'])

<main class="page"><div class="w">
  <p class="eyebrow">{{ $user->roleLabel() }}</p>
  <h1 style="margin-top:10px">Good day, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}.</h1>
  <p class="lede">You are signed in. This is where your tools will appear as each screen is built.</p>

  <div class="panel" style="margin-top:28px;max-width:720px">
    <p class="eyebrow">Coming to your workspace</p>
    <div style="margin-top:8px">
      @foreach ($tools as [$title, $text])
        <div class="row" style="display:block">
          <span style="color:var(--ink);font-weight:700;display:block">{{ $title }}</span>
          <span style="font-weight:400;color:var(--muted);display:block;text-align:left">{{ $text }}</span>
        </div>
      @endforeach
    </div>
  </div>
  <p class="fine" style="margin-top:16px">Your session ends after 15 minutes without activity.</p>
</div></main>
@endsection
