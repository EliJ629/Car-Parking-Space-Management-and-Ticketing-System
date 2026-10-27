{{-- One box for every incident report behind a ban, with all of its details. Needs $ban, $counted and $incidentRoute (a route name or null). --}}
@php $reports = $counted($ban->plate_number); @endphp
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-top:10px;">
  @forelse ($reports as $i)
    <article style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;background:var(--surface, transparent);min-width:0;">
      <header style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;margin-bottom:8px;">
        <b>@if ($incidentRoute)<a href="{{ route($incidentRoute, $i) }}">{{ $i->code }}</a>@else{{ $i->code }}@endif</b>
        <span style="font-size:12px;text-transform:uppercase;color:var(--muted);">{{ \App\Models\Incident::statusLabel($i->status) }}</span>
      </header>
      <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-size:14px;">
        <dt style="color:var(--muted);">Category</dt><dd style="margin:0;">{{ $i->title() }}</dd>
        <dt style="color:var(--muted);">Source</dt><dd style="margin:0;">{{ $i->source === 'auto' ? 'Automatic' : 'Manual' }}</dd>
        <dt style="color:var(--muted);">Reported</dt><dd style="margin:0;">{{ \App\Support\Lot::stamp($i->reported_at) }}</dd>
        <dt style="color:var(--muted);">Reported by</dt><dd style="margin:0;">{{ $i->source === 'auto' ? 'Bayline detection' : ($i->reporter?->name ?? '-') }}</dd>
        <dt style="color:var(--muted);">Plate</dt><dd style="margin:0;">{{ \App\Support\Lot::plate($i->attributed_plate ?: $i->plate_number) }}</dd>
        <dt style="color:var(--muted);">Location</dt><dd style="margin:0;">{{ $i->location ?: '-' }}</dd>
        <dt style="color:var(--muted);">Confirmed</dt><dd style="margin:0;">{{ \App\Support\Lot::stamp($i->confirmed_at) }} by {{ $i->confirmer?->name ?? 'the system' }}@if ($i->confirmation_note)<br><small>{{ $i->confirmation_note }}</small>@endif</dd>
        <dt style="color:var(--muted);">Driver told</dt><dd style="margin:0;">{{ $i->driver_notified ? 'Yes, '.($i->driver_notice_channel === 'sms' ? 'by text' : 'in the app').($i->driver_notified_at ? ' at '.\App\Support\Lot::stamp($i->driver_notified_at) : '') : 'No' }}</dd>
        <dt style="color:var(--muted);">Photos</dt><dd style="margin:0;">{{ $i->photos_count }}</dd>
        @if ($i->resolution_note)<dt style="color:var(--muted);">Resolution</dt><dd style="margin:0;">{{ $i->resolution_note }}</dd>@endif
      </dl>
      <p style="margin:10px 0 0;font-size:14px;white-space:pre-line;overflow-wrap:anywhere;"><span style="color:var(--muted);">What happened:</span> {{ $i->description }}</p>
    </article>
  @empty
    <p style="color:var(--muted);">No confirmed report is on file for this ban.</p>
  @endforelse
</div>
