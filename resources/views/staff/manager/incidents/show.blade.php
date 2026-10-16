<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.incidents.index') }}">Incidents</a> / {{ $incident->code }}</p><h1>{{ $incident->category }}</h1><p class="manager-page-lede">{{ $incident->code }} &middot; {{ $incident->source === 'auto' ? 'Automatic alert' : 'Manual report' }} &middot; {{ \App\Support\Lot::local($incident->reported_at)?->format('M j, Y g:i A') }}</p></section>
<section class="manager-panel manager-detail-panel">
  <div class="manager-data-grid">
    <div><span>Vehicle</span><strong>{{ $incident->plate_number ?? 'Not specified' }}</strong></div>
    <div><span>Location</span><strong>{{ $incident->slot?->code ?? $incident->location ?? 'Not specified' }}</strong></div>
    <div><span>Reported by</span><strong>{{ $incident->reporter?->name ?? ($incident->source === 'auto' ? 'System' : 'Former staff') }}</strong></div>
    <div><span>Status</span><strong>{{ strtoupper(str_replace('_', ' ', $incident->status)) }}</strong></div>
    <div class="manager-field-wide"><span>Description</span><strong>{{ $incident->description }}</strong></div>
    @if ($incident->resolution_note)<div class="manager-field-wide"><span>Resolution note</span><strong>{{ $incident->resolution_note }}</strong></div>@endif
    @if ($incident->resolved_at)<div><span>Resolved at</span><strong>{{ \App\Support\Lot::local($incident->resolved_at)?->format('M j, Y g:i A') }}</strong></div><div><span>Resolved by</span><strong>{{ $incident->resolver?->name ?? 'Former staff' }}</strong></div>@endif
  </div>
  @if ($incident->status !== 'resolved')
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
