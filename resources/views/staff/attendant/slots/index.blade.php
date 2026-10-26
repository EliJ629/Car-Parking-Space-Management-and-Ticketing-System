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

<div style="display:grid;grid-template-columns:1.8fr 1fr;gap:24px;align-items:start;">
  
  <!-- Left Column: Lot Slots Board (BA-51) -->
  <div class="ba-card">
    <h2>Lot slots, live</h2>

    <!-- Zone Filter Tabs & Available Count -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin:12px 0 16px;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;gap:6px;flex-wrap:wrap;" id="zone-filter-tabs">
        <button type="button" class="btn sm primary zone-tab-btn" data-zone="all" style="padding:5px 12px;font-size:13px;">All Zones ({{ $slots->count() }})</button>
        @foreach ($slots->groupBy('zone') as $zoneName => $zoneSlots)
          <button type="button" class="btn sm ghost zone-tab-btn" data-zone="{{ $zoneName }}" style="padding:5px 12px;font-size:13px;">Zone {{ $zoneName }} ({{ $zoneSlots->count() }})</button>
        @endforeach
      </div>
      <span style="font-size:13px;color:var(--muted);font-weight:600;">
        {{ $counts['available'] }} of {{ $slots->count() }} available
      </span>
    </div>

    <!-- Grouped Slots by Zone -->
    @foreach ($slots->groupBy('zone') as $zoneName => $zoneSlots)
      <div class="ba-zone-section" data-zone="{{ $zoneName }}" style="margin-bottom:22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 10px;padding-bottom:6px;border-bottom:1.5px solid var(--line);">
          <b style="font-size:15px;color:var(--ink);">Zone {{ $zoneName }}</b>
          <span style="font-size:12px;color:var(--muted);font-weight:600;">{{ $zoneSlots->where('status', 'available')->count() }} of {{ $zoneSlots->count() }} available</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">
          @foreach ($zoneSlots as $slot)
            @php
                $curr = $slot->currentSession();
                $booking = $slot->nextOpenBooking();
            @endphp
            <div class="ba-slot {{ $slot->status }}"
                 style="min-height:100px;padding:12px 8px;cursor:pointer;"
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
              <span class="st">{{ $slot->statusLabel() }}</span>
              @if ($curr)
                <span class="plt">{{ \App\Support\Lot::plate($curr->plate_number) }}</span>
              @elseif ($booking)
                <span class="plt" style="color:#92400E;">{{ \App\Support\Lot::plate($booking->plate_number) }}</span>
              @elseif ($slot->out_of_service_reason)
                <span class="plt" style="font-size:10px;">{{ \Illuminate\Support\Str::limit($slot->out_of_service_reason, 15) }}</span>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <!-- Legend matching Driver App design -->
    <div class="leg ba-detail-box" style="margin-top:14px;gap:10px 20px;">
      <span><i></i>Available</span>
      <span><i class="r"></i>Reserved</span>
      <span><i class="o"></i>Occupied</span>
      <span><i class="p"></i>Pending exit</span>
      <span><i class="x"></i>Out of service</span>
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
    <button class="x" type="button" data-close aria-label="Close">X</button>
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
    <button class="x" type="button" data-close aria-label="Close">X</button>
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
    <button class="x" type="button" data-close aria-label="Close">&times;</button>
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
</script>
@endpush
@endsection
