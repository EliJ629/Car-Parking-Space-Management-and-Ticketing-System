{{-- The inside of the notifications box. Loaded into the dialog each time the bell is tapped. --}}
@if ($notices->isEmpty())
  <p class="sub" style="margin:8px 0 0">No notifications yet. Reservation updates, payment receipts and parking notices appear here.</p>
@else
  <ul class="nbox">
    @foreach ($notices as $n)
      <li class="{{ $n->isUnread() ? 'unread' : '' }}">
        <div class="top"><b>{{ $n->title }}</b>@if ($n->isUnread())<span class="dot" aria-label="Unread"></span>@endif</div>
        <p>{{ $n->body }}</p>
        <div class="foot">
          <small>{{ \App\Support\Lot::stamp($n->created_at) }}</small>
          <a class="lnk" href="{{ route('driver.notices.show', $n) }}">Open</a>
        </div>
      </li>
    @endforeach
  </ul>
@endif
<div class="actions two" style="margin-top:14px">
  @if ($unread > 0)<button class="btn ghost" type="button" data-notices-read>Mark all {{ $unread }} as read</button>@endif
  <a class="btn ghost" href="{{ route('driver.notices') }}">See all</a>
</div>
