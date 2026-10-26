<section class="manager-page-heading">
  <p class="eyebrow">Parking manager</p>
  <div class="manager-heading-row">
    <div>
      <h1>Parking slots</h1>
      <p class="manager-page-lede">Create, change, take out of service, or remove slots. Only a manager can change the lot layout.</p>
    </div>
    <a class="btn sm" href="{{ route('manager.slots.create') }}">Add slot</a>
  </div>
</section>

<form class="manager-filter-panel" method="GET" action="{{ route('manager.slots.index') }}" data-auto-filter>
  <div class="manager-filter-field">
    <label class="f" for="filter-status">Status</label>
    <select id="filter-status" name="status">
      <option value="">All statuses</option>
      @foreach ($statuses as $value => $label)
        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="manager-filter-field">
    <label class="f" for="filter-floor">Floor</label>
    <select id="filter-floor" name="floor">
      <option value="">All floors</option>
      @foreach ($floors as $floor)
        <option value="{{ $floor }}" @selected((string) ($filters['floor'] ?? '') === (string) $floor)>Floor {{ $floor }}</option>
      @endforeach
    </select>
  </div>
  <div class="manager-filter-field">
    <label class="f" for="filter-zone">Zone</label>
    <select id="filter-zone" name="zone">
      <option value="">All zones</option>
      @foreach ($zones as $zone)
        <option value="{{ $zone }}" @selected(($filters['zone'] ?? '') === $zone)>{{ $zone }}</option>
      @endforeach
    </select>
  </div>
  <div class="manager-filter-field manager-search-field">
    <label class="f" for="filter-search">Search</label>
    <input id="filter-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Slot, zone, or note">
  </div>
  @if (array_filter($filters))
    <a class="manager-text-link" href="{{ route('manager.slots.index') }}">Clear</a>
  @endif
</form>

<div class="manager-table-wrap">
  <table class="manager-table">
    <thead>
      <tr><th scope="col">Slot</th><th scope="col">Floor</th><th scope="col">Zone</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Notes</th><th scope="col">Actions</th></tr>
    </thead>
    <tbody>
      @forelse ($slots as $slot)
        <tr>
          <th scope="row"><a class="manager-slot-code" href="{{ route('manager.slots.edit', $slot) }}">{{ $slot->code }}</a></th>
          <td>{{ $slot->floorNumber() ? 'Floor '.$slot->floorNumber() : '—' }}</td>
          <td>{{ $slot->zone }}</td>
          <td>{{ $slot->type }}</td>
          <td>
            <span class="badge manager-status-badge manager-status-{{ str_replace('_', '-', $slot->status) }}">{{ $slot->statusLabel() }}</span>
            @if ($slot->out_of_service_reason)
              <small class="manager-status-note">{{ $slot->out_of_service_reason }}</small>
            @endif
          </td>
          <td>{{ $slot->notes ?: '—' }}</td>
          <td>
            <div class="manager-row-actions">
              @if (in_array($slot->status, [\App\Models\ParkingSlot::STATUS_OCCUPIED, \App\Models\ParkingSlot::STATUS_RESERVED], true))
                <span class="manager-action-disabled" title="Occupied and reserved slots cannot be edited">Edit unavailable</span>
              @else
                <a href="{{ route('manager.slots.edit', $slot) }}">Edit</a>
              @endif
              @if ($slot->canBeTakenOutOfService())
                <a href="{{ route('manager.slots.service.edit', $slot) }}">Out of service</a>
              @elseif ($slot->status === \App\Models\ParkingSlot::STATUS_OUT_OF_SERVICE)
                <form method="POST" action="{{ route('manager.slots.service.update', $slot) }}">
                  @csrf
                  @method('PATCH')
                  <button type="submit">Return to service</button>
                </form>
              @else
                <span class="manager-action-disabled" title="A slot in use must be released through its parking session">In use</span>
              @endif
              @if ($slot->canBeRemoved())
                <form method="POST" action="{{ route('manager.slots.destroy', $slot) }}" onsubmit="return confirm('Remove {{ $slot->code }} from the lot?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit">Remove</button>
                </form>
              @else
                <span class="manager-action-disabled" title="Occupied, reserved, and pending-exit slots cannot be removed">Remove</span>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td class="manager-table-empty" colspan="7">No slots match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="manager-table-footer">
  <span>Showing {{ $slots->firstItem() ?? 0 }}–{{ $slots->lastItem() ?? 0 }} of {{ $slots->total() }}</span>
  <div class="manager-pagination" aria-label="Slot list pages">
    @if ($slots->previousPageUrl())
      <a href="{{ $slots->previousPageUrl() }}" rel="prev">Previous</a>
    @else
      <span aria-disabled="true">Previous</span>
    @endif
    <strong>{{ $slots->currentPage() }}</strong>
    @if ($slots->nextPageUrl())
      <a href="{{ $slots->nextPageUrl() }}" rel="next">Next</a>
    @else
      <span aria-disabled="true">Next</span>
    @endif
  </div>
</div>
