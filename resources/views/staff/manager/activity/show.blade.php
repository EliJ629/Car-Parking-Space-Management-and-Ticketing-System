<section class="manager-page-heading"><p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.activity.index') }}">Activity log</a> / Entry detail</p><h1>{{ str_replace('_', ' ', ucfirst($entry->action)) }}</h1></section>
<section class="manager-panel manager-detail-panel"><div class="manager-data-grid">
  <div><span>Time</span><strong>{{ \App\Support\Lot::local($entry->created_at)?->format('M j, Y g:i:s A') }}</strong></div>
  <div><span>User</span><strong>{{ $entry->user?->name ?? 'System / removed user' }}{{ $entry->user?->username ? ' ('.$entry->user->username.')' : '' }}</strong></div>
  <div><span>Area</span><strong>{{ $entry->area ?? '—' }}</strong></div>
  <div><span>IP address</span><strong>{{ $entry->ip_address ?? 'Not recorded' }}</strong></div>
  <div class="manager-field-wide"><span>Subject</span><strong>{{ $entry->subject ?? '—' }}</strong></div>
</div>
  <h2 class="manager-subheading">Stored details</h2>
  @if (is_array($entry->details) && $entry->details !== [])
    <div class="manager-detail-section">
      <div class="manager-detail-list">
        @foreach ($entry->details as $label => $value)
          @include('staff.manager.activity._detail-item', ['label' => $label, 'value' => $value])
        @endforeach
      </div>
    </div>
  @elseif ($entry->details)
    <pre class="manager-json">{{ json_encode($entry->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
  @else
    <p class="manager-muted">This entry predates detailed change snapshots.</p>
  @endif
  <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.activity.index') }}">Back to log</a></div>
</section>
