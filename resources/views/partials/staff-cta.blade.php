{{-- $cls is the button class, for example "btn sm". --}}
@if (auth()->check() && auth()->user()->isStaff())
<a class="{{ $cls }}" href="{{ route('staff.home') }}">Open workspace</a>
@else
<a class="{{ $cls }}" href="{{ route('staff.login') }}">Staff sign in</a>
@endif
