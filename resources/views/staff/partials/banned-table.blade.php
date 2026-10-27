{{-- Shared by the Guard and the booth attendant. $bans, $counted and $incidentLinks come from BannedListController. --}}
@forelse ($bans as $ban)
  <section style="border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:16px;">
    <header style="display:flex;flex-wrap:wrap;gap:8px 24px;align-items:baseline;justify-content:space-between;">
      <h2 style="margin:0;font-size:22px;">{{ \App\Support\Lot::plate($ban->plate_number) }}</h2>
      <span style="color:var(--muted);">Banned {{ \App\Support\Lot::local($ban->banned_at)?->format('M j, Y g:i A') }}</span>
    </header>
    <dl style="margin:10px 0 0;display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:14px;">
      <dt style="color:var(--muted);">Driver</dt><dd style="margin:0;">{{ $ban->driver_name ?: ($ban->owner?->name ?? 'Walk-in driver') }}</dd>
      <dt style="color:var(--muted);">Mobile</dt><dd style="margin:0;">{{ $ban->driver_phone ?: ($ban->owner?->phone ?: 'Not on record') }}</dd>
      <dt style="color:var(--muted);">Why</dt><dd style="margin:0;">Banned because of numerous incident reports. {{ $ban->reason }}</dd>
    </dl>
    @include('staff.partials.ban-incident-boxes', ['ban' => $ban, 'incidentRoute' => $incidentLinks ? 'guard.incidents.show' : null])
  </section>
@empty
  <p style="padding:24px 8px;text-align:center;color:var(--muted);">{{ $q !== '' ? 'No banned driver matches this search.' : 'No vehicle is banned.' }}</p>
@endforelse
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span>Showing {{ $bans->firstItem() ?? 0 }}&ndash;{{ $bans->lastItem() ?? 0 }} of {{ $bans->total() }}</span>
  {{ $bans->links() }}
</div>
