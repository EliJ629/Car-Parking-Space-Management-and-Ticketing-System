<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.incidents.index') }}">Incidents</a> / New report</p><h1>Report an incident</h1></section>
<section class="manager-form-panel"><form method="POST" action="{{ route('manager.incidents.store') }}">@csrf
  <div class="manager-form-grid">
    <div class="manager-field"><label class="f" for="category">Category</label><select id="category" name="category" required>@foreach ($categories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select>@error('category')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="slot_id">Slot (optional)</label><select id="slot_id" name="slot_id"><option value="">No slot</option>@foreach ($slots as $slot)<option value="{{ $slot->id }}" @selected((string) old('slot_id') === (string) $slot->id)>{{ $slot->code }}</option>@endforeach</select>@error('slot_id')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="plate_number">Vehicle plate (optional)</label><input id="plate_number" name="plate_number" value="{{ old('plate_number') }}" maxlength="10" placeholder="ABC 1234">@error('plate_number')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="location">Location (optional)</label><input id="location" name="location" value="{{ old('location') }}" maxlength="120">@error('location')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="session_id">Parking session ID (optional)</label><input id="session_id" name="session_id" type="number" min="1" value="{{ old('session_id') }}">@error('session_id')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field manager-field-wide"><label class="f" for="description">What happened?</label><textarea id="description" name="description" rows="5" minlength="10" maxlength="500" required>{{ old('description') }}</textarea>@error('description')<p class="err">{{ $message }}</p>@enderror</div>
  </div>
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.incidents.index') }}">Cancel</a><button class="btn sm" type="submit">Save report</button></div>
</form></section>
