@php
    $g = (int) $facts['grace'];
@endphp
<section class="sec" id="grace" style="padding-top:8px"><div class="w two">
<div>
<p class="eyebrow">Reservation grace period</p>
<h2 style="margin-top:8px">{{ $g > 0 ? $g.' '.\Illuminate\Support\Str::plural('minute', $g).' of grace, then the bay goes back.' : 'No grace period: arrive on time.' }}</h2>
<p class="lede">{{ $g > 0 ? "If you reserve for 8:00 AM you have until ".\Carbon\Carbon::createFromTime(8, 0)->addMinutes($g)->format('g:i A')." to check in. After that the code stops working, the bay is released and you get a notification. Drag the slider to see each case." : 'If you reserve for 8:00 AM the code works at that time only. After that the code stops working, the bay is released and you get a notification. Drag the slider to see each case.' }}</p>
<ul style="list-style:none;padding:0;margin:20px 0 0;font-size:15px;display:grid;gap:8px">
<li class="row" style="padding:0 0 0;border:0"><span style="color:var(--ink);font-weight:700">We tell you when</span></li>
<li>Your reservation is confirmed.</li><li>Your reservation expires.</li><li>A bay is given to you as a walk-in.</li><li>Payment is recorded and the receipt is ready.</li><li>Your car has been parked too long.</li></ul>
</div>
<div class="panel" id="grace-panel" data-grace="{{ $g }}">
<label class="f" for="gs">You arrive at <b id="gt" style="font-family:var(--f-d);font-size:28px;font-weight:800;vertical-align:-3px;margin-left:6px"></b></label>
<input id="gs" type="range" min="0" max="20" step="1" value="0">
<div style="position:relative;height:22px;margin-top:10px;border-radius:4px;overflow:hidden;background:repeating-linear-gradient(135deg,var(--out-bg) 0 6px,var(--line) 6px 8px)" aria-hidden="true"><div id="gb" style="position:absolute;left:0;top:0;bottom:0;width:50%;background:var(--paint)"></div><div id="gm" style="position:absolute;top:0;bottom:0;width:4px;background:var(--ink);transform:translateX(-2px)"></div></div>
<div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-top:6px"><span id="gl0">8:00 AM</span><span id="gl1"></span><span id="gl2"></span></div>
<div id="gr" aria-live="polite" style="margin-top:16px"></div>
</div>
</div></section>
