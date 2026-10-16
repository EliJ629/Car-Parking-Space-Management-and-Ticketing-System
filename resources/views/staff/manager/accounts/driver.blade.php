<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.drivers.index') }}">Driver accounts</a> / Profile</p><h1>{{ $driver->name }}</h1><p class="manager-page-lede">Read-only driver details and registered vehicles.</p>
</section>
<section class="manager-panel manager-detail-panel">
  <div class="manager-data-grid"><div><span>Email</span><strong>{{ $driver->email }}</strong></div><div><span>Mobile</span><strong>{{ $driver->phone ?? '—' }}</strong></div><div><span>Status</span><strong>{{ ucfirst($driver->status) }}</strong></div><div><span>Joined</span><strong>{{ $driver->created_at->format('M j, Y') }}</strong></div></div>
  <h2 class="manager-subheading">Vehicles</h2>
  @forelse ($driver->vehicles as $vehicle)<div class="manager-summary-row"><span>{{ $vehicle->displayPlate() }}</span><strong>{{ $vehicle->color }} {{ $vehicle->vehicle_type }}</strong></div>@empty<p class="manager-muted">No registered vehicles.</p>@endforelse
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.drivers.index') }}">Back</a><form method="POST" action="{{ route('manager.accounts.status', $driver) }}">@csrf @method('PATCH')<button class="btn sm" type="submit">{{ $driver->status === 'active' ? 'Suspend driver' : 'Reactivate driver' }}</button></form></div>
</section>
