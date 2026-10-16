<section class="manager-page-heading"><p class="eyebrow">Manager reports</p><h1>Occupancy and activity</h1><p class="manager-page-lede">Occupancy, peak arrival hours, reservation results, and incidents derived from recorded activity.</p></section>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.reports.index') }}">
  <div class="manager-filter-field"><label class="f" for="report-from">From</label><input id="report-from" name="from" type="date" value="{{ $from }}" required></div>
  <div class="manager-filter-field"><label class="f" for="report-to">To</label><input id="report-to" name="to" type="date" value="{{ $to }}" required></div>
  <button class="btn sm" type="submit">Run report</button>
  <a class="btn ghost sm" href="{{ route('manager.reports.export', ['from' => $from, 'to' => $to]) }}">Export CSV</a>
</form>
@if ($sessionCount === 0)
  <div class="manager-empty"><h2>No data for this range</h2><p>No parking sessions overlap {{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}. Report values will be calculated from recorded parking sessions.</p><a class="manager-text-link" href="{{ route('manager.reports.index', ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()]) }}">Use last 7 days</a></div>
@endif
<section class="manager-stat-grid manager-report-stats">
  <div class="manager-stat"><strong>{{ $averageOccupancy }}%</strong><span>Average occupancy</span></div>
  <div class="manager-stat"><strong>{{ $peakHour ?? '—' }}</strong><span>Peak entry hour</span></div>
  <div class="manager-stat"><strong>{{ $averageStayMinutes > 0 ? floor($averageStayMinutes / 60).' h '.((int) $averageStayMinutes % 60).' min' : '—' }}</strong><span>Average stay</span></div>
  <div class="manager-stat"><strong>{{ $reservationTotal > 0 ? round($reservationsKept / $reservationTotal * 100).'%' : '—' }}</strong><span>Reservations checked in</span></div>
  <div class="manager-stat"><strong>{{ $incidents->sum() }}</strong><span>Incidents in range</span></div>
</section>
<section class="manager-dashboard-grid">
  <div class="manager-panel"><p class="eyebrow">Arrival activity</p><h2>Entries by hour</h2>
    <div class="manager-chart manager-hour-chart">
      @foreach ($hourly as $hour => $count)
        <div class="manager-chart-column"><strong>{{ $count }}</strong><i style="height:{{ max(4, min(110, $count * 12)) }}px"></i><span>{{ \Illuminate\Support\Carbon::createFromTime($hour)->format('ga') }}</span></div>
      @endforeach
    </div>
  </div>
  <div class="manager-panel"><p class="eyebrow">Reservation results</p><h2>Check-ins</h2><div class="manager-summary-row"><span>Checked in</span><strong>{{ $reservationsKept }}</strong></div><div class="manager-summary-row"><span>Not checked in / other status</span><strong>{{ max(0, $reservationTotal - $reservationsKept) }}</strong></div>
    <h3 class="manager-subheading">Incidents by category</h3>
    @forelse ($incidents as $category => $count)<div class="manager-summary-row"><span>{{ $category }}</span><strong>{{ $count }}</strong></div>@empty<p class="manager-muted">No incidents in this range.</p>@endforelse
  </div>
</section>
