@extends('layouts.attendant')

@section('title', 'Reservations - Booth Attendant')
@section('tab', 'reservations')

@section('content')
<h1 class="ba-page-title">Reservations to approve</h1>
<p class="ba-sub">A driver gets the QR code only after you approve. For a PWD slot, check the ID photo first. If you reject, the driver is notified and must choose a new slot.</p>

@if ($pending->isEmpty())
  <div class="ba-card" style="text-align:center;padding:40px 24px;margin-bottom:24px">
    <h2 style="font-size:22px;margin-bottom:6px">Nothing waiting</h2>
    <p style="color:var(--muted);margin:0">New reservation requests appear here.</p>
  </div>
@else
  <div class="ba-rsv-list">
    @foreach ($pending as $r)
      <article class="ba-card ba-rsv" aria-labelledby="r{{ $r->id }}-t">
        <div class="ba-rsv-head">
          <h2 id="r{{ $r->id }}-t">{{ $r->code }}</h2>
          @if ($r->requires_id)<span class="ba-pill warn">PWD slot, ID needed</span>@else<span class="ba-pill">Standard slot</span>@endif
        </div>
        <dl class="ba-kv">
          <div><dt>Driver</dt><dd>{{ $r->driver?->name ?? 'Driver' }}@if ($r->driver?->phone) <small>{{ \App\Support\Lot::phone($r->driver->phone) }}</small>@endif</dd></div>
          <div><dt>Vehicle</dt><dd>{{ \App\Support\Lot::plate($r->plate_number) }} <small>{{ $r->vehicle?->describe() }}</small></dd></div>
          <div><dt>Slot</dt><dd>{{ $r->slot_code }} <small>{{ $r->slot?->zone }}</small></dd></div>
          <div><dt>Arrival</dt><dd>{{ \App\Support\Lot::when($r->scheduled_at) }}</dd></div>
        </dl>
        @if ($r->requires_id)
          @if ($r->id_photo_path)
            <a class="ba-idphoto" href="{{ route('attendant.reservations.id-photo', $r) }}" target="_blank" rel="noopener" aria-label="Open the PWD ID photo for {{ $r->code }} in a new tab">
              <img src="{{ route('attendant.reservations.id-photo', $r) }}" alt="PWD ID photo submitted for {{ $r->code }}" loading="lazy">
              <span>Open full size</span>
            </a>
          @else
            <p class="ba-warn">No ID photo came with this request. Reject it so the driver can try again.</p>
          @endif
        @endif
        <div class="actions">
          <button class="btn ghost" type="button" data-open="#rej-{{ $r->id }}">Reject</button>
          <form method="POST" action="{{ route('attendant.reservations.approve', $r) }}" data-busy>
            @csrf
            <button class="btn primary" type="submit" @disabled($r->requires_id && ! $r->id_photo_path)>Approve</button>
          </form>
        </div>
      </article>

      @push('dialogs')
      <dialog class="badlg" id="rej-{{ $r->id }}" aria-labelledby="rej-{{ $r->id }}-t">
        <form class="in" method="POST" action="{{ route('attendant.reservations.reject', $r) }}" data-reject>
          @csrf
          <button class="x" type="button" data-close aria-label="Close">&times;</button>
          <h2 id="rej-{{ $r->id }}-t">Reject {{ $r->code }}?</h2>
          <p>The driver is told right away and must choose a new slot. Slot {{ $r->slot_code }} is released.</p>
          <label class="ba-label" for="rej-{{ $r->id }}-why">Reason</label>
          <select class="ba-input" id="rej-{{ $r->id }}-why" name="reason" required data-reject-reason>
            <option value="" disabled selected>Choose a reason</option>
            @foreach ($reasons as $reason)<option value="{{ $reason }}">{{ $reason }}</option>@endforeach
            <option value="other">Other (type it)</option>
          </select>
          <div data-reject-other hidden style="margin-top:10px">
            <label class="ba-label" for="rej-{{ $r->id }}-other">Type the reason</label>
            <input class="ba-input" id="rej-{{ $r->id }}-other" name="reason_other" type="text" maxlength="250" placeholder="A few words the driver will understand">
          </div>
          <div class="actions">
            <button class="btn ghost" type="button" data-close>Keep waiting</button>
            <button class="btn primary" type="submit">Reject reservation</button>
          </div>
        </form>
      </dialog>
      @endpush
    @endforeach
  </div>
@endif

@if ($decided->isNotEmpty())
  <h2 style="font-size:18px;margin:28px 0 10px">Decided today</h2>
  <div class="ba-card">
    <ul class="ba-decided">
      @foreach ($decided as $r)
        <li>
          <b>{{ $r->code }}</b> {{ \App\Support\Lot::plate($r->plate_number) }}, slot {{ $r->slot_code }}
          <span class="ba-pill {{ $r->approval_status === 'approved' ? 'ok' : 'warn' }}">{{ $r->approval_status === 'approved' ? 'Approved' : 'Rejected' }}</span>
          @if ($r->approval_status === 'rejected' && $r->rejection_reason)<small>{{ $r->rejection_reason }}</small>@endif
        </li>
      @endforeach
    </ul>
  </div>
@endif
@endsection
