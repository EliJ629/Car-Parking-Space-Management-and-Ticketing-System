<div class="manager-form-grid" data-slot-floor-form data-max-zones-per-floor="{{ $maxZonesPerFloor }}" data-selected-zone="{{ old('zone', $slot->zone ?? '') }}">
  <script type="application/json" data-floor-map>@json($floorZoneMap)</script>
  @if (isset($slot))
    <div class="manager-field manager-field-wide">
      <label class="f" for="slot-code">Slot code</label>
      <input id="slot-code" type="text" value="{{ $slot->code }}" readonly aria-describedby="slot-code-help">
      <p class="hint" id="slot-code-help">Slot codes are permanent so future parking records can keep a stable reference.</p>
    </div>
  @else
    <div class="manager-field manager-field-wide">
      <label class="f" for="slot-code">Slot code</label>
      <input id="slot-code" name="code" type="text" value="{{ old('code') }}" required maxlength="10" pattern="[A-Za-z]-[0-9]{2}" placeholder="A-09" aria-describedby="slot-code-help" @if ($errors->has('code')) aria-invalid="true" @endif>
      <p class="hint" id="slot-code-help">Use a zone letter and two digits. Each code must be unique.</p>
      @error('code')<p class="err">{{ $message }}</p>@enderror
    </div>
  @endif

  <div class="manager-field">
    <label class="f" for="slot-floor">Floor</label>
    <select id="slot-floor" name="floor" required @if ($errors->has('floor')) aria-invalid="true" @endif>
      @foreach ($floors as $floor)
        <option value="{{ $floor }}" @selected((int) old('floor', $selectedFloor) === (int) $floor)>Floor {{ $floor }}</option>
      @endforeach
    </select>
    @php($selectedFloorCount = $floorZoneMap[(int) old('floor', $selectedFloor)]['count'] ?? count($activeZonesOnFloor))
    <p class="hint" data-zone-count>Zones: {{ $selectedFloorCount }} / {{ $maxZonesPerFloor }}</p>
    @error('floor')<p class="err">{{ $message }}</p>@enderror
  </div>

  <div class="manager-field">
    <label class="f" for="slot-zone">Zone</label>
    <select id="slot-zone" name="zone" required @if ($errors->has('zone')) aria-invalid="true" @endif>
      @foreach ($zones as $zone)
        @php($isActive = in_array($zone, $activeZonesOnFloor, true) || $zone === old('zone', $slot->zone ?? null))
        <option value="{{ $zone }}" @selected(old('zone', $slot->zone ?? ($zones[0] ?? '')) === $zone) @disabled($selectedFloorCount >= $maxZonesPerFloor && ! $isActive)>{{ $zone }}</option>
      @endforeach
    </select>
    <div class="manager-add-zone-row">
      <button class="btn ghost sm" type="button" data-add-zone @disabled($selectedFloorCount >= $maxZonesPerFloor)>Add zone</button>
    </div>
    <p class="hint">A floor can only contain 4 zones. Up to {{ \App\Support\Lot::maxSlotsPerZone() }} slots per zone.</p>
    <p class="err" data-floor-limit @if ($selectedFloorCount < $maxZonesPerFloor) hidden @endif>This floor already has 4 zones. Choose an existing zone or select another floor.</p>
    @error('zone')<p class="err">{{ $message }}</p>@enderror
  </div>

  <div class="manager-field">
    <label class="f" for="slot-type">Slot type</label>
    <select id="slot-type" name="type" required @if ($errors->has('type')) aria-invalid="true" @endif>
      @foreach ($types as $type)
        <option value="{{ $type }}" @selected(old('type', $slot->type ?? 'Standard') === $type)>{{ $type }}</option>
      @endforeach
    </select>
    @error('type')<p class="err">{{ $message }}</p>@enderror
  </div>

  <div class="manager-field manager-field-wide">
    <label class="f" for="slot-notes">Notes <span class="manager-muted">(optional)</span></label>
    <textarea id="slot-notes" name="notes" rows="3" maxlength="1000" @if ($errors->has('notes')) aria-invalid="true" @endif>{{ old('notes', $slot->notes ?? '') }}</textarea>
    @error('notes')<p class="err">{{ $message }}</p>@enderror
  </div>
</div>
