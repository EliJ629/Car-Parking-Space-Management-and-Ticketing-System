<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.slots.index') }}">Parking slots</a> / Edit slot</p>
  <h1>Edit slot {{ $slot->code }}</h1>
  <p class="manager-page-lede">Change the zone, type, or notes. The slot code cannot change.</p>
</section>

<section class="manager-form-panel">
  <form method="POST" action="{{ route('manager.slots.update', $slot) }}">
    @csrf
    @method('PATCH')
    @include('staff.manager.slots._form')
    <div class="manager-form-actions">
      <a class="btn ghost sm" href="{{ route('manager.slots.index') }}">Cancel</a>
      <button class="btn sm" type="submit">Save changes</button>
    </div>
  </form>
</section>

<section class="manager-current-status">
  <div>
    <p class="eyebrow">Current status</p>
    <strong>{{ $slot->statusLabel() }}</strong>
    @if ($slot->out_of_service_reason)
      <span>{{ $slot->out_of_service_reason }}{{ $slot->notes ? ': '.$slot->notes : '' }}</span>
    @endif
  </div>
  @if ($slot->canBeTakenOutOfService())
    <a class="manager-text-link" href="{{ route('manager.slots.service.edit', $slot) }}">Take out of service</a>
  @elseif ($slot->status === \App\Models\ParkingSlot::STATUS_OUT_OF_SERVICE)
    <form method="POST" action="{{ route('manager.slots.service.update', $slot) }}">
      @csrf
      @method('PATCH')
      <button class="manager-text-button" type="submit">Return to service</button>
    </form>
  @endif
</section>
