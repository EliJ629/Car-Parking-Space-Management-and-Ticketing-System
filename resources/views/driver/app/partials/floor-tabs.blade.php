@if ($floors !== [])
  <nav class="driver-floor-tabs" aria-label="Parking floors">
    @foreach ($floors as $floor)
      <a href="{{ route($route, ['floor' => $floor]) }}"
         class="{{ (int) $selectedFloor === (int) $floor ? 'active' : '' }}"
         @if ((int) $selectedFloor === (int) $floor) aria-current="page" @endif>
        Floor {{ $floor }}
      </a>
    @endforeach
  </nav>
@endif
