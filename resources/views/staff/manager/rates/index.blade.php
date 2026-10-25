<section class="manager-page-heading">
  <p class="eyebrow">Manager controls</p>
  <div class="manager-heading-row">
    <div><h1>Rates and policies</h1><p class="manager-page-lede">Money rules are stored as effective-dated records. Each future fee calculation can use the rate that applied at exit.</p></div>
  </div>
</section>

<section class="manager-panel">
  <div class="manager-panel-heading"><div><p class="eyebrow">Current parking rates</p><h2>Fees and penalties</h2></div><span class="badge b-ok">{{ $currentRate ? 'Effective '.\App\Support\Lot::local($currentRate->effective_at)?->format('M j, Y') : 'No rates set' }}</span></div>
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
    @if ($currentRate->ends_at)
      <p class="hint">Temporary rate ends {{ \App\Support\Lot::local($currentRate->ends_at)?->format('M j, Y g:i A') }}. After that, the previous normal rate applies again.</p>
    @endif
  @else
    <p class="manager-empty">No rates are configured yet. Add the initial rate set below.</p>
  @endif
  @if ($upcomingRates->isNotEmpty())
    <h3 class="manager-subheading">Scheduled rate changes</h3>
    @foreach ($upcomingRates as $rate)
      <div class="manager-summary-row">
        <span>{{ \App\Support\Lot::local($rate->effective_at)?->format('M j, Y g:i A') }}@if ($rate->ends_at) to {{ \App\Support\Lot::local($rate->ends_at)?->format('M j, Y g:i A') }}@endif &mdash; {{ $rate->reason }}</span>
        <span>
          <strong>₱{{ number_format((float) $rate->first_hour_fee, 2) }} first hour / ₱{{ number_format((float) $rate->additional_hour_fee, 2) }} additional</strong>
          @if ($rate->canBeCanceled())
            <form method="POST" action="{{ route('manager.rates.destroy', $rate) }}" class="manager-inline-delete" onsubmit="return confirm('Cancel this scheduled rate change? The normal rate will not be changed.')">
              @csrf @method('DELETE')<button type="submit">Cancel</button>
            </form>
          @endif
        </span>
      </div>
    @endforeach
  @endif
  @if ($temporaryRates->isNotEmpty())
    <h3 class="manager-subheading">Temporary rates</h3>
    @foreach ($temporaryRates as $rate)
      <div class="manager-summary-row">
        <span>{{ \App\Support\Lot::local($rate->effective_at)?->format('M j, Y g:i A') }} to {{ \App\Support\Lot::local($rate->ends_at)?->format('M j, Y g:i A') }} &mdash; {{ $rate->reason }}</span>
        <span>
          <strong>{{ $rate->effective_at->isFuture() ? 'Scheduled' : 'Active' }}</strong>
          <form method="POST" action="{{ route('manager.rates.destroy', $rate) }}" class="manager-inline-delete" onsubmit="return confirm('Remove this temporary rate change? Normal rates are kept.')">
            @csrf @method('DELETE')<button type="submit">Cancel</button>
          </form>
        </span>
      </div>
    @endforeach
  @endif
</section>

<section class="manager-form-panel manager-section-gap">
  <p class="eyebrow">Add an effective-dated rate set</p>
  <h2 class="manager-form-title">Schedule rate change</h2>
  <form method="POST" action="{{ route('manager.rates.store') }}" data-range-check data-range-strict>
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
      <div class="manager-field"><label class="f" for="effective_at">Effective from</label><input id="effective_at" name="effective_at" type="datetime-local" min="{{ \App\Support\Lot::now()->format('Y-m-d\TH:i') }}" value="{{ old('effective_at', \App\Support\Lot::now()->addDay()->startOfDay()->format('Y-m-d\TH:i')) }}" data-range-start required><p class="hint">Lot time (Asia/Manila). The new rates start at this time.</p></div>
      <div class="manager-field"><label class="f" for="ends_at">Ends at <span class="manager-muted">(optional)</span></label><input id="ends_at" name="ends_at" type="datetime-local" min="{{ \App\Support\Lot::now()->format('Y-m-d\TH:i') }}" value="{{ old('ends_at') }}" data-range-end><p class="hint">For a temporary rate. Leave empty to keep the rate until it is replaced.</p></div>
      <div class="manager-field manager-field-wide"><label class="f" for="reason">Reason for change</label><input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}" required></div>
    </div>
    @foreach (['first_hour_fee','additional_hour_fee','daily_maximum','lost_ticket_penalty','overstay_penalty','effective_at','ends_at','reason'] as $field)
      @error($field)<p class="err">{{ $message }}</p>@enderror
    @endforeach
    <div class="manager-form-actions"><button class="btn sm" type="submit">Save rate set</button></div>
  </form>
</section>

<section class="manager-panel manager-section-gap">
  <div class="manager-panel-heading">
    <div><p class="eyebrow">Exit discounts</p><h2>Discounts</h2></div>
    <button class="btn sm" type="button" data-open-dialog="discount-create-dialog">Add discount</button>
  </div>
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
            <div class="manager-row-actions">
              <button class="manager-text-button" type="button" data-open-dialog="discount-edit-{{ $discount->id }}">Edit</button>
              <form method="POST" action="{{ route('manager.discounts.destroy', $discount) }}" class="manager-inline-delete" onsubmit="return confirm('Remove this discount?')">
                @csrf @method('DELETE')<button type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td class="manager-table-empty" colspan="6">No discounts have been added.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>

<dialog class="manager-dialog" id="discount-create-dialog" aria-labelledby="discount-create-title"
  @if (old('_form') === 'add') data-open-on-load @endif>
  <div class="manager-dialog-inner">
    <button class="manager-dialog-close" type="button" data-close-dialog aria-label="Close">X</button>
    <p class="eyebrow">Exit discounts</p>
    <h2 id="discount-create-title">Add discount</h2>
    <p class="manager-muted">Discounts reduce the parking charge at exit. A value of 0% keeps the record without reducing the fee.</p>
    <form method="POST" action="{{ route('manager.discounts.store') }}">
      @csrf
      <input type="hidden" name="_form" value="add">
      <div class="manager-dialog-fields">
        <div class="manager-field">
          <label class="f" for="discount-name">Name</label>
          <input id="discount-name" name="name" maxlength="80" value="{{ old('_form') === 'add' ? old('name') : '' }}" required>
          @if (old('_form') === 'add') @error('name')<p class="err">{{ $message }}</p>@enderror @endif
        </div>
        <div class="manager-field">
          <label class="f" for="discount-percent">Percent (0 to 100)</label>
          <input id="discount-percent" name="percent" type="number" min="0" max="100" step="1" value="{{ old('_form') === 'add' ? old('percent') : '' }}" required>
          @if (old('_form') === 'add') @error('percent')<p class="err">{{ $message }}</p>@enderror @endif
        </div>
        <div class="manager-field">
          <label class="f" for="discount-proof">Proof required</label>
          <input id="discount-proof" name="proof_label" maxlength="80" value="{{ old('_form') === 'add' ? old('proof_label') : '' }}" required>
          @if (old('_form') === 'add') @error('proof_label')<p class="err">{{ $message }}</p>@enderror @endif
        </div>
        <label class="ck"><input type="checkbox" name="requires_manager_pin" value="1" @checked(old('_form') === 'add' && old('requires_manager_pin'))>Require manager PIN</label>
      </div>
      <div class="manager-dialog-actions">
        <button class="btn sm" type="submit">Add discount</button>
        <button class="btn ghost sm" type="button" data-close-dialog>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

@foreach ($discounts as $discount)
  @php($editing = old('_form') === 'edit' && (string) old('discount_id') === (string) $discount->id)
  <dialog class="manager-dialog" id="discount-edit-{{ $discount->id }}" aria-labelledby="discount-edit-title-{{ $discount->id }}"
    @if ($editing) data-open-on-load @endif>
    <div class="manager-dialog-inner">
      <button class="manager-dialog-close" type="button" data-close-dialog aria-label="Close">X</button>
      <p class="eyebrow">Exit discounts</p>
      <h2 id="discount-edit-title-{{ $discount->id }}">Edit {{ $discount->name }}</h2>
      <form method="POST" action="{{ route('manager.discounts.update', $discount) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="discount_id" value="{{ $discount->id }}">
        <div class="manager-dialog-fields">
          <div class="manager-field">
            <label class="f" for="discount-name-{{ $discount->id }}">Name</label>
            <input id="discount-name-{{ $discount->id }}" name="name" maxlength="80" value="{{ $editing ? old('name') : $discount->name }}" required>
            @if ($editing) @error('name')<p class="err">{{ $message }}</p>@enderror @endif
          </div>
          <div class="manager-field">
            <label class="f" for="discount-percent-{{ $discount->id }}">Percent (0 to 100)</label>
            <input id="discount-percent-{{ $discount->id }}" name="percent" type="number" min="0" max="100" step="1" value="{{ $editing ? old('percent') : $discount->percent }}" required>
            @if ($editing) @error('percent')<p class="err">{{ $message }}</p>@enderror @endif
          </div>
          <div class="manager-field">
            <label class="f" for="discount-proof-{{ $discount->id }}">Proof required</label>
            <input id="discount-proof-{{ $discount->id }}" name="proof_label" maxlength="80" value="{{ $editing ? old('proof_label') : $discount->proof_label }}" required>
            @if ($editing) @error('proof_label')<p class="err">{{ $message }}</p>@enderror @endif
          </div>
          <label class="ck"><input type="checkbox" name="requires_manager_pin" value="1" @checked($editing ? old('requires_manager_pin') : $discount->requires_manager_pin)>Manager PIN required</label>
          <label class="ck"><input type="checkbox" name="active" value="1" @checked($editing ? old('active') : $discount->active)>Active</label>
        </div>
        <div class="manager-dialog-actions">
          <button class="btn sm" type="submit">Save discount</button>
          <button class="btn ghost sm" type="button" data-close-dialog>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
@endforeach

<section class="manager-panel manager-section-gap">
  <div class="manager-panel-heading">
    <div><p class="eyebrow">Approval credentials</p><h2>Manager PIN</h2></div>
    <span class="badge {{ $managerPinIsSet ? 'b-ok' : 'b-warn' }}">{{ $managerPinIsSet ? 'Set' : 'Not set' }}</span>
  </div>
  <p class="manager-muted">Create or update your six-digit PIN. You must confirm your current password each time. The PIN is stored as a one-way hash and cannot be viewed after saving.</p>
  <p class="manager-hint-panel">Discounts marked as requiring a manager PIN are not yet connected to an attendant-side PIN verification flow.</p>
  <div class="manager-form-actions">
    <button class="btn sm" type="button" data-open-dialog="manager-pin-dialog">
      {{ $managerPinIsSet ? 'Edit manager PIN' : 'Create manager PIN' }}
    </button>
  </div>
</section>

<dialog class="manager-dialog" id="manager-pin-dialog" aria-labelledby="manager-pin-title"
  @if ($errors->has('manager_pin') || $errors->has('manager_pin_confirmation') || $errors->has('current_password')) data-open-on-load @endif>
  <div class="manager-dialog-inner">
    <button class="manager-dialog-close" type="button" data-close-dialog aria-label="Close">X</button>
    <p class="eyebrow">Approval credentials</p>
    <h2 id="manager-pin-title">{{ $managerPinIsSet ? 'Edit manager PIN' : 'Create manager PIN' }}</h2>
    <p class="manager-muted">Choose a six-digit PIN and confirm it. Your current account password is required to save.</p>
    <form method="POST" action="{{ route('manager.rates.manager-pin') }}" data-busy data-busy-label="Saving PIN">
      @csrf
      @method('PUT')
      <div class="manager-dialog-fields">
        <div class="manager-field">
          <label class="f" for="manager-pin">New six-digit PIN</label>
          <input id="manager-pin" name="manager_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required autofocus>
          @error('manager_pin')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div class="manager-field">
          <label class="f" for="manager-pin-confirmation">Retype PIN</label>
          <input id="manager-pin-confirmation" name="manager_pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>
        </div>
        <div class="manager-field">
          <label class="f" for="manager-pin-current-password">Current account password</label>
          <input id="manager-pin-current-password" name="current_password" type="password" autocomplete="current-password" required>
          @error('current_password')<p class="err">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="manager-dialog-actions">
        <button class="btn sm" type="submit">Save manager PIN</button>
        <button class="btn ghost sm" type="button" data-close-dialog>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<section class="manager-panel manager-section-gap">
  <p class="eyebrow">Reservation and exit rules</p><h2>Current operational settings</h2>
  <div class="manager-data-grid">
    <div><span>Reservation fee</span><strong>Free</strong></div>
    <div><span>Reservation grace period</span><strong>{{ $settings['reservation_grace_minutes'] ?? 10 }} minutes</strong></div>
    <div><span>Early check-in</span><strong>{{ $settings['early_check_in_minutes'] ?? 30 }} minutes</strong></div>
    <div><span>Advance booking</span><strong>{{ $settings['maximum_advance_booking_days'] ?? 2 }} days</strong></div>
    <div><span>Exit window after payment</span><strong>{{ $settings['exit_window_minutes'] ?? 15 }} minutes</strong></div>
    <div><span>Long-parking threshold</span><strong>{{ $settings['long_parking_threshold_hours'] ?? 12 }} hours</strong></div>
  </div>
  <a class="manager-text-link" href="{{ route('manager.settings.index') }}">Edit operational rules</a>
</section>

<section class="manager-panel manager-section-gap">
  <p class="eyebrow">Rate change history</p><h2>Recent updates</h2>
  @forelse ($history as $entry)
    <div class="manager-summary-row"><span>{{ \App\Support\Lot::local($entry->created_at)?->format('M j, Y g:i A') }} &mdash; {{ $entry->subject }}</span><strong>{{ $entry->user?->name ?? 'System' }}</strong></div>
  @empty
    <p class="manager-muted">No rate changes have been recorded yet.</p>
  @endforelse
  <a class="manager-text-link" href="{{ route('manager.activity.index', ['action' => 'rate_set_created']) }}">View full change log</a>
</section>
