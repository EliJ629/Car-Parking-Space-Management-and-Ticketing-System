@extends('layouts.guard')

@section('title', 'Records - Bayline')
@section('tab', 'records')

@section('content')
@php
    $keep = fn (array $extra = []) => array_filter(array_merge(['tab' => $tab, 'range' => $f['range'], 'from' => $f['range'] === 'custom' ? $f['from'] : null, 'to' => $f['range'] === 'custom' ? $f['to'] : null, 'q' => $f['q'] ?: null, 'payment' => $payment ?? null, 'status' => $status ?? null, 'category' => $category ?? null, 'page' => $rows->currentPage() > 1 ? $rows->currentPage() : null], $extra), fn ($v) => $v !== null && $v !== '' && $v !== 'all');
@endphp
<h1 class="g-title">Records</h1>
<p class="g-sub">{{ $tab === 'parking' ? 'Parking sessions for the lot. Read only.' : 'Incident reports for the lot. Read only.' }}</p>

<nav class="g-sub2" aria-label="Records">
  <a href="{{ route('guard.records') }}" @if ($tab === 'parking') aria-current="page" @endif>Parking records</a>
  <a href="{{ route('guard.records', ['tab' => 'incidents']) }}" @if ($tab === 'incidents') aria-current="page" @endif>Incident records</a>
</nav>

<form class="g-filters g-form" method="GET" action="{{ route('guard.records') }}" aria-label="Filter records" data-auto-filter>
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="fld">
    <label class="f" for="range">Date range</label>
    <select id="range" name="range">
      @foreach (['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'all' => 'All dates', 'custom' => 'Pick dates'] as $v => $l)
        <option value="{{ $v }}" @selected($f['range'] === (string) $v)>{{ $l }}</option>
      @endforeach
    </select>
  </div>
  <div class="fld sm" data-custom @if ($f['range'] !== 'custom') hidden @endif><label class="f" for="from">From</label><input id="from" name="from" type="date" data-range-start max="{{ \App\Support\Lot::now()->format('Y-m-d') }}" value="{{ $f['from'] }}"></div>
  <div class="fld sm" data-custom @if ($f['range'] !== 'custom') hidden @endif><label class="f" for="to">To</label><input id="to" name="to" type="date" data-range-end max="{{ \App\Support\Lot::now()->format('Y-m-d') }}" value="{{ $f['to'] }}"></div>
  <div class="fld">
    <label class="f" for="q">Search</label>
    <input id="q" name="q" type="search" maxlength="20" value="{{ $f['q'] }}" data-search-safe placeholder="{{ $tab === 'parking' ? 'Plate or ticket number' : 'Plate or incident code' }}" autocomplete="off">
  </div>
  @if ($tab === 'parking')
    <div class="fld sm">
      <label class="f" for="payment">Payment</label>
      <select id="payment" name="payment">@foreach (['all' => 'All', 'paid' => 'Paid', 'unpaid' => 'Unpaid'] as $v => $l)<option value="{{ $v }}" @selected($payment === $v)>{{ $l }}</option>@endforeach</select>
    </div>
  @else
    <div class="fld sm">
      <label class="f" for="status">Status</label>
      <select id="status" name="status"><option value="">All</option>@foreach (\App\Models\Incident::STATUSES as $st)<option value="{{ $st }}" @selected($status === $st)>{{ \App\Models\Incident::statusLabel($st) }}</option>@endforeach</select>
    </div>
    <div class="fld">
      <label class="f" for="category">Category</label>
      <select id="category" name="category"><option value="">All</option>@foreach ($categories as $c)<option value="{{ $c }}" @selected($category === $c)>{{ \App\Models\Incident::guardLabel($c) }}</option>@endforeach</select>
    </div>
  @endif
  <noscript><button class="btn sm" type="submit" style="min-height:48px">Apply</button></noscript>
  <a class="btn ghost sm" href="{{ route('guard.records', ['tab' => $tab]) }}" style="min-height:48px">Clear filters</a>
</form>
<p class="hint" style="font-size:13px;color:var(--muted);margin:-4px 0 12px">{{ $rangeText }}</p>

@if ($rows->isEmpty())
  <div class="g-card g-empty"><h2>No results</h2><p>No {{ $tab === 'parking' ? 'parking sessions' : 'incidents' }} match these filters. Try a wider date range or clear the search.</p></div>
@elseif ($tab === 'parking')
  <div class="g-tablewrap">
    <table class="g-table">
      <thead><tr><th>Date</th><th>Entry</th><th>Exit</th><th>Ticket</th><th>Plate</th><th>Slot</th><th>Duration</th><th>Payment</th><th>Exit approved by</th></tr></thead>
      <tbody>
        @foreach ($rows as $s)
          @php
              $paid = in_array($s->status, ['paid', 'completed'], true);
              $mins = \App\Support\Lot::minutesBetween($s->entered_at, $s->exited_at);
          @endphp
          <tr>
            <td data-label="Date">{{ \App\Support\Lot::local($s->entered_at)->format('M j') }}</td>
            <td data-label="Entry">{{ \App\Support\Lot::time($s->entered_at) }}</td>
            <td data-label="Exit">{{ $s->exited_at ? \App\Support\Lot::time($s->exited_at) : '-' }}</td>
            <td data-label="Ticket"><a class="row" href="{{ route('guard.records', $keep(['view' => $s->id])) }}">{{ $s->ticket_number }}</a></td>
            <td data-label="Plate">{{ \App\Support\Lot::plate($s->plate_number) }}</td>
            <td data-label="Slot">{{ $s->slot?->code ?? $s->slot_code }}</td>
            <td data-label="Duration">{{ \App\Support\Lot::duration($mins) }}</td>
            <td data-label="Payment"><span class="g-tag {{ $paid ? 'ok' : 'bad' }}">{{ $paid ? 'Paid' : 'Unpaid' }}</span></td>
            <td data-label="Exit approved by">{{ $s->status === 'completed' ? ($s->exitApprover?->name ?? '-') : ($s->status === 'paid' ? 'Waiting for guard' : '-') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @include('guard.partials.pager', ['paginator' => $rows])
@else
  <div class="g-tablewrap">
    <table class="g-table">
      <thead><tr><th>Incident</th><th>Date</th><th>Category</th><th>Plate</th><th>Location</th><th>Reported by</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($rows as $i)
          <tr>
            <td data-label="Incident"><a class="row" href="{{ route('guard.records', $keep(['view' => $i->id])) }}">{{ $i->code }}</a></td>
            <td data-label="Date">{{ \App\Support\Lot::stamp($i->reported_at) }}</td>
            <td data-label="Category">{{ \App\Models\Incident::guardLabel($i->category) }}</td>
            <td data-label="Plate">{{ $i->plate_number ? \App\Support\Lot::plate($i->plate_number) : '-' }}</td>
            <td data-label="Location">{{ $i->slot?->code ?? $i->location ?? '-' }}</td>
            <td data-label="Reported by">{{ $i->source === 'auto' ? 'Bayline' : ($i->reporter?->name ?? '-') }}</td>
            <td data-label="Status">@include('guard.partials.incident-status', ['status' => $i->status])</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @include('guard.partials.pager', ['paginator' => $rows])
@endif

@if ($detail)
  @push('dialogs')
  <dialog class="gdlg wide" id="detail" aria-labelledby="dt-t" data-autoopen>
    <div class="in">
      @if ($tab === 'parking')
        @php $d = $detail; $paid = in_array($d->status, ['paid', 'completed'], true); @endphp
        <h2 id="dt-t">Parking record {{ $d->ticket_number }}</h2>
        <dl class="g-kv">
          <dt>Plate</dt><dd>{{ \App\Support\Lot::plate($d->plate_number) }}</dd>
          <dt>Vehicle</dt><dd>{{ ucfirst(strtolower(trim(($d->vehicle_color ?? '').' '.($d->vehicle_type ?? '')))) ?: '-' }}</dd>
          <dt>Slot</dt><dd>{{ $d->slot?->code ?? $d->slot_code }}</dd>
          <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($d->entered_at) }}</dd>
          <dt>Exit</dt><dd>{{ $d->exited_at ? \App\Support\Lot::stamp($d->exited_at) : 'Still in the lot' }}</dd>
          <dt>Duration</dt><dd>{{ \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($d->entered_at, $d->exited_at)) }}</dd>
          <dt>Payment</dt><dd><span class="g-tag {{ $paid ? 'ok' : 'bad' }}">{{ $paid ? 'Paid' : 'Unpaid' }}</span>@if ($d->payment) {{ $d->payment->receipt_number }}, {{ \App\Support\Lot::peso($d->payment->total) }}@endif</dd>
          <dt>Exit approved by</dt><dd>{{ $d->exitApprover?->name ?? '-' }}</dd>
          <dt>Incidents</dt><dd>{{ $d->incidents->isEmpty() ? 'None' : $d->incidents->pluck('code')->join(', ') }}</dd>
        </dl>
      @else
        @php $d = $detail; @endphp
        <h2 id="dt-t">Incident {{ $d->code }}</h2>
        <dl class="g-kv">
          <dt>Category</dt><dd>{{ \App\Models\Incident::guardLabel($d->category) }}</dd>
          <dt>Status</dt><dd>@include('guard.partials.incident-status', ['status' => $d->status])</dd>
          <dt>Reported</dt><dd>{{ \App\Support\Lot::stamp($d->reported_at) }} by {{ $d->source === 'auto' ? 'Bayline' : ($d->reporter?->name ?? '-') }}</dd>
          <dt>Plate</dt><dd>{{ $d->plate_number ? \App\Support\Lot::plate($d->plate_number) : '-' }}</dd>
          <dt>Location</dt><dd>{{ $d->slot?->code ?? $d->location ?? '-' }}</dd>
          <dt>Description</dt><dd>{{ $d->description }}</dd>
          @if ($d->resolution_note)<dt>Resolution</dt><dd>{{ $d->resolution_note }}</dd>@endif
          <dt>Photos</dt><dd>{{ $d->photos->count() }}</dd>
        </dl>
      @endif
      <div class="actions">
        @if ($tab === 'incidents')<a class="btn" href="{{ route('guard.incidents.show', $detail) }}">Open incident</a>@endif
        <a class="btn ghost" href="{{ route('guard.records', $keep(['view' => null])) }}">Close</a>
      </div>
    </div>
  </dialog>
  @endpush
@endif
@endsection
