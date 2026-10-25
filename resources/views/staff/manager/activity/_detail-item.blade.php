@php
  $label = is_int($label) ? 'Item '.($label + 1) : \Illuminate\Support\Str::headline((string) $label);
@endphp
<div class="manager-detail-item">
  <span>{{ $label }}</span>
  @if (is_array($value))
    <div class="manager-detail-children">
      @foreach ($value as $childLabel => $childValue)
        @include('staff.manager.activity._detail-item', ['label' => $childLabel, 'value' => $childValue])
      @endforeach
    </div>
  @else
    <strong>
      @if (is_bool($value))
        {{ $value ? 'Yes' : 'No' }}
      @elseif ($value === null || $value === '')
        &mdash;
      @else
        {{ $value }}
      @endif
    </strong>
  @endif
</div>
