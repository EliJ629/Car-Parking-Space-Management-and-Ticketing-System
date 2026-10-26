@php
    $in = $info[$slot->id];
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<button type="button" class="sl slot-layout-slot {{ $cls[$slot->status] ?? '' }} {{ $in['long'] ? 'long' : '' }}" data-slot="{{ $slot->code }}"
  data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
  data-type="{{ $slot->type }}" data-status="{{ $in['long'] ? 'Long parked' : ($label[$slot->status] ?? $slot->status) }}"
  data-vehicle="{{ $in['vehicle'] }}" data-since="{{ $in['since'] }}" data-booking="{{ $in['booking'] }}" data-reason="{{ $in['reason'] }}" data-report="{{ $in['report'] }}"
  aria-label="Slot {{ $slot->code }}, {{ $in['long'] ? 'long parked, '.$in['plate'].', '.$in['duration'] : ($label[$slot->status] ?? $slot->status) }}{{ $slot->type === 'PWD' ? ', PWD slot' : '' }}">
  <b>{{ $slot->code }}</b>
  @if ($in['long'])
    <span class="lp"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Long parked</span>
  @else
    <span>{{ $label[$slot->status] ?? '' }}</span>
  @endif
  @if ($in['plate'] && in_array($slot->status, ['occupied', 'pending_exit'], true))
    <small class="plt">{{ $in['plate'] }}</small><small class="dur">{{ $in['duration'] }}</small>
  @endif
  @if ($in['booked'] && $slot->status === 'reserved')
    <small class="plt">{{ $in['booked_plate'] }}</small><small class="dur">{{ $in['booked'] }}</small>
  @endif
  @if ($slot->type === 'PWD')<em class="pwdtag" title="PWD slot">PWD</em>@endif
</button>
