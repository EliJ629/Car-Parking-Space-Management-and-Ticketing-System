{{-- Find my car, short version for the driver sign in page. Works with public/js/find-my-car.js. --}}
<div class="findbox" id="find">
  <h2>
    <span class="ic" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/></svg></span>
    Find my car
  </h2>
  <p class="lede">Parked as a walk-in? Type the ticket number from your printed ticket. No account needed.</p>
  <span class="demo-note">Demo with sample tickets</span>
  <form id="ff" autocomplete="off" novalidate>
    <label class="f" for="ft">Ticket number</label>
    <input id="ft" type="text" class="tin" placeholder="PK-0017" maxlength="7" inputmode="text" autocapitalize="characters">
    <button class="btn block" type="submit" id="fgo" style="margin-top:12px">Find my car</button>
  </form>
  <div class="chips" id="fchips">
    <button class="chip" type="button" data-t="PK-0017">PK-0017 parked</button>
    <button class="chip" type="button" data-t="PK-0018">PK-0018 paid</button>
    <button class="chip" type="button" data-t="PK-0099">PK-0099 unknown</button>
  </div>
  <div id="fr" aria-live="polite" style="margin-top:16px"></div>
</div>
