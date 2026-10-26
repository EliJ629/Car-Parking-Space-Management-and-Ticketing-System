@php
    $cls = ['available' => '', 'occupied' => 'occ', 'reserved' => 'res', 'pending_exit' => 'pend', 'out_of_service' => 'out', 'awaiting_approval' => 'wait'];
    $label = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'pending_exit' => 'Pending exit', 'out_of_service' => 'Out of service', 'awaiting_approval' => 'Awaiting approval'];
    $free = $slot->state === 'available';
@endphp
<label class="sl pick slot-layout-slot {{ $cls[$slot->state] ?? '' }} {{ $free ? '' : 'off dim' }}"
  data-layout-slot data-slot-id="{{ $slot->id }}" data-class="{{ $slot->vehicle_class ?: 'car' }}" data-state="{{ $slot->state }}" data-want="{{ $free && ($slotId ?? 0) === $slot->id ? 1 : 0 }}" data-zone="{{ $zone }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}">
  <input type="radio" name="slot" value="{{ $slot->id }}" @disabled(! $free) aria-label="Slot {{ $slot->code }}, {{ $label[$slot->state] ?? $slot->state }}{{ $slot->type === 'PWD' ? ', PWD slot' : '' }}">
  <b>{{ $slot->code }}</b><span data-orig="{{ $label[$slot->state] ?? '' }}">{{ $label[$slot->state] ?? '' }}</span>@if (in_array($slot->state, ['reserved', 'awaiting_approval', 'occupied', 'pending_exit'], true) && ($slot->booked_label || ($slot->parked_label ?? null)))<small class="dur">{{ $slot->state === 'occupied' || $slot->state === 'pending_exit' ? ($slot->parked_label ?? '') : $slot->booked_label }}</small>@endif
  @if ($slot->type === 'PWD')<em class="pwdtag" title="PWD slot: needs a PWD ID photo">PWD</em>@endif
</label>
