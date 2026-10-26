<section class="manager-page-heading"><p class="eyebrow">System configuration</p><h1>Settings</h1><p class="manager-page-lede">Change security, parking, notification, and receipt settings without editing code. Changes are logged.</p></section>
<section class="manager-form-panel"><form method="POST" action="{{ route('manager.settings.update') }}">@csrf @method('PUT')
  <h2 class="manager-form-title">Security</h2>
  <div class="manager-form-grid">
    @foreach ($fields as $key => $field)
      @if ($field['group'] === 'security')
        <div class="manager-field"><label class="f" for="setting-{{ $key }}">{{ $field['label'] }}</label><input id="setting-{{ $key }}" name="settings[{{ $key }}]" type="number" min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1" inputmode="numeric" data-setting-number value="{{ old("settings.{$key}", $settings[$key] ?? '') }}" required><p class="err" data-setting-error hidden></p>@error("settings.{$key}")<p class="err">{{ $message }}</p>@enderror</div>
      @endif
    @endforeach
  </div>
  <h2 class="manager-subheading">Parking and reservations</h2>
  <p class="hint" style="margin:0 0 10px">Zone limits apply when a manager adds a slot or moves a slot into another zone.</p>
  <div class="manager-form-grid">
    @foreach ($fields as $key => $field)
      @if ($field['group'] === 'parking')
        <div class="manager-field"><label class="f" for="setting-{{ $key }}">{{ $field['label'] }}</label><input id="setting-{{ $key }}" name="settings[{{ $key }}]" type="number" min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1" inputmode="numeric" data-setting-number value="{{ old("settings.{$key}", $settings[$key] ?? '') }}" required><p class="err" data-setting-error hidden></p>@error("settings.{$key}")<p class="err">{{ $message }}</p>@enderror</div>
      @endif
    @endforeach
  </div>
  <h2 class="manager-subheading">Notifications to drivers</h2>
  <div class="manager-form-grid">
    @foreach ($notifications as $key)
      <div class="manager-field"><label class="ck"><input type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old("settings.{$key}", $settings[$key] ?? true))>{{ str_replace('_', ' ', ucfirst(str_replace('notify_', '', $key))) }}</label></div>
    @endforeach
    <div class="manager-field"><label class="f" for="setting-reservation_reminder_minutes">{{ $fields['reservation_reminder_minutes']['label'] }}</label><input id="setting-reservation_reminder_minutes" name="settings[reservation_reminder_minutes]" type="number" min="{{ $fields['reservation_reminder_minutes']['min'] }}" max="{{ $fields['reservation_reminder_minutes']['max'] }}" step="1" inputmode="numeric" data-setting-number value="{{ old('settings.reservation_reminder_minutes', $settings['reservation_reminder_minutes'] ?? '') }}" required><p class="err" data-setting-error hidden></p>@error('settings.reservation_reminder_minutes')<p class="err">{{ $message }}</p>@enderror</div>
  </div>
  <h2 class="manager-subheading">Lot details shown on receipts</h2>
  <div class="manager-form-grid">
    @foreach ($fields as $key => $field)
      @if ($field['group'] === 'receipt')
        <div class="manager-field manager-field-wide"><label class="f" for="setting-{{ $key }}">{{ $field['label'] }}</label>@if ($key === 'receipt_footer')<textarea id="setting-{{ $key }}" name="settings[{{ $key }}]" maxlength="{{ $field['max_length'] }}" rows="3" required>{{ old("settings.{$key}", $settings[$key] ?? '') }}</textarea>@else<input id="setting-{{ $key }}" name="settings[{{ $key }}]" maxlength="{{ $field['max_length'] }}" value="{{ old("settings.{$key}", $settings[$key] ?? '') }}" required>@endif @error("settings.{$key}")<p class="err">{{ $message }}</p>@enderror</div>
      @endif
    @endforeach
  </div>
  <div class="manager-form-actions"><button class="btn sm" type="submit">Save settings</button></div>
</form></section>
