@extends('layouts.guard')

@section('title', 'Incident '.$incident->code.' - Bayline')
@section('tab', 'incidents')

@section('content')
@php
    $i = $incident;
    $auto = $i->source === 'auto';
    $notes = $i->notes->where('kind', 'note');
    $allowed = ['open' => ['open', 'under_review', 'resolved'], 'under_review' => ['under_review', 'resolved'], 'resolved' => ['resolved']][$i->status];
@endphp
<h1 class="g-title">Incident {{ $i->code }}</h1>
<p class="g-sub">{{ \App\Models\Incident::guardLabel($i->category) }}, {{ $auto ? 'detected automatically' : 'reported manually' }}.</p>

<div class="g-actions" style="margin-top:12px">
  @if ($i->status !== 'resolved')<button class="btn" type="button" data-open="#status-dlg">Update status</button>@endif
  <a class="btn ghost" href="{{ route('guard.incidents') }}">Back to list</a>
</div>

<div class="g-two" style="margin-top:16px">
  <div class="g-stack">
    <section class="g-card">
      <h2>Report</h2>
      <dl class="g-kv">
        <dt>Category</dt><dd>{{ \App\Models\Incident::guardLabel($i->category) }}</dd>
        <dt>Source</dt><dd><span class="g-tag ink">{{ $auto ? 'Automatic' : 'Manual' }}</span></dd>
        <dt>Status</dt><dd>@include('guard.partials.incident-status', ['status' => $i->status])</dd>
        <dt>Reported by</dt><dd>{{ $auto ? 'Bayline detection' : ($i->reporter?->name ?? '-') }}</dd>
        <dt>Reported</dt><dd>{{ \App\Support\Lot::stamp($i->reported_at) }}</dd>
        <dt>Plate</dt><dd>{{ $i->plate_number ? \App\Support\Lot::plate($i->plate_number) : '-' }}</dd>
        <dt>Location</dt><dd>{{ $i->slot?->code ?? $i->location ?? '-' }}</dd>
        @if ($auto)
          <dt>Detections</dt><dd>{{ $i->detection_count }}@if ($i->last_seen_at), last seen {{ \App\Support\Lot::stamp($i->last_seen_at) }}@endif</dd>
        @endif
        @if ($i->status === 'resolved')<dt>Resolved by</dt><dd>{{ $i->resolver?->name ?? 'Bayline' }}, {{ \App\Support\Lot::stamp($i->resolved_at) }}</dd>@endif
      </dl>
      <h3 style="margin-top:18px">Description</h3>
      <p class="g-note" style="margin-top:6px">{{ $i->description }}</p>
      @if ($i->resolution_note)<h3 style="margin-top:14px">Resolution</h3><p class="g-note" style="margin-top:6px">{{ $i->resolution_note }}</p>@endif
      @if ($i->photos->isNotEmpty())
        <h3 style="margin-top:14px">Photos ({{ $i->photos->count() }})</h3>
        <div class="g-thumbs">
          @foreach ($i->photos as $p)<a href="{{ route('staff.incident-photos.show', $p) }}" target="_blank" rel="noopener"><img src="{{ route('staff.incident-photos.show', $p) }}" alt="{{ $p->original_name }}" loading="lazy"></a>@endforeach
        </div>
      @endif
    </section>
  </div>

  <div class="g-stack">
    <section class="g-card">
      <h2>Timeline</h2>
      <ol class="g-tl">
        @foreach ($events as $e)<li class="{{ $e['state'] === 'now' ? 'now' : '' }}"><b>{{ $e['title'] }}</b><span>{{ $e['detail'] }}</span></li>@endforeach
      </ol>
    </section>
    <section class="g-card">
      <h2>Driver notified</h2>
      <dl class="g-kv">
        <dt>Notified</dt><dd>{{ $i->driver_notified ? 'Yes' : 'No' }}</dd>
        @if (! $i->driver_notified && $i->driver_notice_reason)<dt>Reason</dt><dd>{{ $i->driver_notice_reason }}</dd>@endif
      </dl>
    </section>
    <section class="g-card">
      <h2>Notes</h2>
      @forelse ($notes as $n)<div class="g-note">{{ $n->body }}<small>{{ $n->user?->name ?? 'Bayline' }}, {{ \App\Support\Lot::stamp($n->created_at) }}</small></div>@empty<p class="hint" style="color:var(--muted);font-size:14px;margin-bottom:10px">No notes yet.</p>@endforelse
      <button class="btn ghost sm" type="button" data-open="#note-dlg">Add note</button>
    </section>
  </div>
</div>

@push('dialogs')
<dialog class="gdlg g-form" id="note-dlg" aria-labelledby="nt-t" @if ($errors->has('body')) data-autoopen @endif>
  <form class="in" method="POST" action="{{ route('guard.incidents.notes', $i) }}" data-busy data-busy-label="Saving">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="nt-t">Add note</h2>
    <label class="f" for="body">Note</label>
    <textarea id="body" name="body" maxlength="500" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
    @error('body')<p class="g-err">{{ $message }}</p>@enderror
    <div class="actions"><button class="btn" type="submit">Save note</button><button class="btn ghost" type="button" data-close>Cancel</button></div>
  </form>
</dialog>

@if ($i->status !== 'resolved')
<dialog class="gdlg g-form" id="status-dlg" aria-labelledby="st-t">
  <form class="in" method="POST" action="{{ route('guard.incidents.status', $i) }}" data-busy data-busy-label="Saving">
    @csrf
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="st-t">Update status</h2>
    <p>An incident only moves forward. A resolved incident cannot be reopened.</p>
    <div style="margin-top:12px;display:grid;gap:8px">
      @foreach ($allowed as $st)
        <label class="g-radio"><input type="radio" name="status" value="{{ $st }}" @checked(old('status', $i->status) === $st)><span>{{ \App\Models\Incident::statusLabel($st) }}@if ($st === $i->status) (current)@endif</span></label>
      @endforeach
    </div>
    <div class="fld" style="margin-top:14px">
      <label class="f" for="note">Note <span class="hint" style="display:inline">(required to resolve)</span></label>
      <textarea id="note" name="note" maxlength="500" style="min-height:90px">{{ old('note') }}</textarea>
    </div>
    <div class="actions"><button class="btn" type="submit">Save status</button><button class="btn ghost" type="button" data-close>Cancel</button></div>
  </form>
</dialog>
@endif
@endpush
@endsection
