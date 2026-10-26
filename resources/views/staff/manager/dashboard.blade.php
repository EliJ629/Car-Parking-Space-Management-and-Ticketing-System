<section class="manager-page-heading">
  <p class="eyebrow">Parking manager</p>
  <div class="manager-heading-row">
    <div>
      <h1>Dashboard</h1>
      <p class="manager-page-lede">Live view of the lot and the slots that need attention.</p>
    </div>
    <div class="manager-heading-actions">
      <a class="btn ghost sm" href="{{ route('manager.map') }}">Live parking map</a>
      <a class="btn sm" href="{{ route('manager.slots.index') }}">Manage slots</a>
    </div>
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

<section class="manager-panel manager-dashboard-analytics" aria-labelledby="dashboard-analytics-title">
  <div class="manager-panel-heading">
    <div>
      <p class="eyebrow">Last seven days</p>
      <h2 id="dashboard-analytics-title">Reports snapshot</h2>
    </div>
    <a class="manager-text-link" href="{{ route('manager.reports.index') }}">Open full report</a>
  </div>
  <div class="manager-stat-grid manager-dashboard-report-stats">
    <div class="manager-stat"><strong>₱{{ number_format($analytics['revenue'], 2) }}</strong><span>Revenue</span></div>
    <div class="manager-stat"><strong>{{ $analytics['sessionCount'] }}</strong><span>Parking sessions</span></div>
    <div class="manager-stat"><strong>{{ $analytics['averageOccupancy'] }}%</strong><span>Average occupancy</span></div>
    <div class="manager-stat"><strong>{{ $analytics['peakHour'] ?? '—' }}</strong><span>Peak entry hour</span></div>
    <div class="manager-stat"><strong>{{ $analytics['averageStayMinutes'] > 0 ? floor($analytics['averageStayMinutes'] / 60).' h '.((int) $analytics['averageStayMinutes'] % 60).' min' : '—' }}</strong><span>Average stay</span></div>
    <div class="manager-stat"><strong>{{ $analytics['reservationTotal'] > 0 ? round($analytics['reservationsKept'] / $analytics['reservationTotal'] * 100).'%' : '—' }}</strong><span>Reservations checked in</span></div>
    <div class="manager-stat"><strong>{{ $analytics['incidents'] }}</strong><span>Incidents</span></div>
  </div>
  <div class="manager-dashboard-analytics-grid">
    <div>
      <p class="eyebrow">Arrival activity</p>
      <div class="manager-chart manager-hour-chart">
        @foreach ($analytics['hourly'] as $hour => $count)
          <div class="manager-chart-column"><strong>{{ $count }}</strong><i style="height:{{ max(4, min(110, $count * 12)) }}px"></i><span>{{ \Illuminate\Support\Carbon::createFromTime($hour)->format('ga') }}</span></div>
        @endforeach
      </div>
    </div>
    <div class="manager-dashboard-report-summary">
      <p class="eyebrow">Reservation results</p>
      <div class="manager-summary-row"><span>Checked in</span><strong>{{ $analytics['reservationsKept'] }}</strong></div>
      <div class="manager-summary-row"><span>Other reservation statuses</span><strong>{{ max(0, $analytics['reservationTotal'] - $analytics['reservationsKept']) }}</strong></div>
      <p class="manager-muted">Data from {{ $analytics['from']->format('M j') }} to {{ $analytics['to']->format('M j, Y') }}.</p>
    </div>
  </div>
</section>

<section class="manager-dashboard-grid">
  <div class="manager-panel manager-lot-panel">
    <div class="manager-panel-heading">
      <div>
        <p class="eyebrow">Current layout</p>
        <h2>Live parking map</h2>
      </div>
      <a class="btn sm" href="{{ route('manager.map') }}">Open live map</a>
    </div>
    <p class="manager-muted">The live map shows one floor at a time. Open it to switch floors, check slot status, and edit the layout.</p>
    <div class="manager-summary-row"><span>Parking slots</span><strong>{{ $totalSlots }}</strong></div>
    <div class="manager-summary-row"><span>Floors</span><strong>{{ $floorCount }}</strong></div>
    <div class="manager-summary-row"><span>Zones</span><strong>{{ $zoneCount }}</strong></div>
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
      <div class="manager-summary-row"><span>Floors</span><strong>{{ $floorCount }}</strong></div>
      <div class="manager-summary-row"><span>Zones</span><strong>{{ $zoneCount }}</strong></div>
      <div class="manager-summary-row"><span>Last slot change</span><strong>{{ $lastUpdated ? \App\Support\Lot::time(\Illuminate\Support\Carbon::parse($lastUpdated)) : 'No changes yet' }}</strong></div>
      <p class="manager-summary-note">Vehicle activity and reservations will appear as booth and driver operations are connected.</p>
    </section>
  </aside>
</section>
