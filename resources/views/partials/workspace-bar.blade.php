{{-- Top bar for signed in pages. $logout is the route name for the sign out form. --}}
<header class="wsbar"><div class="w">
  <a class="brand" href="{{ $home }}">
    <span class="logo">@include('partials.logo')</span>
    <span><b>Bayline</b><small>Every bay, every ticket.</small></span>
  </a>
  <span class="sp"></span>
  <span class="who">Signed in as <b>{{ auth()->user()->name }}</b></span>
  <button class="tt" id="theme" type="button">Dark mode</button>
  <form method="POST" action="{{ route($logout) }}" style="margin:0">
    @csrf
    <button class="btn ghost sm" type="submit">Sign out</button>
  </form>
</div></header>
