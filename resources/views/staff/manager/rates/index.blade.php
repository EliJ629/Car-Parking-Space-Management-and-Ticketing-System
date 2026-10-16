<section class="manager-page-heading">
  <p class="eyebrow">Manager controls</p>
  <div class="manager-heading-row">
    <div><h1>Rates and policies</h1><p class="manager-page-lede">Money rules are stored as effective-dated records. Each future fee calculation can use the rate that applied at exit.</p></div>
  </div>
</section>

<section class="manager-panel">
  <div class="manager-panel-heading"><div><p class="eyebrow">Current parking rates</p><h2>Fees and penalties</h2></div><span class="badge b-ok">{{ $currentRate ? 'Effective '.$currentRate->effective_at->format('M j, Y') : 'No rates set' }}</span></div>
  @if ($currentRate)
    <div class="manager-data-grid">
      <div><span>First hour</span><strong>₱{{ number_format((float) $currentRate->first_hour_fee, 2) }}</strong></div>
      <div><span>Each additional started hour</span><strong>₱{{ number_format((float) $currentRate->additional_hour_fee, 2) }}</strong></div>
      <div><span>Daily maximum</span><strong>₱{{ number_format((float) $currentRate->daily_maximum, 2) }}</strong></div>
      <div><span>Lost ticket penalty</span><strong>₱{{ number_format((float) $currentRate->lost_ticket_penalty, 2) }}</strong></div>
      <div><span>Overstay penalty</span><strong>₱{{ number_format((float) $currentRate->overstay_penalty, 2) }}</strong></div>
      <div><span>Billing rule</span><strong>Started hours, rounded up</strong></div>
    </div>
    <p class="hint">Reason for current rates: {{ $currentRate->reason }}</p>
  @else
    <p class="manager-empty">No rates are configured yet. Add the initial rate set below.</p>
  @endif
  @if ($upcomingRates->isNotEmpty())
    <h3 class="manager-subheading">Scheduled rate changes</h3>
    @foreach ($upcomingRates as $rate)
      <div class="manager-summary-row"><span>{{ $rate->effective_at->format('M j, Y g:i A') }} &mdash; {{ $rate->reason }}</span><strong>₱{{ number_format((float) $rate->first_hour_fee, 2) }} first hour / ₱{{ number_format((float) $rate->additional_hour_fee, 2) }} additional</strong></div>
    @endforeach
  @endif
</section>

<section class="manager-form-panel manager-section-gap">
  <p class="eyebrow">Add an effective-dated rate set</p>
  <h2 class="manager-form-title">Schedule rate change</h2>
  <form method="POST" action="{{ route('manager.rates.store') }}">
    @csrf
    <div class="manager-form-grid">
      @foreach ([
        'first_hour_fee' => 'First hour (₱)',
        'additional_hour_fee' => 'Each additional started hour (₱)',
        'daily_maximum' => 'Daily maximum (₱)',
        'lost_ticket_penalty' => 'Lost ticket penalty (₱)',
        'overstay_penalty' => 'Overstay penalty (₱)',
      ] as $field => $label)
        <div class="manager-field"><label class="f" for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="1000000" step="0.01" value="{{ old($field, $currentRate?->$field ?? 0) }}" required></div>
      @endforeach
      <div class="manager-field"><label class="f" for="effective_at">Effective from</label><input id="effective_at" name="effective_at" type="datetime-local" value="{{ old('effective_at', now()->addDay()->startOfDay()->format('Y-m-d\TH:i')) }}" required></div>
      <div class="manager-field manager-field-wide"><label class="f" for="reason">Reason for change</label><input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}" required></div>
    </div>
    @foreach (['first_hour_fee','additional_hour_fee','daily_maximum','lost_ticket_penalty','overstay_penalty','effective_at','reason'] as $field)
      @error($field)<p class="err">{{ $message }}</p>@enderror
    @endforeach
    <div class="manager-form-actions"><button class="btn sm" type="submit">Save rate set</button></div>
  </form>
</section>

<section class="manager-panel manager-section-gap">
  <div class="manager-panel-heading"><div><p class="eyebrow">Exit discounts</p><h2>Discounts</h2></div></div>
  <div class="manager-table-wrap">
    <table class="manager-table">
      <thead><tr><th>Name</th><th>Value</th><th>Required proof</th><th>Manager PIN</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      @forelse ($discounts as $discount)
        <tr>
          <td><strong>{{ $discount->name }}</strong></td>
          <td>{{ $discount->percent }}%</td>
          <td>{{ $discount->proof_label }}</td>
          <td>{{ $discount->requires_manager_pin ? 'Required' : 'No' }}</td>
          <td>{{ $discount->active ? 'Active' : 'Inactive' }}</td>
          <td>
            <details class="manager-inline-edit"><summary>Edit</summary>
              <form method="POST" action="{{ route('manager.discounts.update', $discount) }}" class="manager-inline-form">
                @csrf @method('PATCH')
                <label class="f">Name<input name="name" maxlength="80" value="{{ $discount->name }}" required></label>
                <label class="f">Percent<input name="percent" type="number" min="1" max="100" value="{{ $discount->percent }}" required></label>
                <label class="f">Proof<input name="proof_label" maxlength="80" value="{{ $discount->proof_label }}" required></label>
                <label class="ck"><input type="checkbox" name="requires_manager_pin" value="1" @checked($discount->requires_manager_pin)>Manager PIN required</label>
                <label class="ck"><input type="checkbox" name="active" value="1" @checked($discount->active)>Active</label>
                <button class="btn sm" type="submit">Save</button>
              </form>
            </details>
            <form method="POST" action="{{ route('manager.discounts.destroy', $discount) }}" class="manager-inline-delete" onsubmit="return confirm('Remove this discount?')">
              @csrf @method('DELETE')<button type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td class="manager-table-empty" colspan="6">No discounts have been added.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>

<section class="manager-form-panel manager-section-gap">
  <p class="eyebrow">Add discount</p><h2 class="manager-form-title">New exit discount</h2>
  <form method="POST" action="{{ route('manager.discounts.store') }}">
    @csrf
    <div class="manager-form-grid">
      <div class="manager-field"><label class="f" for="discount-name">Name</label><input id="discount-name" name="name" maxlength="80" value="{{ old('name') }}" required></div>
      <div class="manager-field"><label class="f" for="discount-percent">Percent</label><input id="discount-percent" name="percent" type="number" min="1" max="100" value="{{ old('percent') }}" required></div>
      <div class="manager-field"><label class="f" for="proof-label">Proof required</label><input id="proof-label" name="proof_label" maxlength="80" value="{{ old('proof_label') }}" required></div>
      <div class="manager-field"><label class="ck"><input type="checkbox" name="requires_manager_pin" value="1">Require manager PIN</label></div>
    </div>
    <div class="manager-form-actions"><button class="btn sm" type="submit">Add discount</button></div>
  </form>
</section>

<section class="manager-panel manager-section-gap">
  <p class="eyebrow">Reservation and exit rules</p><h2>Current operational settings</h2>
  <div class="manager-data-grid">
    <div><span>Reservation fee</span><strong>Free</strong></div>
    <div><span>Reservation grace period</span><strong>{{ $settings['reservation_grace_minutes'] ?? 10 }} minutes</strong></div>
    <div><span>Early check-in</span><strong>{{ $settings['early_check_in_minutes'] ?? 30 }} minutes</strong></div>
    <div><span>Advance booking</span><strong>{{ $settings['maximum_advance_booking_days'] ?? 7 }} days</strong></div>
    <div><span>Exit window after payment</span><strong>{{ $settings['exit_window_minutes'] ?? 15 }} minutes</strong></div>
    <div><span>Long-parking threshold</span><strong>{{ $settings['long_parking_threshold_hours'] ?? 12 }} hours</strong></div>
  </div>
  <a class="manager-text-link" href="{{ route('manager.settings.index') }}">Edit operational rules</a>
</section>

<section class="manager-panel manager-section-gap">
  <p class="eyebrow">Rate change history</p><h2>Recent updates</h2>
  @forelse ($history as $entry)
    <div class="manager-summary-row"><span>{{ $entry->created_at->format('M j, Y g:i A') }} &mdash; {{ $entry->subject }}</span><strong>{{ $entry->user?->name ?? 'System' }}</strong></div>
  @empty
    <p class="manager-muted">No rate changes have been recorded yet.</p>
  @endforelse
  <a class="manager-text-link" href="{{ route('manager.activity.index', ['action' => 'rate_set_created']) }}">View full change log</a>
</section>
