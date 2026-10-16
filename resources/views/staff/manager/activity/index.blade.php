<section class="manager-page-heading"><p class="eyebrow">Audit and accountability</p><h1>Activity log</h1><p class="manager-page-lede">Actions are recorded with their actor and time. Activity entries cannot be edited or deleted.</p></section>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.activity.index') }}">
  <div class="manager-filter-field"><label class="f" for="activity-date">Date</label><input id="activity-date" name="date" type="date" value="{{ $filters['date'] ?? '' }}"></div>
  <div class="manager-filter-field"><label class="f" for="activity-user">User</label><select id="activity-user" name="user_id"><option value="">All users</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
  <div class="manager-filter-field"><label class="f" for="activity-action">Action</label><select id="activity-action" name="action"><option value="">All actions</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ str_replace('_', ' ', ucfirst($action)) }}</option>@endforeach</select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="activity-search">Search</label><input id="activity-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Ticket, plate, slot, or action"></div>
  <button class="btn sm" type="submit">Filter</button>
</form>
<div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Time</th><th>User</th><th>Area</th><th>Action</th><th>Subject / details</th><th></th></tr></thead><tbody>
@forelse ($logs as $entry)
<tr><td>{{ \App\Support\Lot::local($entry->created_at)?->format('M j, Y g:i A') }}</td><td>{{ $entry->user?->name ?? 'System / removed user' }}</td><td>{{ $entry->area ?? '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($entry->action)) }}</td><td>{{ $entry->subject ?? '—' }}</td><td><a class="manager-text-link" href="{{ route('manager.activity.show', $entry) }}">Details</a></td></tr>
@empty<tr><td colspan="6" class="manager-table-empty">No activity matches these filters.</td></tr>@endforelse
</tbody></table></div>
<div class="manager-table-footer"><span>Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</span>{{ $logs->links() }}</div>
