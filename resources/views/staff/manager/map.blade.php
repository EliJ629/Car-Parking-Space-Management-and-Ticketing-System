<section class="manager-page-heading">
  <p class="eyebrow">Parking manager</p>
  <div class="manager-heading-row">
    <div>
      <h1>Live parking map</h1>
      <p class="manager-page-lede">View one floor at a time. Switch floors to see that floor’s zones and slot status.</p>
    </div>
    <a class="btn sm" href="{{ route('manager.slots.index') }}">Manage slots</a>
  </div>
</section>

@if ($floors !== [])
  <nav class="manager-tabs" aria-label="Parking floors">
    @foreach ($floors as $floor)
      <a href="{{ route('manager.map', ['floor' => $floor]) }}" class="{{ (int) $selectedFloor === (int) $floor ? 'active' : '' }}" @if ((int) $selectedFloor === (int) $floor) aria-current="page" @endif>Floor {{ $floor }}</a>
    @endforeach
  </nav>
@endif

<section class="manager-dashboard-grid">
  <div class="manager-panel manager-lot-panel">
    <div class="manager-panel-heading">
      <div>
        <p class="eyebrow">Current layout</p>
        <h2>Floor {{ $selectedFloor }}</h2>
      </div>
      <div class="manager-layout-actions" data-layout-actions>
        @if ($slotsByZone->isNotEmpty())
          <button class="btn ghost sm" type="button" data-layout-edit data-open-dialog="layout-editor-dialog">Edit Layout</button>
          <button class="btn ghost sm" type="button" data-layout-auto hidden>Auto Layout</button>
          <button class="btn ghost sm" type="button" data-layout-cancel hidden>Cancel</button>
          <button class="btn sm" type="button" data-layout-save hidden>Save Layout</button>
        @endif
        <a class="manager-text-link" href="{{ route('manager.slots.index', ['floor' => $selectedFloor]) }}">Slots on this floor</a>
      </div>
    </div>

    @if ($slotsByZone->isEmpty())
      <div class="manager-empty">
        <h3>{{ $floors === [] ? 'Your lot is ready to set up' : 'This floor has no parking slots yet' }}</h3>
        <p>{{ $floors === [] ? 'Add the first parking slot to start building the live map.' : 'Add a slot on this floor from Parking slots.' }}</p>
        <a class="btn sm" href="{{ route('manager.slots.create') }}">Add a parking slot</a>
      </div>
    @else
      <div class="manager-layout-workspace">
        <div class="manager-lot manager-layout" data-layout-editor data-save-url="{{ route('manager.layout.update') }}" data-authorize-url="{{ route('manager.layout.authorize') }}">
          <div class="manager-layout-canvas" data-layout-canvas>
            @foreach ($slotsByZone as $zone => $zoneSlots)
              @php($zonePosition = $zonePositions[$zone] ?? null)
              @php($vehicleType = $zonePosition?->vehicle_type ?? \App\Models\ParkingLayoutZone::VEHICLE_CAR)
              <span class="manager-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
                    data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">{{ $zone }} <small>({{ $vehicleType }})</small></span>
            @endforeach
            @foreach ($slotsByZone as $zone => $zoneSlots)
              @foreach ($zoneSlots as $slot)
                <a class="manager-slot manager-layout-slot manager-slot-{{ str_replace('_', '-', $slot->status) }} {{ in_array($slot->status, [\App\Models\ParkingSlot::STATUS_OCCUPIED, \App\Models\ParkingSlot::STATUS_RESERVED], true) ? 'manager-slot-readonly' : '' }}"
                   @if (! in_array($slot->status, [\App\Models\ParkingSlot::STATUS_OCCUPIED, \App\Models\ParkingSlot::STATUS_RESERVED], true)) href="{{ route('manager.slots.edit', $slot) }}" @endif
                   data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
                   data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
                   aria-label="{{ $slot->code }}, {{ $slot->statusLabel() }}, {{ $slot->type }}"
                   @if (in_array($slot->status, [\App\Models\ParkingSlot::STATUS_OCCUPIED, \App\Models\ParkingSlot::STATUS_RESERVED], true)) aria-disabled="true" title="Occupied and reserved slots cannot be edited" @endif>
                  <strong>{{ $slot->code }}</strong>
                    @if ($slot->type === \App\Models\ParkingSlot::TYPE_PWD)<small class="manager-pwd-marker" aria-label="PWD parking slot">PWD</small>@endif
                    <span>{{ $slot->statusLabel() }}</span>
                </a>
              @endforeach
            @endforeach
          </div>
          <div class="manager-lane" aria-label="Vehicle lane">
            <span>Entrance</span><i aria-hidden="true"></i><span>Exit</span>
          </div>
        </div>
      </div>
      <form class="manager-layout-form" data-layout-form method="POST" action="{{ route('manager.layout.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="floor" value="{{ $selectedFloor }}">
        <div data-layout-inputs></div>
      </form>
      <div class="manager-legend" aria-label="Slot status legend">
        @foreach (\App\Models\ParkingSlot::statuses() as $status => $label)
          <span><i class="manager-legend-{{ str_replace('_', '-', $status) }}" aria-hidden="true"></i>{{ $label }}</span>
        @endforeach
      </div>
    @endif
  </div>

  <aside class="manager-side-stack">
    <section class="manager-panel manager-lot-summary">
      <p class="eyebrow">This floor</p>
      <h2>Floor {{ $selectedFloor }}</h2>
      <div class="manager-summary-row"><span>Parking slots</span><strong>{{ $slotCount }}</strong></div>
      <div class="manager-summary-row"><span>Zones</span><strong>{{ $zoneCount }} / {{ $maxZonesPerFloor }}</strong></div>
      <p class="manager-summary-note">A floor can only contain 4 zones. Only zones that already have slots appear on the map.</p>
    </section>

    <dialog class="manager-dialog manager-layout-dialog" id="layout-editor-dialog" aria-labelledby="layout-editor-title">
      <div class="manager-dialog-inner">
        <button class="manager-dialog-close" type="button" data-close-dialog aria-label="Close editor">X</button>
        <p class="eyebrow">Parking layout editor</p>
        <h2 id="layout-editor-title">Edit live lot map</h2>
        <p class="manager-muted">Pan across this floor, then arrange slots and zone indicators before saving.</p>
        <div class="manager-layout-occupied-gate" data-layout-occupied-gate hidden>
          <h3>Occupied slots detected</h3>
          <p>Editing the physical layout while vehicles are inside can cause an unsafe map. Confirm that you want to continue with your manager password.</p>
          <form data-layout-occupied-form>
            <label class="f" for="layout-manager-password">Manager password</label>
            <input id="layout-manager-password" name="password" type="password" autocomplete="current-password" required>
            <p class="hint badline" data-layout-occupied-error hidden></p>
            <div class="manager-dialog-actions">
              <button class="btn ghost sm" type="button" data-layout-gate-cancel>Cancel</button>
              <button class="btn sm" type="submit">Confirm and edit</button>
            </div>
          </form>
        </div>
        <div data-layout-modal-content></div>
      </div>
    </dialog>
  </aside>
</section>
