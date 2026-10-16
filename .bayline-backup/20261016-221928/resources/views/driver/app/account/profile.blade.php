@extends('layouts.driver-app')

@section('title', 'Edit profile - Bayline')
@section('tab', 'account')
@section('heading', 'Edit profile')
@section('back', route('driver.account'))

@section('content')
<form method="POST" action="{{ route('driver.account.profile.update') }}" data-busy data-busy-label="Saving" novalidate>
  @csrf @method('PUT')
  <div class="fld">
    <label class="f" for="name">Full name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
    @error('name')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" autocapitalize="none" required @error('email') aria-invalid="true" @enderror>
    @error('email')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="fld">
    <label class="f" for="phone">Mobile number</label>
    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', \App\Support\Lot::phone($user->phone)) }}" autocomplete="tel" required @error('phone') aria-invalid="true" @enderror>
    @error('phone')<p class="err">{{ $message }}</p>@enderror
  </div>
  <div class="actions"><button class="btn" type="submit">Save changes</button><a class="btn ghost" href="{{ route('driver.account') }}">Cancel</a></div>
</form>
@endsection
