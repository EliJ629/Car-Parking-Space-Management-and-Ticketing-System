@extends('layouts.app')

@section('title', 'Bayline Driver')

@section('body')
<header class="nav"><div class="w">
<a class="brand" href="#top"><span class="logo">@include('partials.logo')</span><span><b>Bayline</b><small>Every bay, every ticket.</small></span></a>
<nav aria-label="Sections"><a href="#reserve">Reserve</a><a href="#find">Find my car</a><a href="#grace">Grace period</a><a href="#fees">Fees</a></nav>
<span class="sp"></span><button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>@include('partials.driver-cta', ['mode' => 'nav'])
</div></header>

<main id="top">
<section class="hero"><div class="w">
<div>
<p class="eyebrow">For drivers</p>
<h1 style="margin-top:12px">Your bay is <em>waiting</em>.</h1>
<p class="lede">Reserve a bay for free, follow your parking from your phone and get your receipts in one place. Parked as a walk-in? Type the ticket number and see where the car is.</p>
<div class="cta">@include('partials.driver-cta', ['mode' => 'hero'])</div>
<p style="font-size:13px;color:var(--muted);margin-top:14px">Reservations cost nothing. You pay cash at the booth when you leave.</p>
</div>
<div style="display:flex;justify-content:center">
<div class="panel" style="width:min(330px,100%);padding:0;border-radius:26px;overflow:hidden;border:1.5px solid var(--field)" aria-label="Sample reservation">
<div style="background:var(--band);color:var(--band-ink);padding:18px 20px 16px"><p class="eyebrow">My reservations</p><p style="font-family:var(--f-d);font-weight:800;font-size:30px;line-height:1;margin-top:6px">Fri, Oct 16 &middot; 8:00 AM</p></div>
<div class="road" aria-hidden="true"></div>
<div style="padding:18px 20px 20px">
<div style="display:flex;gap:14px;align-items:center"><div class="plate"><small>ZONE A</small><b>A-06</b></div><div><p style="font-weight:700">Toyota Vios</p><p style="font-size:13px;color:var(--muted)">DEF 2208</p><span class="badge b-pend" style="margin-top:4px;background:var(--res-bg);border-color:var(--paint-deep);color:var(--ink)">Reserved, free</span></div></div>
<div id="qr" style="margin:16px auto 8px;width:170px;height:170px;background:var(--qr-bg);padding:10px;border-radius:8px;border:1px solid var(--line)"></div>
<p style="text-align:center;font-family:var(--f-d);font-weight:700;font-size:24px;letter-spacing:.08em">RSV-4F7K29</p>
<p style="text-align:center;font-size:12.5px;color:var(--muted);margin-top:4px">Show this at the booth. Valid until 8:10 AM.</p>
</div></div></div>
</div></section>
<div class="road" aria-hidden="true"></div>

<section class="sec band" id="reserve"><div class="w">
<p class="eyebrow">Reserve</p>
<h2 style="margin-top:8px">Reserve in a minute. Pay nothing now.</h2>
<p class="lede">Book up to 7 days ahead. The bay is held for you and nobody else can take it.</p>
<ol class="steps">
<li><div class="t">Step 1</div><h3>Pick a day and time</h3><p>Choose a day within the next 7 days and your arrival time.</p></li>
<li><div class="t">Step 2</div><h3>Pick a bay and a car</h3><p>The map shows free bays. Choose one of the cars saved in your account.</p></li>
<li><div class="t">Step 3</div><h3>Get your code</h3><p>You receive a reservation code and a QR code. Both are free.</p></li>
<li><div class="t">Step 4</div><h3>Show it at the booth</h3><p>The attendant scans the code, checks your car and prints the ticket.</p></li>
<li><div class="t">Step 5</div><h3>Pay when you leave</h3><p>Cash at the booth. The guard checks your car at the gate and you drive out.</p></li>
</ol>
</div></section>

<section class="sec" id="find"><div class="w two">
<div>
<p class="eyebrow">Find my car</p>
<h2 style="margin-top:8px">Forgot where you parked? Type your ticket number.</h2>
<p class="lede">No account needed. The number is printed on the front of the ticket you got at the entrance booth. Try one of the sample tickets.</p>
<div class="chips" id="fchips"><button class="chip" type="button" data-t="PK-0017">PK-0017 parked</button><button class="chip" type="button" data-t="PK-0018">PK-0018 paid</button><button class="chip" type="button" data-t="PK-0011">PK-0011 left</button><button class="chip" type="button" data-t="PK-0099">PK-0099 unknown</button></div>
<p style="font-size:13px;color:var(--muted);margin-top:16px;max-width:34em">The result shows your bay, the vehicle type, a masked plate and the fee so far. It never shows the full plate or any contact details, and it does not show which other bays are taken.</p>
</div>
<div class="panel" style="background:var(--warn-bg);border-color:var(--paint-deep);border-style:dashed;border-width:2px">
<span class="demo-note">Demo with sample tickets</span>
<form id="ff" autocomplete="off" novalidate>
<label class="f" for="ft">Ticket number</label>
<div style="display:flex;gap:10px;flex-wrap:wrap"><input id="ft" type="text" class="tin" placeholder="PK-0017" value="PK-0017" maxlength="7" inputmode="text" style="flex:1 1 160px;min-width:0"><button class="btn" type="submit" id="fgo">Find my car</button></div>
</form>
<div id="fr" aria-live="polite" style="margin-top:16px"></div>
</div>
</div></section>

<section class="sec" id="grace" style="padding-top:8px"><div class="w two">
<div>
<p class="eyebrow">Reservation grace period</p>
<h2 style="margin-top:8px">Ten minutes of grace, then the bay goes back.</h2>
<p class="lede">If you reserve for 8:00 AM you have until 8:10 AM to check in. After that the code stops working, the bay is released and you get a notification. Drag the slider to see each case.</p>
<ul style="list-style:none;padding:0;margin:20px 0 0;font-size:15px;display:grid;gap:8px">
<li class="row" style="padding:0 0 0;border:0"><span style="color:var(--ink);font-weight:700">We tell you when</span></li>
<li>Your reservation is confirmed.</li><li>Your reservation expires.</li><li>A bay is given to you as a walk-in.</li><li>Payment is recorded and the receipt is ready.</li><li>Your car has been parked too long.</li></ul>
</div>
<div class="panel">
<label class="f" for="gs">You arrive at <b id="gt" style="font-family:var(--f-d);font-size:28px;font-weight:800;vertical-align:-3px;margin-left:6px"></b></label>
<input id="gs" type="range" min="0" max="20" step="1" value="6">
<div style="position:relative;height:22px;margin-top:10px;border-radius:4px;overflow:hidden;background:repeating-linear-gradient(135deg,var(--out-bg) 0 6px,var(--line) 6px 8px)" aria-hidden="true"><div style="position:absolute;left:0;top:0;bottom:0;width:50%;background:var(--paint)"></div><div id="gm" style="position:absolute;top:0;bottom:0;width:4px;background:var(--ink);transform:translateX(-2px)"></div></div>
<div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-top:6px"><span>8:00 AM</span><span>8:10 AM grace ends</span><span>8:20 AM</span></div>
<div id="gr" aria-live="polite" style="margin-top:16px"></div>
</div>
</div></section>

<section class="sec" id="fees" style="padding-top:8px"><div class="w two">
<div>
<p class="eyebrow">Fees</p>
<h2 style="margin-top:8px">Know the amount before you reach the booth.</h2>
<p class="lede">The first hour is &#8369;30 and every started hour after that is &#8369;20, up to &#8369;300 a day. Seniors and PWD drivers get 20% off the parking fee.</p>
<div class="alert" style="margin-top:20px">These are the current sample rates. The parking manager can change them, and the booth screen always shows the exact amount.</div>
</div>
<div class="panel">
<label class="f" for="dm">Time parked: <span id="dd"></span></label>
<input id="dm" type="range" min="10" max="900" step="5" value="125">
<label style="display:flex;gap:8px;align-items:center;margin-top:14px;font-size:14px"><input id="dsr" type="checkbox"> Senior or PWD discount, 20%</label>
<div id="dout" style="margin-top:14px"></div>
<p style="font-size:12.5px;color:var(--muted);margin-top:10px">Pay cash at the booth. After payment the guard approves your exit at the gate.</p>
</div>
</div></section>
</main>

<section class="close"><div class="w"><div><h2>Park with a plan.</h2><p style="margin-top:8px;font-size:17px">Create a free account and reserve your first bay.</p></div><div style="display:flex;gap:12px;flex-wrap:wrap">@include('partials.driver-cta', ['mode' => 'close'])</div></div></section>
<footer><div class="w"><span><b>Bayline</b> &middot; Car Parking Space Management and Ticketing System</span><span>Boquiren &middot; Jarder &middot; Masangya &middot; Segunto &middot; Adviser: Prof. Jayson P. Joble &middot; ITP 108</span><a class="lnk-foot" href="{{ route('staff.landing') }}">Staff page</a></div></footer>

@endsection

@push('scripts')
<script src="{{ asset('js/driver-landing.js') }}?v={{ filemtime(public_path('js/driver-landing.js')) }}"></script>
<script src="{{ asset('js/find-my-car.js') }}?v={{ filemtime(public_path('js/find-my-car.js')) }}"></script>
@endpush
