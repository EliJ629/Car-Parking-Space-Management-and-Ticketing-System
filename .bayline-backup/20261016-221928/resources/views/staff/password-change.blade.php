@extends('layouts.app')

@section('title', 'Change password - Bayline')

@section('body')
@include('partials.workspace-bar', ['home' => route('staff.home'), 'logout' => 'staff.logout'])

<main class="page">
  <div class="w">
    <section class="manager-form-panel" style="max-width:520px;margin:24px auto">
      <p class="eyebrow">Account security</p>
      <h1 style="font-size:42px;margin-top:8px">Choose a new password</h1>
      <p class="manager-page-lede">Your temporary password must be replaced before you can continue.</p>
      @if ($errors->any())
        <div class="manager-feedback manager-feedback-error" role="alert">
          <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif
      <form method="POST" action="{{ route('staff.password.change.update') }}">
        @csrf
        @method('PUT')
        <div class="manager-form-grid">
          <div class="manager-field manager-field-wide">
            <label class="f" for="password">New password</label>
            <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
          </div>
          <div class="manager-field manager-field-wide">
            <label class="f" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
          </div>
        </div>
        <div class="manager-form-actions">
          <button class="btn sm" type="submit">Save password</button>
        </div>
      </form>
    </section>
  </div>
</main>
@endsection
