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
      <a class="manager-text-link" href="{{ route('manager.slots.index') }}">All slots</a>
    </div>

    @if ($slotsByZone->isEmpty())
      <div class="manager-empty">
        <h3>Your lot is ready to set up</h3>
        <p>Add the first parking slot to start building the live map.</p>
        <a class="btn sm" href="{{ route('manager.slots.create') }}">Add your first slot</a>
      </div>
    @else
      <div class="manager-lot">
        @foreach ($slotsByZone as $zone => $zoneSlots)
          <section class="manager-zone" aria-label="{{ $zone }}">
            <h3>{{ $zone }}</h3>
            <div class="manager-slot-grid">
              @foreach ($zoneSlots as $slot)
                <a class="manager-slot manager-slot-{{ str_replace('_', '-', $slot->status) }}"
                   href="{{ route('manager.slots.edit', $slot) }}"
                   aria-label="{{ $slot->code }}, {{ $slot->statusLabel() }}, {{ $slot->type }}">
                  <strong>{{ $slot->code }}</strong>
                  <span>{{ $slot->statusLabel() }}</span>
                </a>
              @endforeach
            </div>
          </section>
        @endforeach
        <div class="manager-lane" aria-label="Vehicle lane">
          <span>Entrance</span><i aria-hidden="true"></i><span>Exit</span>
        </div>
      </div>
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

    <section class="manager-panel manager-lot-summary">
      <p class="eyebrow">Lot summary</p>
      <h2>Current layout</h2>
      <div class="manager-summary-row"><span>Parking slots</span><strong>{{ $totalSlots }}</strong></div>
      <div class="manager-summary-row"><span>Zones</span><strong>{{ $zoneCount }}</strong></div>
      <div class="manager-summary-row"><span>Last slot change</span><strong>{{ $lastUpdated ? \Illuminate\Support\Carbon::parse($lastUpdated)->format('g:i A') : 'No changes yet' }}</strong></div>
      <p class="manager-summary-note">Vehicle activity and reservations will appear as booth and driver operations are connected.</p>
    </section>
  </aside>
</section>
