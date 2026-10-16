<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.slots.index') }}">Parking slots</a> / Slot maintenance</p>
  <h1>Take {{ $slot->code }} out of service</h1>
  <p class="manager-page-lede">Unavailable slots will not be offered for parking until a manager returns them to service.</p>
</section>

<section class="manager-form-panel">
  <form method="POST" action="{{ route('manager.slots.service.update', $slot) }}">
    @csrf
    @method('PATCH')
    <div class="manager-form-grid">
      <div class="manager-field manager-field-wide">
        <label class="f" for="service-reason">Reason</label>
        <select id="service-reason" name="reason" required @if ($errors->has('reason')) aria-invalid="true" @endif>
          <option value="">Choose a reason</option>
          @foreach ($reasons as $reason)
            <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>
          @endforeach
        </select>
        @error('reason')<p class="err">{{ $message }}</p>@enderror
      </div>
      <div class="manager-field manager-field-wide">
        <label class="f" for="service-notes">Note <span class="manager-muted">(optional)</span></label>
        <textarea id="service-notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
      </div>
    </div>
    <div class="manager-service-impact">
      <strong>{{ $slot->code }} is {{ $slot->statusLabel() }}</strong>
      <span>Only available slots can be taken out of service.</span>
    </div>
    <div class="manager-form-actions">
      <a class="btn ghost sm" href="{{ route('manager.slots.index') }}">Cancel</a>
      <button class="btn sm" type="submit">Confirm</button>
    </div>
  </form>
</section>
