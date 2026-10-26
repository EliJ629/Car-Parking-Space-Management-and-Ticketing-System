<section class="manager-page-heading"><p class="eyebrow">Manager reports</p><h1>Sales and revenue</h1><p class="manager-page-lede">Figures are calculated from paid cash receipts and match the Payments records.</p></section>
<form class="manager-filter-panel" method="GET" action="{{ route('manager.sales.index') }}" data-auto-filter>
  <div class="manager-filter-field"><label class="f" for="sales-from">From</label><input id="sales-from" name="from" type="date" value="{{ $from }}" data-range-start required></div>
  <div class="manager-filter-field"><label class="f" for="sales-to">To</label><input id="sales-to" name="to" type="date" value="{{ $to }}" data-range-end required></div>
  <a class="btn ghost sm" href="{{ route('manager.sales.export', ['from' => $from, 'to' => $to]) }}">Export CSV</a>
</form>
<section class="manager-stat-grid manager-sales-stats">
  <div class="manager-stat"><strong>₱{{ number_format((float) $todayTotal, 2) }}</strong><span>Sales today</span></div>
  <div class="manager-stat"><strong>₱{{ number_format((float) $weekTotal, 2) }}</strong><span>This week</span></div>
  <div class="manager-stat"><strong>₱{{ number_format((float) $monthTotal, 2) }}</strong><span>This month</span></div>
  <div class="manager-stat"><strong>₱{{ number_format((float) $average, 2) }}</strong><span>Average per paid session in range</span></div>
  <div class="manager-stat"><strong>{{ $payments->count() }}</strong><span>Paid sessions in range</span></div>
</section>
<section class="manager-dashboard-grid">
  <div class="manager-panel"><p class="eyebrow">Sales by day</p><h2>Paid receipts</h2>
    @if ($days->isEmpty())<p class="manager-empty">No paid receipts in this date range.</p>
    @else
      @php($maxSales = max(1, (float) $days->max()))
      <div class="manager-chart" aria-label="Sales by day">
        @foreach ($days as $day => $amount)
          <div class="manager-chart-column"><strong>₱{{ number_format($amount, 0) }}</strong><i style="height:{{ max(4, (int) round($amount / $maxSales * 150)) }}px"></i><span>{{ \Illuminate\Support\Carbon::parse($day)->format('M j') }}</span></div>
        @endforeach
      </div>
    @endif
  </div>
  <div class="manager-panel"><p class="eyebrow">Where the money came from</p><h2>Fee breakdown</h2>
    <div class="manager-summary-row"><span>Base fees</span><strong>₱{{ number_format($totals['base'], 2) }}</strong></div>
    <div class="manager-summary-row"><span>Additional hours</span><strong>₱{{ number_format($totals['additional'], 2) }}</strong></div>
    <div class="manager-summary-row"><span>Penalties</span><strong>₱{{ number_format($totals['penalties'], 2) }}</strong></div>
    <div class="manager-summary-row"><span>Discounts given</span><strong>-₱{{ number_format($totals['discounts'], 2) }}</strong></div>
    <p class="manager-summary-note">Cash is the only payment method; no online payments are included.</p>
  </div>
</section>
<section class="manager-panel manager-section-gap"><p class="eyebrow">Shift totals</p><h2>Sales by attendant</h2>
  <div class="manager-table-wrap"><table class="manager-table" data-paginate="10"><thead><tr><th>Attendant</th><th>Paid sessions</th><th>Cash collected</th><th>Discounts</th><th>Penalties</th></tr></thead><tbody>
  @forelse ($attendants as $attendant)<tr><th scope="row">{{ $attendant['name'] }}</th><td>{{ $attendant['sessions'] }}</td><td>₱{{ number_format($attendant['cash'], 2) }}</td><td>₱{{ number_format($attendant['discounts'], 2) }}</td><td>₱{{ number_format($attendant['penalties'], 2) }}</td></tr>@empty<tr><td colspan="5" class="manager-table-empty">No paid sessions in the selected range.</td></tr>@endforelse
  </tbody></table></div>
</section>
