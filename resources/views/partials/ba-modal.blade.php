{{-- A pop-up for the booth attendant. $modal: title, body, note, primary{label,url|close}, secondary{label,url|close}, tone. --}}
<dialog class="badlg {{ $modal['tone'] ?? '' }} {{ ! empty($auto) ? 'auto-open' : '' }}" id="{{ $id }}" aria-labelledby="{{ $id }}-t">
  <div class="in">
    <h2 id="{{ $id }}-t">{{ $modal['title'] }}</h2>
    <p>{{ $modal['body'] }}</p>
    @if (! empty($modal['note']))<p class="note">{{ $modal['note'] }}</p>@endif
    <div class="actions">
      @foreach (['secondary' => 'ghost', 'primary' => 'primary'] as $slot => $style)
        @if (! empty($modal[$slot]))
          @php $a = $modal[$slot]; @endphp
          @if (! empty($a['url']))
            <a class="btn {{ $style }}" href="{{ $a['url'] }}">{{ $a['label'] }}</a>
          @else
            <button class="btn {{ $style }}" type="button" data-close>{{ $a['label'] }}</button>
          @endif
        @endif
      @endforeach
    </div>
  </div>
</dialog>
