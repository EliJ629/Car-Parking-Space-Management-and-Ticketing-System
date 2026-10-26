@php $isMine = $slot->code === $mine; @endphp
<div class="sl slot-layout-slot {{ $isMine ? 'me' : '' }}" data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $zone }}" data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}">
  <b>{{ $slot->code }}</b>@if ($isMine)<span>You are here</span>@endif
  @if (! $isMine && $slot->status === 'occupied' && $slot->parked_label)<small class="dur">{{ $slot->parked_label }}</small>@elseif (! $isMine && $slot->status === 'reserved' && $slot->booked_label)<small class="dur">{{ $slot->booked_label }}</small>@endif
  @if ($isMine && $slot->parked_label)<small class="dur">{{ $slot->parked_label }}</small>@endif
  @if ($slot->type === 'PWD')<em class="pwdtag" title="PWD slot">PWD</em>@endif
</div>
