{{-- Find my car. One component for the driver sign in page and the landing page, so the Manager's layout choice shows in both.
     It works with public/js/find-my-car.js and asks the server (route find-my-car); there is no sample data. --}}
@php
    $lookup = \App\Support\LandingConfig::lookup();
@endphp
<div class="findbox findbox-{{ $lookup['layout'] }}" id="find" data-find-url="{{ route('find-my-car') }}">
  <h2>
    <span class="ic" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/></svg></span>
    {{ $lookup['heading'] }}
  </h2>
  @if ($lookup['layout'] !== 'compact')
    <p class="lede">{{ $lookup['intro'] }}</p>
  @endif
  <form id="ff" autocomplete="off" novalidate>
    <label class="f" for="ft">Ticket number</label>
    <div class="findrow">
      <input id="ft" type="text" class="tin" data-ticket data-plate-msg="ft-msg" placeholder="PK-0017" maxlength="8" inputmode="text" autocapitalize="characters" aria-describedby="ft-msg">
      <button class="btn" type="submit" id="fgo">Find my car</button>
    </div>
    <p class="plate-msg" id="ft-msg" hidden></p>
  </form>
  <div id="fr" aria-live="polite" style="margin-top:16px"></div>
</div>
