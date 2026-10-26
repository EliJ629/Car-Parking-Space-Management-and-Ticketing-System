@extends('layouts.app')

@section('title', 'Bayline Driver')

@section('body')
@php
    $cfg = \App\Support\LandingConfig::get();
    $facts = \App\Support\LandingConfig::liveFacts();
    $visible = \App\Support\LandingConfig::visibleSections();
    $labels = ['reserve' => 'Reserve', 'find' => 'Find my car', 'grace' => 'Grace period', 'fees' => 'Fees'];
    $rates = array_intersect_key($facts, array_flip(['first', 'extra', 'cap', 'lost', 'over']));
@endphp
<header class="nav"><div class="w">
<a class="brand" href="#top"><span class="logo">@include('partials.logo')</span><span><b>Bayline</b><small>Every bay, every ticket.</small></span></a>
<nav aria-label="Sections">@foreach ($visible as $key)<a href="#{{ $key === 'find' ? 'find-section' : $key }}">{{ $labels[$key] }}</a>@endforeach</nav>
<span class="sp"></span><button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>@include('partials.driver-cta', ['mode' => 'nav'])
</div></header>

<main id="top">
<section class="hero"><div class="w">
<div>
<p class="eyebrow">{{ $cfg['hero_eyebrow'] }}</p>
<h1 style="margin-top:12px">{!! \App\Support\LandingConfig::emphasise($cfg['hero_title']) !!}</h1>
<p class="lede">{{ $cfg['hero_lede'] }}</p>
<div class="cta">@include('partials.driver-cta', ['mode' => 'hero'])</div>
<p style="font-size:13px;color:var(--muted);margin-top:14px">Reservations cost nothing. You pay cash at the booth when you leave.</p>
</div>
<div style="display:flex;justify-content:center">
<div class="panel" style="width:min(330px,100%);padding:0;border-radius:26px;overflow:hidden;border:1.5px solid var(--field)" aria-label="Sample reservation, for illustration only"><span class="demo-note" style="margin:10px 0 0 20px">Sample, for illustration</span>
<div style="background:var(--band);color:var(--band-ink);padding:18px 20px 16px"><p class="eyebrow">My reservations</p><p style="font-family:var(--f-d);font-weight:800;font-size:30px;line-height:1;margin-top:6px">Fri, Oct 16 &middot; 8:00 AM</p></div>
<div class="road" aria-hidden="true"></div>
<div style="padding:18px 20px 20px">
<div style="display:flex;gap:14px;align-items:center"><div class="plate"><small>ZONE A</small><b>A-06</b></div><div><p style="font-weight:700">Toyota Vios</p><p style="font-size:13px;color:var(--muted)">DEF 2208</p><span class="badge b-pend" style="margin-top:4px;background:var(--res-bg);border-color:var(--paint-deep);color:var(--ink)">Reserved, free</span></div></div>
<div id="qr" style="margin:16px auto 8px;width:170px;height:170px;background:var(--qr-bg);padding:10px;border-radius:8px;border:1px solid var(--line)"></div>
<p style="text-align:center;font-family:var(--f-d);font-weight:700;font-size:24px;letter-spacing:.08em">RSV-4F7K29</p>
<p style="text-align:center;font-size:12.5px;color:var(--muted);margin-top:4px">Show this at the booth. Valid until {{ \Carbon\Carbon::createFromTime(8, 0)->addMinutes($facts['grace'])->format('g:i A') }}.</p>
</div></div></div>
</div></section>
<div class="road" aria-hidden="true"></div>

@foreach ($visible as $section)
@include('driver.landing.'.$section, ['facts' => $facts])
@endforeach
</main>

<section class="close"><div class="w"><div><h2>Park with a plan.</h2><p style="margin-top:8px;font-size:17px">Create a free account and reserve your first bay.</p></div><div style="display:flex;gap:12px;flex-wrap:wrap">@include('partials.driver-cta', ['mode' => 'close'])</div></div></section>
<footer><div class="w"><span><b>Bayline</b> &middot; Car Parking Space Management and Ticketing System</span><span>Boquiren &middot; Jarder &middot; Masangya &middot; Segunto &middot; Adviser: Prof. Jayson P. Joble &middot; ITP 108</span><a class="lnk-foot" href="{{ route('staff.landing') }}">Staff page</a></div></footer>

@endsection

@push('scripts')
<script>window.BAYLINE_RATES = @json($rates);</script>
<script src="{{ asset('js/driver-landing.js') }}?v={{ filemtime(public_path('js/driver-landing.js')) }}"></script>
<script src="{{ asset('js/find-my-car.js') }}?v={{ filemtime(public_path('js/find-my-car.js')) }}"></script>
@endpush
