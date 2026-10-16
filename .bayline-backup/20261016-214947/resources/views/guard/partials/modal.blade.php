<dialog class="gdlg {{ $modal['tone'] ?? '' }}" id="{{ $id }}" aria-labelledby="{{ $id }}-t" @if (! empty($auto)) data-autoopen @endif>
  <div class="in">
    <button class="x" type="button" data-close aria-label="Close">X</button>
    <h2 id="{{ $id }}-t">{{ $modal['title'] }}</h2>
    <p>{{ $modal['body'] }}</p>
    @if (! empty($modal['note']))<p class="note">{{ $modal['note'] }}</p>@endif
    <div class="actions">
      @foreach (['primary' => '', 'secondary' => 'ghost'] as $slot => $style)
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
