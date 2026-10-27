<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.incidents.index') }}">Incidents</a> / {{ $incident->code }}</p><h1>{{ $incident->title() }}</h1><p class="manager-page-lede">{{ $incident->code }} &middot; {{ $incident->source === 'auto' ? 'Automatic alert' : 'Manual report' }} &middot; {{ \App\Support\Lot::local($incident->reported_at)?->format('M j, Y g:i A') }}</p></section>
<section class="manager-panel manager-detail-panel">
  <div class="manager-data-grid">
    <div><span>{{ $incident->category === 'Vehicle damage' ? 'Damaged vehicle' : 'Vehicle' }}</span><strong>{{ $incident->plate_number ? \App\Support\Lot::plate($incident->plate_number) : 'Not specified' }}</strong></div>
    @if ($incident->category === 'Vehicle damage')<div><span>Responsible vehicle</span><strong>{{ $incident->responsible_plate ? \App\Support\Lot::plate($incident->responsible_plate) : 'Unknown' }}</strong></div>@endif
    <div><span>Review</span><strong>{{ strtoupper($incident->confirmation) }}@if ($incident->confirmed_at) &middot; {{ \App\Support\Lot::local($incident->confirmed_at)?->format('M j, g:i A') }} by {{ $incident->confirmer?->name ?? 'System' }}@endif</strong></div>
    @if ($incident->confirmation_note)<div class="manager-field-wide"><span>Review note</span><strong>{{ $incident->confirmation_note }}</strong></div>@endif
    <div><span>Driver notified</span><strong>{{ $incident->driver_notified_at ? \App\Support\Lot::local($incident->driver_notified_at)?->format('M j, g:i A').' by '.strtoupper((string) $incident->driver_notice_channel).' - '.$incident->driver_notice_result : 'Not yet' }}</strong></div>
    <div><span>Location</span><strong>{{ $incident->slot?->code ?? $incident->location ?? 'Not specified' }}</strong></div>
    <div><span>Reported by</span><strong>{{ $incident->reporter?->name ?? ($incident->source === 'auto' ? 'System' : 'Former staff') }}</strong></div>
    <div><span>Status</span><strong>{{ strtoupper(str_replace('_', ' ', $incident->status)) }}</strong></div>
    <div class="manager-field-wide"><span>Description</span><strong>{{ $incident->description }}</strong></div>
    @if ($incident->resolution_note)<div class="manager-field-wide"><span>Resolution note</span><strong>{{ $incident->resolution_note }}</strong></div>@endif
    @if ($incident->resolved_at)<div><span>Resolved at</span><strong>{{ \App\Support\Lot::local($incident->resolved_at)?->format('M j, Y g:i A') }}</strong></div><div><span>Resolved by</span><strong>{{ $incident->resolver?->name ?? 'Former staff' }}</strong></div>@endif
  </div>
  @if ($incident->attributed_plate)
    <h2 class="manager-subheading">Ban count for {{ \App\Support\Lot::plate($incident->attributed_plate) }}</h2>
    <p class="manager-page-lede">{{ $counted->count() }} of {{ $threshold }} confirmed incidents.@if ($ban) <strong>This vehicle is banned.</strong>@endif @if ($incident->confirmation === 'pending')Only confirmed reports count. This one is waiting for your review.@endif</p>
  @elseif (in_array($incident->category, \App\Models\Incident::BAN_CATEGORIES, true))
    <p class="manager-page-lede">No responsible vehicle is recorded, so this report cannot count toward a ban.</p>
  @endif
  @if ($incident->confirmation === 'pending')
    <h2 class="manager-subheading">Review this report</h2>
    <div class="manager-form-actions">
      <form method="POST" action="{{ route('manager.incidents.confirm', $incident) }}" data-busy>@csrf<button class="btn sm" type="submit">Confirm report</button></form>
      <form method="POST" action="{{ route('manager.incidents.dismiss', $incident) }}" data-busy>@csrf<input name="note" required minlength="3" maxlength="255" placeholder="Why is it not valid?" aria-label="Reason for dismissing"><button class="btn ghost sm" type="submit">Dismiss</button></form>
    </div>
  @endif
  @if ($blockers)
    <div class="manager-feedback manager-feedback-error" role="status"><strong>This report cannot be resolved yet</strong><ul>@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
  @endif
  @if ($incident->status !== 'resolved' && $incident->category !== \App\Services\IncidentService::LONG_PARKED)
    <h2 class="manager-subheading">Update incident status</h2>
    <form method="POST" action="{{ route('manager.incidents.update', $incident) }}">@csrf @method('PATCH')
      <div class="manager-form-grid">
        <div class="manager-field"><label class="f" for="incident-status-update">Status</label><select id="incident-status-update" name="status" required>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(old('status', $incident->status) === $status)>{{ strtoupper(str_replace('_', ' ', $status)) }}</option>@endforeach</select>@error('status')<p class="err">{{ $message }}</p>@enderror</div>
        <div class="manager-field manager-field-wide"><label class="f" for="resolution_note">Resolution / progress note</label><textarea id="resolution_note" name="resolution_note" rows="3" maxlength="500">{{ old('resolution_note', $incident->resolution_note) }}</textarea>@error('resolution_note')<p class="err">{{ $message }}</p>@enderror</div>
      </div>
      <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.incidents.index') }}">Back</a><button class="btn sm" type="submit">Save status</button></div>
    </form>
  @else
    <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.incidents.index') }}">Back to incidents</a></div>
  @endif
</section>
