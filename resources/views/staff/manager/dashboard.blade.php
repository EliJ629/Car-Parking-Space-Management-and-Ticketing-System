<section class="manager-page-heading">
  <p class="eyebrow">Parking manager</p>
  <div class="manager-heading-row">
    <div>
      <h1>Dashboard</h1>
      <p class="manager-page-lede">Live view of the lot and the slots that need attention.</p>
    </div>
    <a class="btn sm" href="{{ route('manager.slots.index') }}">Manage slots</a>
  </div>
</section>

<section class="manager-stat-grid" aria-label="Parking slot totals">
  @foreach ($statusCounts as $status => $count)
    <a class="manager-stat manager-stat-{{ str_replace('_', '-', $status) }}" href="{{ route('manager.slots.index', ['status' => $status]) }}">
      <strong>{{ $count }}</strong>
      <span>{{ \App\Models\ParkingSlot::statuses()[$status] }}</span>
    </a>
  @endforeach
  <a class="manager-stat" href="{{ route('manager.sales.index') }}">
    <strong>₱{{ number_format($salesToday, 2) }}</strong>
    <span>Sales today</span>
  </a>
  <a class="manager-stat manager-stat-out-of-service" href="{{ route('manager.incidents.index', ['status' => 'open']) }}">
    <strong>{{ $openIncidents }}</strong>
    <span>Open incidents</span>
  </a>
</section>

<section class="manager-dashboard-grid">
  <div class="manager-panel manager-lot-panel">
    <div class="manager-panel-heading">
      <div>
        <p class="eyebrow">Current layout</p>
        <h2>Live lot map</h2>
      </div>
      <div class="manager-layout-actions" data-layout-actions>
        <button class="btn ghost sm" type="button" data-layout-edit data-open-dialog="layout-editor-dialog">Edit Layout</button>
        <button class="btn ghost sm" type="button" data-layout-auto hidden>Auto Layout</button>
        <button class="btn ghost sm" type="button" data-layout-cancel hidden>Cancel</button>
        <button class="btn sm" type="button" data-layout-save hidden>Save Layout</button>
        <a class="manager-text-link" href="{{ route('manager.slots.index') }}">All slots</a>
      </div>
    </div>

    @if ($slotsByZone->isEmpty())
      <div class="manager-empty">
        <h3>Your lot is ready to set up</h3>
        <p>Add the first parking slot to start building the live map.</p>
        <a class="btn sm" href="{{ route('manager.slots.create') }}">Add your first slot</a>
      </div>
    @else
      <div class="manager-layout-workspace">
        <div class="manager-lot manager-layout" data-layout-editor data-save-url="{{ route('manager.layout.update') }}">
          <div class="manager-layout-canvas" data-layout-canvas>
            @foreach ($slotsByZone as $zone => $zoneSlots)
              @php($zonePosition = $zonePositions[$zone] ?? null)
              <span class="manager-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
                    data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">{{ $zone }}</span>
            @endforeach
            @foreach ($slotsByZone as $zone => $zoneSlots)
              @foreach ($zoneSlots as $slot)
                <a class="manager-slot manager-layout-slot manager-slot-{{ str_replace('_', '-', $slot->status) }}"
                   href="{{ route('manager.slots.edit', $slot) }}"
                   data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
                   data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
                   aria-label="{{ $slot->code }}, {{ $slot->statusLabel() }}, {{ $slot->type }}">
                  <strong>{{ $slot->code }}</strong>
                  <span>{{ $slot->statusLabel() }}</span>
                  @if (in_array($slot->status, ['occupied', 'pending_exit'], true) && $slot->parked_label)<small class="manager-slot-extra" title="Parked for">{{ $slot->parked_label }}</small>
                  @elseif ($slot->status === 'reserved' && $slot->booked_label)<small class="manager-slot-extra" title="Reserved for">{{ $slot->booked_label }}</small>@endif
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
    <section class="manager-panel">
      <div class="manager-panel-heading">
        <div><p class="eyebrow">Needs attention</p><h2>Slot alerts</h2></div>
        @if ($attentionSlots->isNotEmpty())
          <span class="manager-alert-count">{{ $attentionSlots->count() }}</span>
        @endif
      </div>
      @forelse ($attentionSlots as $slot)
        <a class="manager-attention-item" href="{{ route('manager.slots.edit', $slot) }}">
          <span class="manager-attention-copy">
            <strong>{{ $slot->code }} {{ strtolower($slot->statusLabel()) }}</strong>
            <small>
              @if ($slot->status === \App\Models\ParkingSlot::STATUS_OUT_OF_SERVICE)
                {{ $slot->out_of_service_reason }}{{ $slot->notes ? ': '.$slot->notes : '' }}
              @elseif ($slot->status === \App\Models\ParkingSlot::STATUS_PENDING_EXIT)
                Slot remains unavailable until the gate confirms the exit.
              @else
                Reserved slot; it remains unavailable for walk-ins.
              @endif
            </small>
          </span>
          <span class="badge {{ $slot->status === \App\Models\ParkingSlot::STATUS_OUT_OF_SERVICE ? 'b-done' : 'b-pend' }}">{{ $slot->statusLabel() }}</span>
        </a>
      @empty
        <p class="manager-muted">No reserved, pending-exit, or out-of-service slots right now.</p>
      @endforelse
      <a class="manager-text-link manager-panel-link" href="{{ route('manager.slots.index') }}">Review parking slots</a>
    </section>

    <dialog class="manager-dialog manager-layout-dialog" id="layout-editor-dialog" aria-labelledby="layout-editor-title">
      <div class="manager-dialog-inner">
        <button class="manager-dialog-close" type="button" data-close-dialog aria-label="Close editor">X</button>
        <p class="eyebrow">Parking layout editor</p>
        <h2 id="layout-editor-title">Edit live lot map</h2>
        <p class="manager-muted">Pan across the existing map, then arrange slots and zone indicators before saving.</p>
        <div data-layout-modal-content></div>
      </div>
    </dialog>

    <section class="manager-panel manager-lot-summary">
      <p class="eyebrow">Lot summary</p>
      <h2>Current layout</h2>
      <div class="manager-summary-row"><span>Parking slots</span><strong>{{ $totalSlots }}</strong></div>
      <div class="manager-summary-row"><span>Zones</span><strong>{{ $zoneCount }}</strong></div>
      <div class="manager-summary-row"><span>Last slot change</span><strong>{{ $lastUpdated ? \App\Support\Lot::time(\Illuminate\Support\Carbon::parse($lastUpdated)) : 'No changes yet' }}</strong></div>
      <p class="manager-summary-note">Vehicle activity and reservations will appear as booth and driver operations are connected.</p>
    </section>
  </aside>
</section>
