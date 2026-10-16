{{-- Top bar for signed in pages. $logout is the route name for the sign out form. --}}
<header class="wsbar"><div class="w">
  <a class="brand" href="{{ $home }}">
    <span class="logo">@include('partials.logo')</span>
    <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
  </a>
  <span class="sp"></span>
  <span class="who">Signed in as <b>{{ auth()->user()->name }}</b></span>
  <button class="tt" id="theme" type="button" aria-label="Switch to dark mode" title="Switch to dark mode"><svg class="ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><circle cx="16.6" cy="7.4" r="1.5"/><path d="M19.5 7.4L20.5 7.4M18.7 9.5L19.4 10.2M16.6 10.3L16.6 11.3M14.5 9.5L13.8 10.2M13.7 7.4L12.7 7.4M14.5 5.3L13.8 4.6M16.6 4.5L16.6 3.5M18.7 5.3L19.4 4.6"/></svg></button>
  <form method="POST" action="{{ route($logout) }}" style="margin:0">
    @csrf
    <button class="btn ghost sm" type="submit">Sign out</button>
  </form>
</div></header>
