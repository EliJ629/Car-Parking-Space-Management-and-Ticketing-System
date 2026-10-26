@extends('layouts.guard')

@section('title', 'Gate validation - Bayline')
@section('tab', 'gate')

@section('content')
@php
    $vehicleText = fn ($s) => strtolower(trim(($s->vehicle_color ?? '').' '.($s->vehicle_type ?? ''))) ?: '-';
@endphp
<h1 class="g-title">Gate validation</h1>
<p class="g-sub">{{ $tab === 'search' ? 'Look up any parking session by plate or ticket.' : 'Paid vehicles waiting to leave. Check each vehicle at the gate, then tap Good to Go.' }}</p>

<nav class="g-sub2" aria-label="Gate validation views">
  <a href="{{ route('guard.gate') }}" @if ($tab === 'queue') aria-current="page" @endif>Exit queue ({{ $queue->count() }})</a>
  <a href="{{ route('guard.gate', ['tab' => 'search']) }}" @if ($tab === 'search') aria-current="page" @endif>Manual search</a>
</nav>

@if ($tab === 'queue')
  <div class="g-stats">
    <div class="g-stat warn"><b>{{ $queue->count() }}</b><span>Waiting for approval</span></div>
    <div class="g-stat"><b>{{ $approvedToday }}</b><span>Approved today</span></div>
    <div class="g-stat {{ $longAlerts->count() ? 'bad' : '' }}"><b>{{ $longAlerts->count() }}</b><span>Open long-parking {{ $longAlerts->count() === 1 ? 'alert' : 'alerts' }}</span></div>
  </div>

  @foreach ($longAlerts->take(3) as $alert)
    <div class="g-banner" role="status">
      <b>Long-parked vehicle: {{ $alert->slot?->code ?? $alert->location }}, {{ \App\Support\Lot::plate($alert->plate_number) }}</b>
      @if ($alert->session)Parked {{ \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($alert->session->entered_at)) }}, over the {{ \App\Support\Lot::longParkingHours() }} h limit. @endif
      Alert {{ $alert->code }} is {{ strtolower(\App\Models\Incident::statusLabel($alert->status)) }}@if ($alert->driver_notified) and the driver was notified @endif.
      <a href="{{ route('guard.incidents.show', $alert) }}">Open incident</a>
    </div>
  @endforeach

  @if ($queue->isEmpty())
    <div class="g-card g-empty">
      <div class="ic" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg></div>
      <h2>No vehicles waiting</h2>
      <p>When the booth takes a payment, the vehicle appears here. This list updates by itself.</p>
      <div class="g-actions" style="justify-content:center"><a class="btn ghost" href="{{ route('guard.gate', ['tab' => 'search']) }}">Search a vehicle</a></div>
    </div>
  @else
    <div id="queue" data-feed="{{ route('guard.gate.feed') }}" data-ids="{{ $queue->pluck('id')->implode(',') }}">
      <div class="g-tablewrap">
        <table class="g-table" data-paginate="10">
          <thead><tr><th>Ticket</th><th>Plate</th><th>Slot</th><th>Vehicle</th><th>Entry</th><th>Paid at</th><th>Receipt</th><th>Exit window left</th><th><span class="sr-only" style="position:absolute;left:-999px">Action</span></th></tr></thead>
          <tbody>
            @foreach ($queue as $s)
              <tr class="{{ $loop->first ? 'hl' : '' }}">
                <td data-label="Ticket"><b class="k">{{ $s->ticket_number }}</b></td>
                <td data-label="Plate"><b class="k">{{ \App\Support\Lot::plate($s->plate_number) }}</b></td>
                <td data-label="Slot">{{ $s->slot?->code ?? $s->slot_code }}</td>
                <td data-label="Vehicle">{{ ucfirst($vehicleText($s)) }}</td>
                <td data-label="Entry">{{ \App\Support\Lot::time($s->entered_at) }}</td>
                <td data-label="Paid at">{{ \App\Support\Lot::time($s->payment->paid_at) }}</td>
                <td data-label="Receipt">{{ $s->payment->receipt_number }}</td>
                <td data-label="Exit window left"><span data-left-for="{{ $s->id }}">{{ $s->minutes_left < 1 ? 'Ending' : $s->minutes_left.' min' }}</span></td>
                <td class="go"><a class="btn sm" href="{{ route('guard.gate.show', $s) }}" aria-label="Review {{ $s->ticket_number }}">Review</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="g-foot"><span>{{ $queue->count() }} {{ $queue->count() === 1 ? 'request' : 'requests' }} waiting</span><span>Updates by itself when the booth takes a payment</span></div>
    </div>
  @endif

@else
  <form class="g-card g-form" method="GET" action="{{ route('guard.gate') }}" role="search">
    <input type="hidden" name="tab" value="search">
    <p class="g-lab" style="margin-bottom:8px">Find a vehicle</p>
    <div class="g-search">
      <div>
        <label class="f" for="q">Plate or ticket number</label>
        <input id="q" name="q" type="search" data-plate-or-ticket maxlength="20" value="{{ $q }}" placeholder="BNM 6628 or PK-0018" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
      </div>
      <button class="btn" type="submit" style="min-height:48px">Search</button>
    </div>
    <p class="hint">Use this when a vehicle reaches the gate without a request in the queue.</p>
  </form>

  @if ($result)
    @php $s = $result; @endphp
    @if ($state === 'paid')
      <div class="g-banner ok" style="margin-top:16px" role="status"><b>Paid and waiting for approval</b>Exit request received at {{ \App\Support\Lot::time($s->payment?->paid_at) }}. Verify the vehicle at the gate before approving.</div>
    @elseif ($state === 'unpaid')
      <div class="g-banner stop" style="margin-top:16px" role="status"><b>Not paid</b>This vehicle still owes {{ \App\Support\Lot::peso($due) }}. Send the driver to the booth first.</div>
    @else
      <div class="g-banner" style="margin-top:16px" role="status"><b>Already exited</b>This session is closed. The vehicle left at {{ \App\Support\Lot::stamp($s->exited_at) }}.</div>
    @endif

    <section class="g-card" style="margin-top:14px" aria-label="Search result">
      <div class="g-plate"><b>{{ \App\Support\Lot::plate($s->plate_number) }}</b> @include('guard.partials.state', ['state' => $state])</div>
      <dl class="g-kv cols">
        <dt>Plate</dt><dd>{{ \App\Support\Lot::plate($s->plate_number) }}</dd>
        <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($s->entered_at) }}</dd>
        <dt>Color and type</dt><dd>{{ ucfirst($vehicleText($s)) }}</dd>
        <dt>Payment status</dt><dd>@include('guard.partials.state', ['state' => $state])</dd>
        <dt>Slot</dt><dd>{{ $s->slot?->code ?? $s->slot_code }}@if ($s->slot), {{ $s->slot->zone }}@endif</dd>
        <dt>{{ $state === 'unpaid' ? 'Amount due' : 'Receipt' }}</dt><dd>{{ $state === 'unpaid' ? \App\Support\Lot::peso($due) : ($s->payment ? $s->payment->receipt_number.', '.\App\Support\Lot::peso($s->payment->total) : '-') }}</dd>
        <dt>Ticket</dt><dd>{{ $s->ticket_number }}</dd>
        <dt>{{ $state === 'exited' ? 'Exited' : 'Exit window' }}</dt><dd>{{ $state === 'exited' ? \App\Support\Lot::stamp($s->exited_at) : ($state === 'paid' ? 'Until '.\App\Support\Lot::time($gate->windowEnd($s)) : 'Starts after payment') }}</dd>
      </dl>
      <div class="g-actions">
        @if ($state === 'paid')<a class="btn" href="{{ route('guard.gate.show', $s) }}">Review and approve</a>@endif
        @if ($state === 'unpaid')<button class="btn" type="button" data-open="#balance">Outstanding balance</button>@endif
        @if ($state === 'exited')<a class="btn" href="{{ route('guard.gate.show', $s) }}">Open record</a>@endif
        <a class="btn ghost" href="{{ route('guard.gate', ['tab' => 'search']) }}">Search again</a>
      </div>
    </section>

    @if ($state === 'unpaid')
      @push('dialogs')
      <dialog class="gdlg stop" id="balance" aria-labelledby="bal-t" data-autoopen>
        <div class="in">
          <h2 id="bal-t">Outstanding balance</h2>
          <p>{{ \App\Support\Lot::plate($s->plate_number) }} has not paid. The amount due so far is <b>{{ \App\Support\Lot::peso($due) }}</b>.</p>
          <p class="note">The Guard cannot release this slot. Send the driver to the booth to pay, then the vehicle shows in the exit queue.</p>
          <div class="actions">
            <button class="btn" type="button" data-close>OK, send to booth</button>
          </div>
        </div>
      </dialog>
      @endpush
    @endif
  @elseif ($searched)
    <div class="g-card g-empty" style="margin-top:16px"><h2>Nothing found</h2><p>No parking session matches {{ $q }}.</p></div>
    @push('dialogs')
    <dialog class="gdlg" id="none" aria-labelledby="none-t" data-autoopen>
      <div class="in">
        <h2 id="none-t">No session found</h2>
        <p>There is no parking session for {{ $q }}. Check the plate or the ticket number and try again.</p>
        <p class="note">A vehicle with no ticket is not a paid exit.</p>
        <div class="actions"><button class="btn" type="button" data-close>Search again</button></div>
      </div>
    </dialog>
    @endpush
  @else
    <div class="g-card g-empty" style="margin-top:16px"><h2>Search a vehicle</h2><p>Enter a plate number or a ticket number to see its payment state.</p></div>
  @endif
@endif
@endsection
