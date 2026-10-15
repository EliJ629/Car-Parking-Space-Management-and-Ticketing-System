@extends('layouts.app')

@section('title', 'My account - Bayline')

@section('body')
@include('partials.workspace-bar', ['home' => route('driver.home'), 'logout' => 'driver.logout'])

<main class="page"><div class="w">
  <p class="eyebrow">My account</p>
  <h1 style="margin-top:10px">Hi, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}.</h1>

  @if (session('status'))
    <div class="alert ok" role="status" style="margin-top:18px;max-width:720px"><b>{{ session('status') }}</b></div>
  @endif

  <div class="two" style="margin-top:28px">
    <div>
      <h2 style="font-size:32px">My cars</h2>
      @if ($vehicles->isEmpty())
        <p class="empty" style="margin-top:12px">No cars saved yet.</p>
      @else
        <ul class="vlist">
          @foreach ($vehicles as $vehicle)
            <li>
              <div class="plate"><small>{{ strtoupper($vehicle->vehicle_type) }}</small><b>{{ $vehicle->displayPlate() }}</b></div>
              <div>
                <p style="font-weight:700">{{ $vehicle->color }} {{ strtolower($vehicle->vehicle_type) }}</p>
                <p class="fine">{{ $vehicle->plate_number }}</p>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
    <div>
      <h2 style="font-size:32px">My reservations</h2>
      <p class="empty" style="margin-top:12px">Nothing reserved yet. Reservations open in the next release, and they are free.</p>
      <p style="margin-top:14px"><a class="lnk" href="{{ route('driver.landing') }}#find">Find my car</a></p>
    </div>
  </div>
</div></main>
@endsection
