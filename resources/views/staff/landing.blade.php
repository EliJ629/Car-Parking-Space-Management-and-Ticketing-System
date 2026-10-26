@extends('layouts.app')

@section('title', 'Bayline Staff')

@section('body')
<header class="nav"><div class="w">
<a class="brand" href="#top"><span class="logo">@include('partials.logo')</span><span><b>Bayline</b><small>Every bay, every ticket.</small></span></a>
<nav aria-label="Sections"><a href="#roles">Your workspace</a><a href="#fees">Fee breakdown</a><a href="#exit">Exit rule</a></nav>
<span class="sp"></span><button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>@include('partials.staff-cta', ['cls' => 'btn sm'])
</div></header>

<main id="top">
<section class="hero"><div class="w">
<div>
<p class="eyebrow">For the parking team</p>
<h1 style="margin-top:12px">The booth, the gate and the office see the <em>same lot</em>.</h1>
<p class="lede">Bayline keeps slots, tickets, cash payments and exits in one live record. Nobody works from a guess, and a car leaves only when the guard says so.</p>
<div class="cta">@include('partials.staff-cta', ['cls' => 'btn'])<a class="btn ghost" href="#roles">See your workspace</a></div>
</div>
<div class="panel" id="xw" aria-labelledby="xwt">
<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
<div><p class="eyebrow" id="xwt">Try it: one exit, start to finish</p><p style="margin-top:6px;font-weight:700">PK-0018 &middot; White van BNM 6628 &middot; Slot A-07</p></div>
<span class="badge b-done" id="xbadge">Occupied</span></div>
<div class="lot" id="xlot" style="margin-top:14px"></div>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
<button class="btn sm" id="xb1" type="button">1. Booth records cash</button>
<button class="btn sm" id="xb2" type="button" disabled>2. Gate taps Good to Go</button>
<button class="btn sm ghost" id="xb3" type="button">Start over</button></div>
<div id="xst" aria-live="polite" style="margin-top:8px"></div>
</div>
</div></section>
<div class="road" aria-hidden="true"></div>

<section class="sec" id="roles"><div class="w">
<p class="eyebrow">Your workspace</p>
<h2 style="margin-top:8px">Pick your post. See your first screen.</h2>
<p class="lede">Every account opens on the screen built for that job, so nobody scrolls past tools they do not use.</p>
<div role="tablist" aria-label="Posts" id="tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:26px"></div>
<div id="tabpanel" role="tabpanel" class="two" style="margin-top:22px"></div>
</div></section>

<section class="sec" id="fees" style="padding-top:8px"><div class="w two">
<div>
<p class="eyebrow">Exit and payment</p>
<h2 style="margin-top:8px">Every peso, itemised before the driver pays.</h2>
<p class="lede">The exit screen shows the base fee, extra hours, discounts and penalties as separate lines. Move the sliders to see the same breakdown the booth sees.</p>
<div class="alert" style="margin-top:20px">Rates, discounts and penalties come from the Parking Manager's settings. The numbers here are the current sample rates and can change.</div>
</div>
<div class="panel">
<label class="f" for="fmin">Time parked: <span id="fdur"></span></label>
<input id="fmin" type="range" min="10" max="900" step="5" value="125">
<div style="display:grid;gap:14px;margin-top:16px">
<div><label class="f" for="fdisc">Discount</label><select id="fdisc"><option value="0">None</option><option value="0.2">Senior or PWD, 20%</option><option value="0.5">Tenant validation, 50%</option></select></div>
<div style="display:flex;gap:20px;flex-wrap:wrap;font-size:14px">
<label style="display:flex;gap:8px;align-items:center"><input id="flost" type="checkbox"> Lost ticket (&#8369;100)</label>
<label style="display:flex;gap:8px;align-items:center"><input id="fover" type="checkbox"> Overstay (&#8369;150)</label></div></div>
<div id="fout" style="margin-top:14px"></div>
<p class="small" style="font-size:12.5px;color:var(--muted);margin-top:10px">Payment is cash only. A receipt prints after payment.</p>
</div>
</div></section>

<section class="sec band" id="exit"><div class="w">
<p class="eyebrow">The exit rule</p>
<h2 style="margin-top:8px">A car leaves only when the guard says so.</h2>
<p class="lede">Payment alone does not free a bay. The slot stays held until the gate check is done, so two cars never get one bay.</p>
<ol class="steps">
<li><div class="t">Booth</div><h3>Find the ticket</h3><p>Search by ticket number or plate. The session, entry time and slot appear.</p></li>
<li><div class="t">Booth</div><h3>Take cash</h3><p>The fee breaks down line by line. Payment is recorded and the receipt prints.</p></li>
<li><div class="t">System</div><h3>Pending exit</h3><p>The slot turns solid yellow and stays unavailable. The request reaches the gate screen.</p></li>
<li><div class="t">Gate</div><h3>Check the car</h3><p>The guard compares plate, colour and type with the screen.</p></li>
<li><div class="t">Gate</div><h3>Good to Go</h3><p>The session closes and the slot goes back to Available.</p></li>
</ol>
</div></section>

<section class="sec"><div class="w">
<p class="eyebrow">Built into every shift</p>
<h2 style="margin-top:8px;margin-bottom:26px">Four rules the system enforces for you.</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:28px 36px">
<div style="border-top:3px solid var(--ink);padding-top:12px"><h3 style="font-size:28px">Cash only</h3><p style="color:var(--muted);margin-top:6px;font-size:15px">No online payment. Reservations are free and the fee is paid at the booth on exit.</p></div>
<div style="border-top:3px solid var(--ink);padding-top:12px"><h3 style="font-size:28px">10-minute grace</h3><p style="color:var(--muted);margin-top:6px;font-size:15px">A reservation for 2:00 PM is valid until 2:10 PM. After that the code stops working and the bay is released.</p></div>
<div style="border-top:3px solid var(--ink);padding-top:12px"><h3 style="font-size:28px">12-hour alert</h3><p style="color:var(--muted);margin-top:6px;font-size:15px">A car parked past the limit raises one alert, and the guard can also file a report by hand.</p></div>
<div style="border-top:3px solid var(--ink);padding-top:12px"><h3 style="font-size:28px">Everything logged</h3><p style="color:var(--muted);margin-top:6px;font-size:15px">Entries, payments, discounts, manual slot changes and sign ins are saved with the user and the time.</p></div>
</div></div></section>
</main>

<section class="close"><div class="w"><div><h2>Ready for your shift?</h2><p style="margin-top:8px;font-size:17px">Sign in with the account you were given.</p></div>@include('partials.staff-cta', ['cls' => 'btn'])</div></section>
<footer><div class="w"><span><b>Bayline</b> &middot; Car Parking Space Management and Ticketing System</span><span>Boquiren &middot; Jarder &middot; Masangya &middot; Segunto &middot; Adviser: Prof. Jayson P. Joble &middot; ITP 108</span><a class="lnk-foot" href="{{ route('driver.landing') }}">Driver page</a></div></footer>

@endsection

@push('scripts')
<script src="{{ asset('js/staff-landing.js') }}?v={{ filemtime(public_path('js/staff-landing.js')) }}"></script>
@endpush
