<section class="manager-page-heading">
  <p class="eyebrow">Accounts and roles</p><div class="manager-heading-row"><div><h1>Driver accounts</h1><p class="manager-page-lede">Review registered driver profiles and suspend accounts when needed.</p></div></div>
</section>
<nav class="manager-tabs" aria-label="Account type"><a href="{{ route('manager.accounts.index') }}">Staff accounts</a><a class="active" aria-current="page" href="{{ route('manager.drivers.index') }}">Driver accounts</a></nav>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.drivers.index') }}" data-auto-filter>
  <div class="manager-filter-field"><label class="f" for="driver-status">Status</label><select id="driver-status" name="status"><option value="">All</option>@foreach (['active','suspended'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="driver-q">Search</label><input id="driver-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email" maxlength="100"></div>
  @if (array_filter($filters))
    <a class="manager-text-link" href="{{ route('manager.drivers.index') }}">Clear</a>
  @endif
</form>
<div class="manager-table-wrap"><table class="manager-table"><thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Vehicles</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse ($drivers as $driver)
<tr><th scope="row">{{ $driver->name }}</th><td>{{ $driver->email }}</td><td>{{ $driver->phone ?? '—' }}</td><td>{{ $driver->vehicles_count }}</td><td><span class="badge {{ $driver->status === 'active' ? 'b-ok' : 'b-stop' }}">{{ strtoupper($driver->status) }}</span></td><td><div class="manager-row-actions"><a href="{{ route('manager.drivers.show', $driver) }}">View</a><button type="button" data-open-dialog="driver-status-{{ $driver->id }}">{{ $driver->status === 'active' ? 'Suspend' : 'Reactivate' }}</button></div></td></tr>
@empty <tr><td colspan="6" class="manager-table-empty">No driver accounts found.</td></tr> @endforelse
</tbody></table></div>
@foreach ($drivers as $driver)
  <dialog class="manager-dialog" id="driver-status-{{ $driver->id }}" aria-labelledby="driver-status-title-{{ $driver->id }}" @if (session('openAccountModal') === "status-{$driver->id}") data-open-on-load @endif>
    <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.status', $driver) }}">
      @csrf @method('PATCH')
      <h2 id="driver-status-title-{{ $driver->id }}">{{ $driver->status === 'active' ? 'Suspend' : 'Reactivate' }} driver?</h2>
      <p class="manager-muted">{{ $driver->status === 'active' ? 'This will prevent' : 'This will allow' }} {{ $driver->name }} from signing in {{ $driver->status === 'active' ? 'until the account is reactivated' : 'again' }}.</p>
      <div class="manager-dialog-field"><label class="f" for="driver-manager-password-{{ $driver->id }}">Manager password</label><input id="driver-manager-password-{{ $driver->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
      @if (session('openAccountModal') === "status-{$driver->id}") @error('manager_password')<p class="err">{{ $message }}</p>@enderror @endif
      <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">{{ $driver->status === 'active' ? 'Suspend driver' : 'Reactivate driver' }}</button></div>
    </form>
  </dialog>
@endforeach
<div class="manager-table-footer"><span>{{ $drivers->total() }} driver accounts</span>{{ $drivers->links() }}</div>
