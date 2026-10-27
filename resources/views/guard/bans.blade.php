@extends('layouts.guard')

@section('title', 'Banned list - Bayline')
@section('tab', 'bans')

@section('content')
<h1 class="g-title">Banned list</h1>
<p class="g-sub">Vehicles and drivers who may not park or reserve. A vehicle is banned after {{ $threshold }} confirmed incident reports. Read only: the Parking Manager lifts a ban.</p>

<form class="g-filters g-form" method="GET" action="{{ route('guard.bans') }}" aria-label="Search the banned list" data-auto-filter>
  <div class="fld">
    <label class="f" for="q">Search</label>
    <input id="q" name="q" type="search" maxlength="20" value="{{ $q }}" data-search-safe placeholder="Plate, name or mobile" autocomplete="off">
  </div>
  @if ($q !== '')<a class="btn ghost sm" href="{{ route('guard.bans') }}">Clear</a>@endif
</form>

<div style="margin-top:16px;">
  @include('staff.partials.banned-table')
</div>
@endsection
