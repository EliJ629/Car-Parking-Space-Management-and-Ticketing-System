@extends('layouts.driver-app')

@section('title', 'Notifications - Bayline')
@section('tab', 'account')
@section('heading', 'Notifications')
@section('back', route('driver.account'))

@section('content')
@if ($today->isEmpty() && $earlier->isEmpty())
  @include('driver.app.partials.empty', ['title' => 'No notifications yet', 'text' => 'Reservation updates, payment receipts and parking notices appear here.'])
@else
  @if ($unread > 0)
    <form method="POST" action="{{ route('driver.notices.read-all') }}" style="margin:0 0 12px;text-align:right">
      @csrf
      <button class="lnk" type="submit" style="background:none;border-top:0;border-inline:0;cursor:pointer;font-family:inherit">Mark all {{ $unread }} as read</button>
    </form>
  @endif
  @foreach (['Today' => $today, 'Earlier' => $earlier] as $heading => $group)
    @if ($group->isNotEmpty())
      <h2 class="eyebrow" style="font-family:var(--f-b);margin:16px 0 8px;font-size:12px">{{ $heading }}</h2>
      <ul class="dlist">
        @foreach ($group as $n)
          <li>
            <a class="ditem {{ $n->isUnread() ? 'unread' : '' }}" href="{{ route('driver.notices.show', $n) }}">
              <div class="top"><b>{{ $n->title }}</b>@if ($n->isUnread())<span class="dot" aria-label="Unread"></span>@endif</div>
              <p>{{ \Illuminate\Support\Str::limit($n->body, 110) }}</p>
              <p class="meta">{{ $heading === 'Today' ? \App\Support\Lot::time($n->created_at) : \App\Support\Lot::stamp($n->created_at) }}</p>
            </a>
          </li>
        @endforeach
      </ul>
    @endif
  @endforeach
@endif
@endsection
