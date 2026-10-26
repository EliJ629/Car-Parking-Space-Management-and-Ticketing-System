@extends('layouts.guard')

@section('title', 'Incident reporting - Bayline')
@section('tab', 'incidents')

@section('content')
<h1 class="g-title">Incident reporting</h1>
<p class="g-sub">Report anything unusual in the lot. The Parking Manager sees every report right away.</p>

<div class="g-actions" style="margin-top:14px">
  <a class="btn" href="{{ route('guard.incidents.create') }}">New incident</a>
</div>

<nav class="g-chips" style="margin-top:18px" aria-label="Filter by status">
  <a href="{{ route('guard.incidents') }}" @if (! $status) aria-current="true" @endif>All ({{ $total }})</a>
  @foreach (\App\Models\Incident::STATUSES as $st)
    <a href="{{ route('guard.incidents', ['status' => $st]) }}" @if ($status === $st) aria-current="true" @endif>{{ \App\Models\Incident::statusLabel($st) }} ({{ $counts[$st] ?? 0 }})</a>
  @endforeach
</nav>

@if ($incidents->isEmpty())
  <div class="g-card g-empty"><h2>No incidents</h2><p>{{ $status ? 'Nothing is '.strtolower(\App\Models\Incident::statusLabel($status)).' right now.' : 'Nothing has been reported yet.' }}</p></div>
@else
  <div class="g-tablewrap">
    <table class="g-table">
      <thead><tr><th>Incident</th><th>Category</th><th>Plate</th><th>Location</th><th>Source</th><th>Reported</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($incidents as $i)
          <tr>
            <td data-label="Incident"><a class="row" href="{{ route('guard.incidents.show', $i) }}">{{ $i->code }}</a></td>
            <td data-label="Category">{{ \App\Models\Incident::guardLabel($i->category) }}</td>
            <td data-label="Plate">{{ $i->plate_number ? \App\Support\Lot::plate($i->plate_number) : '-' }}</td>
            <td data-label="Location">{{ $i->slot?->code ?? $i->location ?? '-' }}</td>
            <td data-label="Source"><span class="g-tag ink">{{ $i->source === 'auto' ? 'Automatic' : 'Manual' }}</span></td>
            <td data-label="Reported">{{ \App\Support\Lot::stamp($i->reported_at) }}</td>
            <td data-label="Status">@include('guard.partials.incident-status', ['status' => $i->status])</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @include('guard.partials.pager', ['paginator' => $incidents])
@endif
@endsection
