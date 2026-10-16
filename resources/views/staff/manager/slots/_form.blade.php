<div class="manager-form-grid">
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
    <label class="f" for="slot-zone">Zone</label>
    <select id="slot-zone" name="zone" required @if ($errors->has('zone')) aria-invalid="true" @endif>
      @foreach ($zones as $zone)
        <option value="{{ $zone }}" @selected(old('zone', $slot->zone ?? 'Zone A') === $zone)>{{ $zone }}</option>
      @endforeach
    </select>
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
