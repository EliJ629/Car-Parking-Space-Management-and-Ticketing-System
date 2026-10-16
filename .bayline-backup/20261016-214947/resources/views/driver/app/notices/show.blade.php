@extends('layouts.driver-app')

@section('title', $notice->title.' - Bayline')
@section('tab', 'account')
@section('heading', 'Notification')
@section('back', route('driver.notices'))

@section('content')
<section class="dcard">
  <p class="lab">{{ \App\Support\Lot::stamp($notice->created_at) }}</p>
  <h3>{{ $notice->title }}</h3>
  <p>{{ $notice->body }}</p>
</section>

@if ($subject && $notice->subject_type === 'reservation')
  <div class="actions"><a class="btn" href="{{ route('driver.bookings.show', $subject) }}">View reservation {{ $subject->code }}</a></div>
@elseif ($subject && $notice->subject_type === 'session')
  <div class="actions">
    <a class="btn" href="{{ route('driver.session') }}">View session</a>
    @if ($subject->payment && $subject->status === 'completed')<a class="btn ghost" href="{{ route('driver.receipt', $subject->payment) }}">View receipt</a>@endif
  </div>
@elseif ($subject && $notice->subject_type === 'incident')
  <div class="alert" style="margin-top:14px"><b>Need to talk to someone?</b>Ask the security guard or the booth attendant at the lot. Quote report {{ $subject->code }}.</div>
@endif
@endsection
