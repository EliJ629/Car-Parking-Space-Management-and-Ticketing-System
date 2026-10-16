<section class="manager-page-heading">
  <p class="eyebrow">Accounts and roles</p><div class="manager-heading-row"><div><h1>Driver accounts</h1><p class="manager-page-lede">Review registered driver profiles and suspend accounts when needed.</p></div></div>
</section>
<nav class="manager-tabs" aria-label="Account type"><a href="{{ route('manager.accounts.index') }}">Staff accounts</a><a class="active" aria-current="page" href="{{ route('manager.drivers.index') }}">Driver accounts</a></nav>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.drivers.index') }}">
  <div class="manager-filter-field"><label class="f" for="driver-status">Status</label><select id="driver-status" name="status"><option value="">All</option>@foreach (['active','suspended','inactive'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="driver-q">Search</label><input id="driver-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email"></div><button class="btn sm" type="submit">Filter</button>
</form>
<div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Vehicles</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse ($drivers as $driver)
<tr><th scope="row">{{ $driver->name }}</th><td>{{ $driver->email }}</td><td>{{ $driver->phone ?? '—' }}</td><td>{{ $driver->vehicles_count }}</td><td><span class="badge {{ $driver->status === 'active' ? 'b-ok' : 'b-stop' }}">{{ strtoupper($driver->status) }}</span></td><td><div class="manager-row-actions"><a href="{{ route('manager.drivers.show', $driver) }}">View</a><form method="POST" action="{{ route('manager.accounts.status', $driver) }}" onsubmit="return confirm('{{ $driver->status === 'active' ? 'Suspend' : 'Reactivate' }} this driver?')">@csrf @method('PATCH')<button type="submit">{{ $driver->status === 'active' ? 'Suspend' : 'Reactivate' }}</button></form></div></td></tr>
@empty <tr><td colspan="6" class="manager-table-empty">No driver accounts found.</td></tr> @endforelse
</tbody></table></div>
<div class="manager-table-footer"><span>{{ $drivers->total() }} driver accounts</span>{{ $drivers->links() }}</div>
