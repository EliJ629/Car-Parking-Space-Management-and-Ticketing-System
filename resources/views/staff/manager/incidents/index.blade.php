<section class="manager-page-heading"><p class="eyebrow">Safety and operations</p><div class="manager-heading-row"><div><h1>Incidents</h1><p class="manager-page-lede">Review automatic long-parking alerts and reports raised by staff.</p></div><a class="btn sm" href="{{ route('manager.incidents.create') }}">Report incident</a></div></section>
<section class="manager-stat-grid manager-incident-stats">
  <div class="manager-stat"><strong>{{ $openCount }}</strong><span>Open or under review</span></div>
  <div class="manager-stat"><strong>{{ $autoOpenCount }}</strong><span>Automatic alerts open</span></div>
  <div class="manager-stat"><strong>{{ $weekCount }}</strong><span>Reported this week</span></div>
</section>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.incidents.index') }}" data-auto-filter>
  <div class="manager-filter-field"><label class="f" for="incident-status">Status</label><select id="incident-status" name="status"><option value="">All statuses</option>@foreach ($statuses as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ strtoupper(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
  <div class="manager-filter-field"><label class="f" for="incident-category">Category</label><select id="incident-category" name="category"><option value="">All categories</option>@foreach ($categories as $value)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $value }}</option>@endforeach</select></div>
  <div class="manager-filter-field"><label class="f" for="incident-source">Source</label><select id="incident-source" name="source"><option value="">Auto and manual</option><option value="auto" @selected(($filters['source'] ?? '') === 'auto')>Automatic</option><option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option></select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="incident-search">Search</label><input id="incident-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="ID, plate, or location"></div>
  @if (array_filter($filters))
    <a class="manager-text-link" href="{{ route('manager.incidents.index') }}">Clear</a>
  @endif
</form>
<div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>ID</th><th>Reported</th><th>Category</th><th>Source</th><th>Vehicle / location</th><th>By</th><th>Status</th></tr></thead><tbody>
@forelse ($incidents as $incident)
<tr><th scope="row"><a class="manager-slot-code" href="{{ route('manager.incidents.show', $incident) }}">{{ $incident->code }}</a></th><td>{{ \App\Support\Lot::local($incident->reported_at)?->format('M j, g:i A') }}</td><td>{{ $incident->category }}</td><td><span class="badge {{ $incident->source === 'auto' ? 'b-pend' : 'b-done' }}">{{ strtoupper($incident->source) }}</span></td><td>{{ $incident->plate_number ?? '—' }}{{ $incident->slot ? ' / '.$incident->slot->code : ($incident->location ? ' / '.$incident->location : '') }}</td><td>{{ $incident->reporter?->name ?? ($incident->source === 'auto' ? 'System' : 'Former staff') }}</td><td>{{ strtoupper(str_replace('_', ' ', $incident->status)) }}</td></tr>
@empty<tr><td colspan="7" class="manager-table-empty">No incidents match these filters.</td></tr>@endforelse
</tbody></table></div>
<div class="manager-table-footer"><span>Showing {{ $incidents->firstItem() ?? 0 }}–{{ $incidents->lastItem() ?? 0 }} of {{ $incidents->total() }}</span>{{ $incidents->links() }}</div>
