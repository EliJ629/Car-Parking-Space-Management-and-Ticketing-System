{{-- $mode is nav, hero or close. --}}
@if (auth()->check() && auth()->user()->isDriver())
<a class="btn{{ $mode === 'nav' ? ' sm' : '' }}" href="{{ route('driver.home') }}">Open my account</a>
@elseif ($mode === 'nav')
<a class="btn ghost sm" href="{{ route('driver.login') }}">Sign in</a><a class="btn sm" href="{{ route('driver.register') }}">Create account</a>
@elseif ($mode === 'hero')
<a class="btn" href="{{ route('driver.register') }}">Create an account</a>@if (in_array('find', \App\Support\LandingConfig::visibleSections(), true))<a class="btn ghost" href="#find-section">Find my car</a>@endif
@else
<a class="btn" href="{{ route('driver.register') }}">Create an account</a><a class="btn ghost" href="{{ route('driver.login') }}">Sign in</a>
@endif
