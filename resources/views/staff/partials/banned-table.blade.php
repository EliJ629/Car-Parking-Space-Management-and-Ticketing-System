{{-- Shared by the Guard and the booth attendant. $bans, $counted, $labelOf and $incidentLinks come from BannedListController. --}}
<div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
    <thead>
      <tr style="border-bottom:2px solid var(--line);color:var(--muted);font-size:12px;text-transform:uppercase;">
        <th style="padding:10px 8px;">Plate</th>
        <th style="padding:10px 8px;">Driver</th>
        <th style="padding:10px 8px;">Mobile</th>
        <th style="padding:10px 8px;">Banned</th>
        <th style="padding:10px 8px;">Why (reports)</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($bans as $ban)
        <tr style="border-bottom:1px solid var(--line);vertical-align:top;">
          <th scope="row" style="padding:10px 8px;white-space:nowrap;">{{ \App\Support\Lot::plate($ban->plate_number) }}</th>
          <td style="padding:10px 8px;">{{ $ban->driver_name ?: ($ban->owner?->name ?? 'Walk-in driver') }}</td>
          <td style="padding:10px 8px;white-space:nowrap;">{{ $ban->driver_phone ?: ($ban->owner?->phone ?: 'Not on record') }}</td>
          <td style="padding:10px 8px;white-space:nowrap;">{{ \App\Support\Lot::local($ban->banned_at)?->format('M j, Y g:i A') }}</td>
          <td style="padding:10px 8px;min-width:260px;">
            <div>{{ $ban->reason }}</div>
            <ul style="margin:6px 0 0;padding-left:18px;">
              @foreach ($counted($ban->plate_number) as $i)
                <li>
                  @if ($incidentLinks)<a href="{{ route('guard.incidents.show', $i) }}">{{ $i->code }}</a>@else<b>{{ $i->code }}</b>@endif
                  &middot; {{ $labelOf($i) }} &middot; {{ \App\Support\Lot::stamp($i->reported_at) }}@if ($i->location) &middot; {{ $i->location }}@endif
                  <br><small>{{ \Illuminate\Support\Str::limit((string) $i->description, 140) }}</small>
                </li>
              @endforeach
            </ul>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" style="padding:24px 8px;text-align:center;color:var(--muted);">{{ $q !== '' ? 'No banned driver matches this search.' : 'No vehicle is banned.' }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span>Showing {{ $bans->firstItem() ?? 0 }}&ndash;{{ $bans->lastItem() ?? 0 }} of {{ $bans->total() }}</span>
  {{ $bans->links() }}
</div>
