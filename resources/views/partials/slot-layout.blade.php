{{-- The lot map as the Parking Manager laid it out. $zones: slots grouped by zone. $item: the view that draws one slot.
     $scale: 1 or 1.25 (a larger canvas for boards that show more inside each slot). --}}
@php
    $scale = $scale ?? 1;
    $positions = $zonePositions ?? \App\Support\SlotLayout::zonePositions($zones->flatMap(fn ($group) => $group));
@endphp
<p class="slot-layout-hint">Swipe sideways to see the whole lot.</p>
<div class="lot slot-layout {{ $scale > 1 ? 'scale-wide' : '' }}" data-layout-readonly @isset($label) role="group" aria-label="{{ $label }}" @endisset>
  <div class="slot-layout-canvas" data-layout-canvas data-scale="{{ $scale }}">
    @foreach ($zones as $zone => $zoneSlots)
      @php $zp = $positions[$zone] ?? null; $moto = ($zoneSlots->first()?->vehicle_class ?? 'car') === 'motorcycle'; @endphp
      <span class="slot-layout-zone-label" data-layout-zone-label data-zone="{{ $zone }}"
        data-x="{{ $zp?->label_x ?? '' }}" data-y="{{ $zp?->label_y ?? '' }}">{{ $zone }}<em class="zcls {{ $moto ? 'moto' : '' }}">{{ $moto ? 'Motorcycles' : 'Cars' }}</em></span>
    @endforeach
    @foreach ($zones as $zone => $zoneSlots)
      @foreach ($zoneSlots as $slot)
        @include($item, ['slot' => $slot, 'zone' => $zone])
      @endforeach
    @endforeach
  </div>
  <div class="slot-layout-lane" aria-hidden="true"><span>Entrance</span><i></i><span>Exit</span></div>
</div>
