<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.incidents.index') }}">Incidents</a> / New report</p><h1>Report an incident</h1><p class="manager-page-lede">Long-parking incidents are made by Bayline automatically, so they are not on this list. Only a vehicle that is parked in the lot now can be reported.</p></section>
<section class="manager-form-panel"><form method="POST" action="{{ route('manager.incidents.store') }}" data-mgr-incident data-rules='@json($requirements)' novalidate>@csrf
  <div class="manager-form-grid">
    <div class="manager-field"><label class="f" for="category">Category</label><select id="category" name="category" required><option value="">Choose a category</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select>@error('category')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field" id="mgr-other" hidden><label class="f" for="other_label">What kind of incident is it? (required)</label><input id="other_label" name="other_label" maxlength="80" value="{{ old('other_label') }}" placeholder="For example Blocked exit lane" autocomplete="off">@error('other_label')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="plate_number">Vehicle plate <span id="mgr-plate-need">(optional)</span></label><input id="plate_number" name="plate_number" data-plate data-plate-optional data-plate-msg="mgr-plate-msg" value="{{ old('plate_number') }}" maxlength="20" placeholder="ABC 1234" autocapitalize="characters" autocomplete="off" spellcheck="false">@error('plate_number')<p class="err">{{ $message }}</p>@enderror<p class="err" id="mgr-plate-msg" hidden></p></div>
    <div class="manager-field" id="mgr-responsible" hidden><label class="f" for="responsible_plate">Responsible vehicle plate (required)</label><input id="responsible_plate" name="responsible_plate" data-plate data-plate-optional data-plate-msg="mgr-resp-msg" value="{{ old('responsible_plate') }}" maxlength="20" placeholder="The vehicle that caused the damage" autocapitalize="characters" autocomplete="off" spellcheck="false">@error('responsible_plate')<p class="err">{{ $message }}</p>@enderror<p class="err" id="mgr-resp-msg" hidden></p><label class="manager-check"><input type="checkbox" id="responsible_unknown" name="responsible_unknown" value="1" @checked(old('responsible_unknown'))> Responsible vehicle unknown</label><p class="hint">The report counts against this vehicle, not the damaged one.</p></div>
    <div class="manager-field"><label class="f" for="location">Slot <span id="mgr-place-need">(optional)</span></label><select id="location" name="location" @disabled($occupied->isEmpty())><option value="">{{ $occupied->isEmpty() ? 'No vehicle is parked right now' : 'Choose an occupied slot' }}</option>@foreach ($occupied as $row)<option value="{{ $row['code'] }}" data-plate="{{ $row['plate'] }}" @selected(old('location') === $row['code'])>{{ $row['code'] }} - {{ $row['plate_label'] }}</option>@endforeach</select>@error('location')<p class="err">{{ $message }}</p>@enderror<p class="hint">Only slots with a vehicle in them are listed.</p></div>
    <div class="manager-field" id="mgr-place-text" hidden><label class="f" for="place_text">Other place in the lot (optional)</label><input id="place_text" name="place_text" maxlength="120" value="{{ old('place_text') }}" placeholder="For example Zone A lane">@error('place_text')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="session_id">Parking session ID (optional)</label><input id="session_id" name="session_id" type="number" min="1" value="{{ old('session_id') }}">@error('session_id')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field manager-field-wide"><label class="f" for="description">What happened?</label><textarea id="description" name="description" rows="5" minlength="10" maxlength="500" required>{{ old('description') }}</textarea>@error('description')<p class="err">{{ $message }}</p>@enderror</div>
  </div>
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.incidents.index') }}">Cancel</a><button class="btn sm" type="submit">Save report</button></div>
</form></section>
<script>
(function () {
  var f = document.querySelector('[data-mgr-incident]'); if (!f) return;
  var rules = JSON.parse(f.getAttribute('data-rules') || '{}');
  var cat = f.querySelector('#category'), plate = f.querySelector('#plate_number'), loc = f.querySelector('#location');
  var show = function (id, on) { var el = f.querySelector(id); if (el) el.hidden = !on; };
  function sync() {
    var r = rules[cat.value] || { plate: 'optional', place: 'optional' };
    show('#mgr-other', r.label === 'required');
    show('#mgr-responsible', r.responsible === 'required');
    show('#mgr-place-text', r.place === 'location' || r.place === 'optional');
    f.querySelector('#mgr-plate-need').textContent = r.plate === 'required' ? '(required)' : '(optional)';
    f.querySelector('#mgr-place-need').textContent = r.place === 'slot' ? '(required)' : (r.place === 'auto' ? '(filled from the vehicle)' : '(optional)');
    loc.disabled = r.place === 'auto' || loc.options.length < 2;
    if (r.place === 'auto') { loc.value = ''; }
  }
  // Choosing a slot fills in the plate parked there, so the two can never disagree.
  loc.addEventListener('change', function () {
    var o = loc.options[loc.selectedIndex];
    if (o && o.getAttribute('data-plate') && !plate.value) { plate.value = o.getAttribute('data-plate'); plate.dispatchEvent(new Event('input', { bubbles: true })); }
  });
  cat.addEventListener('change', sync); sync();
})();
</script>
