@extends('layouts.attendant')

@section('title', 'Parking slots - Booth Attendant')
@section('tab', 'slots')

@section('content')
<h1 class="ba-page-title">Parking slots</h1>
<p class="ba-sub">Click a slot to see its details or to correct its status.</p>

@if ($errors->any())
  <div style="background:var(--stop-bg);border-left:5px solid var(--stop);border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px;color:var(--stop);">
    <b style="font-size:15px;display:block;margin-bottom:4px;">Cannot update slot status:</b>
    <ul style="margin:0;padding-left:18px;font-size:13px;">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;align-items:start;">
  
  <!-- Left Column: Lot Slots Board (BA-51) -->
  <div class="ba-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
      <div>
        <h2 style="margin:0 0 2px;">Lot slots, live</h2>
        @if (count($floors) > 1)
          <span style="font-size:13px;color:var(--muted);font-weight:600;">Floor {{ $selectedFloor }}</span>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="ba-view-toggle">
          <button type="button" id="btn-toggle-map" class="active" onclick="switchSlotView('map')">Visual map</button>
          <button type="button" id="btn-toggle-grid" onclick="switchSlotView('grid')">Grid list</button>
        </div>
        <span style="font-size:13px;color:var(--muted);font-weight:600;">
          {{ $counts['available'] }} of {{ $slots->count() }} available
        </span>
      </div>
    </div>

    @if (count($floors) > 1)
      <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        @foreach ($floors as $floor)
          <a href="{{ route('attendant.slots.index', ['floor' => $floor]) }}"
             class="btn sm {{ (int) $selectedFloor === (int) $floor ? 'primary' : 'ghost' }}"
             style="padding:5px 14px;font-size:13px;text-decoration:none;">
            Floor {{ $floor }}
          </a>
        @endforeach
      </div>
    @endif

    <!-- Visual Map View (Matching Manager Layout Canvas) -->
    <div class="ba-layout-wrap" id="ba-layout-view">
      <div class="ba-map-controls">
        <span style="color:var(--muted);display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
          <span class="ba-pan-hint">Pan around lot</span>
        </span>
        <div style="display:inline-flex;gap:3px;background:var(--bg);border:1px solid var(--line);border-radius:6px;padding:2px;">
          <button type="button" id="btn-map-fit" onclick="toggleMapFit(true)" style="border:none;background:none;font:600 11.5px var(--f-b);color:var(--muted);padding:4px 9px;border-radius:4px;cursor:pointer;">Fit to screen</button>
          <button type="button" id="btn-map-100" class="active" onclick="toggleMapFit(false)" style="border:none;background:var(--ink);color:var(--bg);font:600 11.5px var(--f-b);padding:4px 9px;border-radius:4px;cursor:pointer;">100% Zoom</button>
        </div>
      </div>
      <div class="ba-layout-canvas" data-layout-canvas>
        @foreach ($floorSlots->groupBy('zone') as $zone => $zoneSlots)
          @php
            $zonePosition = $zonePositions[$zone] ?? null;
            $vehicleType = $zonePosition?->vehicle_type ?? 'Car';
            $displayZoneName = str_starts_with($zone, 'Zone') ? $zone : 'Zone ' . $zone;
          @endphp
          <span data-layout-zone-label data-zone="{{ $zone }}"
                data-x="{{ $zonePosition?->label_x ?? '' }}" data-y="{{ $zonePosition?->label_y ?? '' }}">
            {{ $displayZoneName }} <small>({{ $vehicleType }})</small>
          </span>
        @endforeach
        @foreach ($floorSlots as $slot)
          @php
              $curr = $slot->currentSession();
              $booking = $slot->nextOpenBooking();
              $isLong = $curr?->isLongParked() ?? false;
          @endphp
          <div class="ba-slot ba-layout-slot {{ $slot->status }} {{ $isLong ? 'long' : '' }}"
               data-layout-slot data-slot-id="{{ $slot->id }}" data-zone="{{ $slot->zone }}"
               data-x="{{ $slot->layout_x ?? '' }}" data-y="{{ $slot->layout_y ?? '' }}"
               title="{{ $slot->code }} &mdash; {{ $slot->statusLabel() }}"
               style="cursor:pointer;"
               onclick="openSlotDetail({{ json_encode([
                   'id' => $slot->id,
                   'code' => $slot->code,
                   'zone' => $slot->zone,
                   'type' => $slot->type,
                   'status' => $slot->status,
                   'status_label' => $slot->statusLabel(),
                   'session_id' => $curr?->id,
                   'ticket' => $curr?->ticket_number,
                   'plate' => $curr ? \App\Support\Lot::plate($curr->plate_number) : ($booking ? \App\Support\Lot::plate($booking->plate_number) : null),
                   'vehicle' => $curr ? "{$curr->vehicle_color} {$curr->vehicle_type}" : null,
                   'entry' => $curr ? \App\Support\Lot::stamp($curr->entered_at) : null,
                   'parked' => $curr ? \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($curr->entered_at)) : null,
                   'booking_driver' => $booking?->driver?->name,
                   'booking_code' => $booking?->code,
                   'booking_time' => $booking ? \App\Support\Lot::time($booking->scheduled_at) : null,
                   'out_reason' => $slot->out_of_service_reason,
               ]) }})">
            @if ($slot->type === 'PWD')<span class="pwd-tag">PWD</span>@endif
            <b>{{ $slot->code }}</b>
            <span class="st">@if ($isLong)&#9201; Long parked @else {{ $slot->statusLabel() }} @endif</span>
            @if ($curr)
              <span class="plt">{{ \App\Support\Lot::plate($curr->plate_number) }}</span>
              <span class="plt">{{ \App\Support\Lot::duration($curr->parkedMinutes()) }}</span>
            @elseif ($booking)
              <span class="plt" style="color:#92400E;">{{ \App\Support\Lot::plate($booking->plate_number) }}</span>
              @if ($slot->status === 'reserved')<span class="plt" style="color:#92400E;">{{ \App\Support\SlotLayout::tileTime($booking->scheduled_at) }}</span>@endif
            @elseif ($slot->out_of_service_reason)
              <span class="plt" style="font-size:9px;">{{ \Illuminate\Support\Str::limit($slot->out_of_service_reason, 12) }}</span>
            @endif
          </div>
        @endforeach
      </div>
      <div class="ba-layout-lane">
        <span>Entrance</span><i></i><span>Exit</span>
      </div>
    </div>

    <!-- Grouped Slots Grid View -->
    <div id="ba-grid-view" style="display:none;">
      <!-- Zone Filter Tabs & Available Count -->
      <div style="margin:12px 0 16px;">
        <div style="display:flex;gap:6px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding-bottom:4px;flex-wrap:nowrap;" id="zone-filter-tabs">
          <button type="button" class="btn sm primary zone-tab-btn" data-zone="all" style="padding:6px 14px;font-size:12.5px;white-space:nowrap;flex-shrink:0;border-radius:99px;">All Zones ({{ $slots->count() }})</button>
          @foreach ($slots->groupBy('zone') as $zoneName => $zoneSlots)
            @php
              $cleanZoneLabel = str_starts_with($zoneName, 'Zone') ? $zoneName : 'Zone ' . $zoneName;
            @endphp
            <button type="button" class="btn sm ghost zone-tab-btn" data-zone="{{ $zoneName }}" style="padding:6px 14px;font-size:12.5px;white-space:nowrap;flex-shrink:0;border-radius:99px;">{{ $cleanZoneLabel }} ({{ $zoneSlots->count() }})</button>
          @endforeach
        </div>
      </div>

      <!-- Grouped Slots by Zone -->
      @foreach ($slots->groupBy('zone') as $zoneName => $zoneSlots)
        @php
          $cleanZoneTitle = str_starts_with($zoneName, 'Zone') ? $zoneName : 'Zone ' . $zoneName;
        @endphp
        <div class="ba-zone-section" data-zone="{{ $zoneName }}" style="margin-bottom:22px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 10px;padding-bottom:6px;border-bottom:1.5px solid var(--line);">
            <b style="font-size:15px;color:var(--ink);">{{ $cleanZoneTitle }}</b>
            <span style="font-size:12px;color:var(--muted);font-weight:600;">{{ $zoneSlots->where('status', 'available')->count() }} of {{ $zoneSlots->count() }} available</span>
          </div>
          <div class="ba-zone-slots-grid">
            @foreach ($zoneSlots as $slot)
              @php
                  $curr = $slot->currentSession();
                  $booking = $slot->nextOpenBooking();
                  $isLong = $curr?->isLongParked() ?? false;
              @endphp
              <div class="ba-slot {{ $slot->status }} {{ $isLong ? 'long' : '' }}"
                   style="cursor:pointer;"
                   onclick="openSlotDetail({{ json_encode([
                       'id' => $slot->id,
                       'code' => $slot->code,
                       'zone' => $slot->zone,
                       'type' => $slot->type,
                       'status' => $slot->status,
                       'status_label' => $slot->statusLabel(),
                       'session_id' => $curr?->id,
                       'ticket' => $curr?->ticket_number,
                       'plate' => $curr ? \App\Support\Lot::plate($curr->plate_number) : ($booking ? \App\Support\Lot::plate($booking->plate_number) : null),
                       'vehicle' => $curr ? "{$curr->vehicle_color} {$curr->vehicle_type}" : null,
                       'entry' => $curr ? \App\Support\Lot::stamp($curr->entered_at) : null,
                       'parked' => $curr ? \App\Support\Lot::duration(\App\Support\Lot::minutesBetween($curr->entered_at)) : null,
                       'booking_driver' => $booking?->driver?->name,
                       'booking_code' => $booking?->code,
                       'booking_time' => $booking ? \App\Support\Lot::time($booking->scheduled_at) : null,
                       'out_reason' => $slot->out_of_service_reason,
                   ]) }})">
                @if ($slot->type === 'PWD')<span class="pwd-tag">PWD</span>@endif
                <b>{{ $slot->code }}</b>
                <span class="st">@if ($isLong)&#9201; Long parked @else {{ $slot->statusLabel() }} @endif</span>
                @if ($curr)
                  <span class="plt">{{ \App\Support\Lot::plate($curr->plate_number) }}</span>
                  <span class="plt">{{ \App\Support\Lot::duration($curr->parkedMinutes()) }}</span>
                @elseif ($booking)
                  <span class="plt" style="color:#92400E;">{{ \App\Support\Lot::plate($booking->plate_number) }}</span>
                  @if ($slot->status === 'reserved')<span class="plt" style="color:#92400E;">{{ \App\Support\SlotLayout::tileTime($booking->scheduled_at) }}</span>@endif
                @elseif ($slot->out_of_service_reason)
                  <span class="plt" style="font-size:10px;">{{ \Illuminate\Support\Str::limit($slot->out_of_service_reason, 15) }}</span>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>

    <!-- Legend matching Driver App design -->
    <div class="leg ba-detail-box" style="margin-top:14px;gap:10px 20px;">
      <span><i></i>Available</span>
      <span><i class="r"></i>Reserved</span>
      <span><i class="o"></i>Occupied</span>
      <span><i class="p"></i>Pending exit</span>
      <span><i class="lg"></i>Long parked</span>
      <span><i class="x"></i>Out of service</span>
      <span style="display:inline-flex;align-items:center;gap:6px;">
        <span style="font-size:10px;font-weight:900;background:#2563EB;color:#fff;border-radius:3px;padding:2px 5px;">♿ PWD</span> Priority
      </span>
    </div>
  </div>

  <!-- Right Column: Recent Changes & Slot Types (BA-51) -->
  <div>
    <div class="ba-card" style="margin-bottom:20px;">
      <h2>Recent manual changes</h2>
      <div class="ba-list-group">
        @forelse ($recentChanges as $rc)
          <div style="font-size:13px;padding-bottom:10px;border-bottom:1px solid var(--line);">
            <b>{{ $rc->subject }} set to {{ ucfirst(str_replace('_', ' ', $rc->details['new_status'] ?? '')) }}</b><br>
            <span style="color:var(--muted);">{{ $rc->user?->name ?? 'Staff' }}, {{ \App\Support\Lot::stamp($rc->created_at) }}. Reason: {{ $rc->details['reason'] ?? 'Manual fix' }}</span>
          </div>
        @empty
          <p style="color:var(--muted);font-size:13px;margin:0;">No manual changes recorded recently.</p>
        @endforelse
      </div>
    </div>

    <div class="ba-card">
      <h2>Slot types</h2>
      <p style="font-size:14px;color:var(--muted);margin:0;">
        <b>PWD slots:</b> A-01 and B-02.<br>
        All other slots are Standard.
      </p>
    </div>
  </div>
</div>

<!-- POP-UP BA-52: Slot Detail Modal -->
<dialog class="badlg" id="slot-detail-dlg">
  <div class="in">
    <h2>Slot <span id="sd-code"></span></h2>

    <div class="ba-detail-box" style="display:flex;flex-direction:column;gap:10px;font-size:14px;margin-bottom:18px;">
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Slot</span><b id="sd-full"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Type</span><b id="sd-type"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Status</span><b id="sd-status"></b></div>
      
      <!-- If Occupied -->
      <div id="sd-occ-block" style="display:none;flex-direction:column;gap:10px;border-top:1px dashed var(--line);padding-top:10px;margin-top:4px;">
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Vehicle</span><b id="sd-veh"></b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Ticket</span><b id="sd-ticket"></b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Entry</span><b id="sd-entry"></b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Parked for</span><b id="sd-parked"></b></div>
      </div>

      <!-- If Reserved -->
      <div id="sd-rsv-block" style="display:none;flex-direction:column;gap:10px;border-top:1px dashed var(--line);padding-top:10px;margin-top:4px;">
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Driver</span><b id="sd-rsv-driver"></b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Code</span><b id="sd-rsv-code"></b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Arrival</span><b id="sd-rsv-time"></b></div>
      </div>

      <!-- If Out of Service -->
      <div id="sd-out-block" style="display:none;border-top:1px dashed var(--line);padding-top:10px;margin-top:4px;">
        <span style="color:var(--muted);">Reason:</span> <b id="sd-out-reason"></b>
      </div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Close</button>
      <a class="btn ghost" id="sd-exit-link" href="#" style="display:none;">Open in Exit and payment</a>
      <button class="btn primary" type="button" id="sd-btn-correct">Correct status</button>
    </div>
  </div>
</dialog>

<!-- POP-UP BA-53 & BA-54: Manual Correction Modal -->
<dialog class="badlg" id="manual-correct-dlg">
  <form id="correct-form" class="in" method="POST" action="">
    @csrf
    <h2>Correct slot status</h2>

    <div class="ba-detail-box" style="margin-bottom:16px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:var(--muted);">Slot</span><b id="mc-slot-full"></b></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Current status</span><b id="mc-cur-status"></b></div>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="mc-new-status">New status</label>
      <select class="ba-input" id="mc-new-status" name="new_status">
        <option value="available">Available</option>
        <option value="occupied">Occupied</option>
        <option value="out_of_service">Out of service</option>
      </select>
    </div>

    <div class="ba-form-group">
      <label class="ba-label" for="mc-reason">Reason (required)</label>
      <textarea class="ba-input" id="mc-reason" name="reason" rows="3" placeholder="Explain the reason (e.g. Oil spill on bay, needs maintenance)" required style="resize:vertical;"></textarea>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
        <p style="font-size:12px;color:var(--muted);margin:0;">At least 10 characters. Saved with your name and the time.</p>
        <span id="mc-char-count" style="font-size:12px;font-weight:700;color:var(--stop);">0 / 10 chars</span>
      </div>
    </div>

    <div style="background:var(--warn-bg);border:1px solid var(--paint-deep);border-radius:6px;padding:12px;font-size:13px;color:var(--ink);margin-bottom:20px;">
      <b>No ticket is created or closed</b><br>
      A status change only fixes the slot. Entries and exits go through New entry and Exit and payment.
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" data-close>Cancel</button>
      <button class="btn primary" type="submit">Save changes</button>
    </div>
  </form>
</dialog>

<!-- POP-UP BA-55: Cannot Mark Available Modal -->
@if(session('error_modal') && session('error_modal')['type'] === 'cannot_mark_available')
<dialog class="badlg auto-open" id="cannot-available-dlg">
  <div class="in">
    <h2 style="color:var(--stop);">{{ session('error_modal')['slot'] }} cannot be marked Available</h2>
    
    @if(isset(session('error_modal')['ticket']))
      <p>Vehicle <b>{{ session('error_modal')['plate'] }}</b> is Occupied under ticket <b>{{ session('error_modal')['ticket'] }}</b>. A slot cannot be Available while a vehicle is in it.<br><br>Close the session in Exit and payment first. The slot is released after the Guard approves the exit.</p>
      <div class="actions">
        <button class="btn ghost" type="button" data-close>Close</button>
        <a class="btn primary" href="{{ route('attendant.exit.show', session('error_modal')['session_id']) }}">Open in Exit and payment</a>
      </div>
    @else
      <p>{{ session('error_modal')['message'] ?? 'This slot is currently held and cannot be set to Available.' }}</p>
      <div class="actions">
        <button class="btn primary" type="button" data-close>Close</button>
      </div>
    @endif
  </div>
</dialog>
@endif

@push('scripts')
<script>
let currentSelectedSlot = null;

window.openSlotDetail = function(slot) {
  currentSelectedSlot = slot;

  document.getElementById('sd-code').textContent = slot.code;
  document.getElementById('sd-full').textContent = `${slot.code}, ${slot.zone}`;
  document.getElementById('sd-type').textContent = slot.type;
  document.getElementById('sd-status').textContent = slot.status_label.toUpperCase();

  // Reset blocks
  const occBlock = document.getElementById('sd-occ-block');
  const rsvBlock = document.getElementById('sd-rsv-block');
  const outBlock = document.getElementById('sd-out-block');
  const exitLink = document.getElementById('sd-exit-link');

  occBlock.style.display = 'none';
  rsvBlock.style.display = 'none';
  outBlock.style.display = 'none';
  exitLink.style.display = 'none';

  if (slot.status === 'occupied' || slot.status === 'pending_exit') {
    occBlock.style.display = 'flex';
    document.getElementById('sd-veh').textContent = `${slot.plate}, ${slot.vehicle || 'Vehicle'}`;
    document.getElementById('sd-ticket').textContent = slot.ticket;
    document.getElementById('sd-entry').textContent = slot.entry;
    document.getElementById('sd-parked').textContent = slot.parked;

    if (slot.session_id) {
      exitLink.href = "{{ url('staff/attendant/exit/session') }}/" + slot.session_id;
      exitLink.style.display = 'inline-flex';
    }
  } else if (slot.status === 'reserved') {
    rsvBlock.style.display = 'flex';
    document.getElementById('sd-rsv-driver').textContent = slot.booking_driver;
    document.getElementById('sd-rsv-code').textContent = slot.booking_code;
    document.getElementById('sd-rsv-time').textContent = slot.booking_time;
  } else if (slot.status === 'out_of_service') {
    outBlock.style.display = 'block';
    document.getElementById('sd-out-reason').textContent = slot.out_reason || 'Out of service';
  }

  document.getElementById('slot-detail-dlg').showModal();
};

document.getElementById('sd-btn-correct').addEventListener('click', () => {
  document.getElementById('slot-detail-dlg').close();
  
  const form = document.getElementById('correct-form');
  form.action = "{{ url('staff/attendant/slots') }}/" + currentSelectedSlot.id + "/correct";

  document.getElementById('mc-slot-full').textContent = `${currentSelectedSlot.code}, ${currentSelectedSlot.zone}, ${currentSelectedSlot.type}`;
  document.getElementById('mc-cur-status').textContent = currentSelectedSlot.status_label.toUpperCase();

  // Intelligent default: pick a different status than current
  const selectEl = document.getElementById('mc-new-status');
  if (currentSelectedSlot.status === 'available') {
    selectEl.value = 'out_of_service';
  } else {
    selectEl.value = 'available';
  }

  const reasonEl = document.getElementById('mc-reason');
  reasonEl.value = '';
  updateCharCount();

  document.getElementById('manual-correct-dlg').showModal();
});

function updateCharCount() {
  const reasonEl = document.getElementById('mc-reason');
  const countSpan = document.getElementById('mc-char-count');
  if (!reasonEl || !countSpan) return;

  reasonEl.value = reasonEl.value.replace(/[<>{}\`\\]/g, '');
  const len = reasonEl.value.trim().length;
  if (len >= 10) {
    countSpan.textContent = `${len} chars (valid)`;
    countSpan.style.color = 'var(--ok)';
  } else {
    countSpan.textContent = `${len} / 10 chars minimum`;
    countSpan.style.color = 'var(--stop)';
  }
}

const reasonInput = document.getElementById('mc-reason');
if (reasonInput) {
  reasonInput.addEventListener('input', updateCharCount);
}

// Zone quick filter tabs
document.querySelectorAll('.zone-tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.zone-tab-btn').forEach(b => {
      b.classList.remove('primary');
      b.classList.add('ghost');
    });
    btn.classList.add('primary');
    btn.classList.remove('ghost');

    const targetZone = btn.dataset.zone;
    document.querySelectorAll('.ba-zone-section').forEach(sec => {
      if (targetZone === 'all' || sec.dataset.zone === targetZone) {
        sec.style.display = 'block';
      } else {
        sec.style.display = 'none';
      }
    });
  });
});

window.switchSlotView = function(mode) {
  const mapView = document.getElementById('ba-layout-view');
  const gridView = document.getElementById('ba-grid-view');
  const btnMap = document.getElementById('btn-toggle-map');
  const btnGrid = document.getElementById('btn-toggle-grid');
  if (mode === 'map') {
    mapView.style.display = 'block';
    gridView.style.display = 'none';
    btnMap.classList.add('active');
    btnGrid.classList.remove('active');
  } else {
    mapView.style.display = 'none';
    gridView.style.display = 'block';
    btnGrid.classList.add('active');
    btnMap.classList.remove('active');
  }
};

window.toggleMapFit = function(fit) {
  const wrap = document.getElementById('ba-layout-view');
  if (!wrap) return;
  const canvas = wrap.querySelector('[data-layout-canvas]');
  const btnFit = document.getElementById('btn-map-fit');
  const btn100 = document.getElementById('btn-map-100');
  
  if (fit && canvas) {
    const wrapWidth = wrap.clientWidth - 26;
    const naturalWidth = 760;
    const scale = Math.min(1, Math.max(0.35, wrapWidth / naturalWidth));
    canvas.style.transform = `scale(${scale})`;
    canvas.style.transformOrigin = 'top left';
    const naturalHeight = canvas.offsetHeight || 540;
    canvas.style.marginBottom = `-${naturalHeight * (1 - scale)}px`;
    canvas.style.marginRight = `-${naturalWidth * (1 - scale)}px`;
    wrap.style.overflowX = 'hidden';
    if (btnFit) { btnFit.classList.add('active'); }
    if (btn100) { btn100.classList.remove('active'); }
  } else if (canvas) {
    canvas.style.transform = 'none';
    canvas.style.marginBottom = '0';
    canvas.style.marginRight = '0';
    wrap.style.overflowX = 'auto';
    if (btn100) { btn100.classList.add('active'); }
    if (btnFit) { btnFit.classList.remove('active'); }
  }
};

window.addEventListener('resize', () => {
  const btnFit = document.getElementById('btn-map-fit');
  if (btnFit && btnFit.classList.contains('active')) {
    toggleMapFit(true);
  }
});
</script>
@endpush
@endsection
