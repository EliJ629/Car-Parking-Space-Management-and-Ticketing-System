{{-- A bottom sheet pop-up. $modal: title, body, note, primary{label,url|close}, secondary{label,url|close}, tone. --}}
<dialog class="dsheet {{ $modal['tone'] ?? '' }}" id="{{ $id }}" aria-labelledby="{{ $id }}-t" @if (! empty($auto)) data-autoopen @endif>
  <div class="in">
    <h2 id="{{ $id }}-t">{{ $modal['title'] }}</h2>
    <p class="sub" style="color:var(--ink)">{{ $modal['body'] }}</p>
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
