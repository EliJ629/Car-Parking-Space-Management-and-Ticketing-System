@extends('layouts.attendant')

@section('title', 'Banned list - Booth Attendant')
@section('tab', 'bans')

@section('content')
<h1 class="ba-page-title">Banned list</h1>
<p class="ba-sub">Vehicles and drivers who may not park or reserve. A vehicle is banned after {{ $threshold }} confirmed incident reports. Do not check in a banned vehicle. Only the Parking Manager lifts a ban.</p>

<div class="ba-card" style="margin-bottom:20px;">
  <form method="GET" action="{{ route('attendant.bans') }}" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;">
      <label class="ba-label" for="ban-q">Search</label>
      <input class="ba-input" id="ban-q" name="q" type="search" maxlength="20" value="{{ $q }}" placeholder="Plate, name or mobile" autocomplete="off" oninput="this.value=this.value.replace(/[^A-Za-z0-9 -]/g,'')">
    </div>
    <button class="btn sm" type="submit">Search</button>
    @if ($q !== '')<a class="btn ghost sm" href="{{ route('attendant.bans') }}">Clear</a>@endif
  </form>
</div>

<div class="ba-card">
  @include('staff.partials.banned-table')
</div>
@endsection
