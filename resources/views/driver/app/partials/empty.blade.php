<div class="empty-state">
  <div class="ic" aria-hidden="true">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon ?? '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 16V8h3.5a2.5 2.5 0 0 1 0 5H9"/>' !!}</svg>
  </div>
  <h2>{{ $title }}</h2>
  <p>{{ $text }}</p>
  @if (! empty($cta))<div class="actions" style="max-width:320px;margin-inline:auto"><a class="btn block" href="{{ $cta[1] }}">{{ $cta[0] }}</a></div>@endif
</div>
