@php
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service'];
@endphp
<button type="button" class="sl slot-layout-slot {{ $cls[$slot->status] ?? '' }}"
  data-layout-slot data-slot="{{ $slot->code }}" data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}"
  data-type="{{ $slot->type }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
  data-status="{{ $label[$slot->status] ?? ucfirst($slot->status) }}" data-parked="{{ in_array($slot->status, ['occupied', 'pending_exit'], true) ? $slot->parked_label : '' }}" data-booked="{{ $slot->status === 'reserved' ? $slot->booked_label : '' }}" data-class="{{ $slot->classLabel() }}" data-free="{{ $slot->status === 'available' ? 1 : 0 }}"
  data-reserve="{{ route('driver.reserve', ['slot' => $slot->id]) }}"
  aria-label="Slot {{ $slot->code }}, {{ $label[$slot->status] ?? $slot->status }}{{ $slot->type === 'PWD' ? ', PWD slot' : '' }}">
  <b>{{ $slot->code }}</b><span>{{ $label[$slot->status] ?? '' }}</span>
  @if (in_array($slot->status, ['occupied', 'pending_exit'], true) && $slot->parked_label)<small class="dur" title="Parked for">{{ $slot->parked_label }}</small>
  @elseif ($slot->status === 'reserved' && $slot->booked_label)<small class="dur" title="Reserved for">{{ $slot->booked_label }}</small>@endif
  @if ($slot->type === 'PWD')<em class="pwdtag" title="PWD slot">PWD</em>@endif
</button>
