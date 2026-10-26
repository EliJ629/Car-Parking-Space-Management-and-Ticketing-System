<section class="manager-page-heading"><p class="eyebrow">Safety and operations</p><h1>Banned vehicles</h1><p class="manager-page-lede">A vehicle is banned after {{ $threshold }} confirmed incidents. Banned vehicles cannot reserve a slot, be checked in or be added to a driver account. {{ $pending }} {{ \Illuminate\Support\Str::plural('report', $pending) }} waiting for review could add to a count.</p></section>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.bans.index') }}" data-auto-filter>
  <div class="manager-filter-field"><label class="f" for="ban-state">Show</label><select id="ban-state" name="state"><option value="active" @selected(($filters['state'] ?? 'active') === 'active')>Banned now</option><option value="lifted" @selected(($filters['state'] ?? '') === 'lifted')>Lifted</option></select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="ban-search">Plate</label><input id="ban-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="20" placeholder="ABC 1234" data-plate data-plate-optional></div>
  @if (array_filter($filters) && ! (count(array_filter($filters)) === 1 && ($filters['state'] ?? '') === 'active'))<a class="manager-text-link" href="{{ route('manager.bans.index') }}">Clear</a>@endif
</form>
<div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Plate</th><th>Reason</th><th>Incidents</th><th>Banned</th><th>Account</th><th>{{ ($filters['state'] ?? 'active') === 'lifted' ? 'Lifted' : 'Action' }}</th></tr></thead><tbody>
@forelse ($bans as $ban)
<tr>
  <th scope="row"><span class="manager-slot-code">{{ \App\Support\Lot::plate($ban->plate_number) }}</span></th>
  <td>{{ $ban->reason }}</td>
  <td>@foreach ($counted($ban->plate_number) as $i) <a href="{{ route('manager.incidents.show', $i) }}">{{ $i->code }}</a>@if (! $loop->last), @endif @endforeach</td>
  <td>{{ \App\Support\Lot::local($ban->banned_at)?->format('M j, Y g:i A') }}<br><small>{{ $ban->banner?->name ?? 'System' }}</small></td>
  <td>{{ $ban->owner?->name ?? 'No linked account' }}</td>
  <td>@if ($ban->lifted_at){{ \App\Support\Lot::local($ban->lifted_at)?->format('M j, Y g:i A') }}<br><small>{{ $ban->lifter?->name ?? 'Former staff' }}: {{ $ban->lift_note }}</small>@else
    <form method="POST" action="{{ route('manager.bans.lift', $ban) }}" data-busy>@csrf<input name="note" required minlength="3" maxlength="255" placeholder="Why lift the ban?" aria-label="Reason for lifting the ban for {{ \App\Support\Lot::plate($ban->plate_number) }}"><button class="btn ghost sm" type="submit">Lift ban</button></form>@endif</td>
</tr>
@empty<tr><td colspan="6" class="manager-table-empty">{{ ($filters['state'] ?? 'active') === 'lifted' ? 'No ban has been lifted.' : 'No vehicle is banned.' }}</td></tr>@endforelse
</tbody></table></div>
<div class="manager-table-footer"><span>Showing {{ $bans->firstItem() ?? 0 }}&ndash;{{ $bans->lastItem() ?? 0 }} of {{ $bans->total() }}</span>{{ $bans->links() }}</div>
