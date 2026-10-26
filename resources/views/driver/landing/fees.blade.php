@php
    $money = fn ($n) => '₱'.rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<section class="sec" id="fees" style="padding-top:8px"><div class="w two">
<div>
<p class="eyebrow">Fees</p>
<h2 style="margin-top:8px">Know the amount before you reach the booth.</h2>
<p class="lede">The first hour is {{ $money($facts['first']) }} and every started hour after that is {{ $money($facts['extra']) }}, up to {{ $money($facts['cap']) }} a day.@if ($facts['discount']) Seniors and PWD drivers get {{ rtrim(rtrim(number_format($facts['discount'], 2), '0'), '.') }}% off the parking fee.@endif</p>
<div class="alert" style="margin-top:20px">These are the current rates set by the parking manager. The booth screen always shows the exact amount.</div>
</div>
<div class="panel" id="fees-panel" data-discount="{{ $facts['discount'] ?? 0 }}">
<label class="f" for="dm">Time parked: <span id="dd"></span></label>
<input id="dm" type="range" min="10" max="900" step="5" value="125">
@if ($facts['discount'])
<label style="display:flex;gap:8px;align-items:center;margin-top:14px;font-size:14px"><input id="dsr" type="checkbox"> Senior or PWD discount, {{ rtrim(rtrim(number_format($facts['discount'], 2), '0'), '.') }}%</label>
@endif
<div id="dout" style="margin-top:14px"></div>
<p style="font-size:12.5px;color:var(--muted);margin-top:10px">Pay cash at the booth. After payment the guard approves your exit at the gate.</p>
</div>
</div></section>
