<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.drivers.index') }}">Driver accounts</a> / Profile</p><h1>{{ $driver->name }}</h1><p class="manager-page-lede">Read-only driver details and registered vehicles.</p>
</section>
<section class="manager-panel manager-detail-panel">
  <div class="manager-data-grid"><div><span>Email</span><strong>{{ $driver->email }}</strong></div><div><span>Mobile</span><strong>{{ $driver->phone ?? '—' }}</strong></div><div><span>Status</span><strong>{{ ucfirst($driver->status) }}</strong></div><div><span>Joined</span><strong>{{ \App\Support\Lot::local($driver->created_at)?->format('M j, Y') }}</strong></div></div>
  <h2 class="manager-subheading">Vehicles</h2>
  @forelse ($driver->vehicles as $vehicle)<div class="manager-summary-row"><span>{{ $vehicle->displayPlate() }}</span><strong>{{ $vehicle->color }} {{ $vehicle->vehicle_type }}</strong></div>@empty<p class="manager-muted">No registered vehicles.</p>@endforelse
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.drivers.index') }}">Back</a><button class="btn sm" type="button" data-open-dialog="driver-status-{{ $driver->id }}">{{ $driver->status === 'active' ? 'Suspend driver' : 'Reactivate driver' }}</button></div>
</section>
<dialog class="manager-dialog" id="driver-status-{{ $driver->id }}" aria-labelledby="driver-status-title-{{ $driver->id }}" @if (session('openAccountModal') === "status-{$driver->id}") data-open-on-load @endif>
  <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.status', $driver) }}">
    @csrf @method('PATCH')
    <h2 id="driver-status-title-{{ $driver->id }}">{{ $driver->status === 'active' ? 'Suspend' : 'Reactivate' }} driver?</h2>
    <p class="manager-muted">{{ $driver->status === 'active' ? 'This will prevent' : 'This will allow' }} {{ $driver->name }} from signing in {{ $driver->status === 'active' ? 'until the account is reactivated' : 'again' }}.</p>
    <div class="manager-dialog-field"><label class="f" for="driver-manager-password-{{ $driver->id }}">Manager password</label><input id="driver-manager-password-{{ $driver->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
    <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">{{ $driver->status === 'active' ? 'Suspend driver' : 'Reactivate driver' }}</button></div>
  </form>
</dialog>
