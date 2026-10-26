@extends('layouts.guard')

@section('title', 'New incident - Bayline')
@section('tab', 'incidents')

@section('content')
@php
    $val = fn ($k) => old($k, $prefill[$k] ?? '');
@endphp
<h1 class="g-title">New incident</h1>
<p class="g-sub">Record anything unusual in the lot. The Parking Manager sees the report right away.</p>

@if ($errors->any())
  <div class="g-banner stop" role="alert" style="margin-top:14px"><b>Check the form</b>{{ $errors->count() === 1 ? 'One thing needs fixing.' : $errors->count().' things need fixing.' }}</div>
@endif

<form class="g-form" method="POST" action="{{ route('guard.incidents.store') }}" enctype="multipart/form-data" data-busy data-busy-label="Submitting" novalidate style="margin-top:16px"
      data-incident-form data-check="{{ route('guard.incidents.check-plate') }}" data-rules='@json($requirements)'>
  @csrf
  <input type="hidden" name="session_id" value="{{ old('session_id', $prefill['session_id']) }}">
  <div class="g-two">
    <section class="g-card">
      <fieldset class="g-cats {{ $errors->has('category') ? 'bad' : '' }}">
        <legend>Category</legend>
        @foreach ($categories as $c)
          <label class="g-radio"><input type="radio" name="category" value="{{ $c }}" @checked($val('category') === $c) @if ($loop->first) required @endif><span>{{ \App\Models\Incident::guardLabel($c) }}</span></label>
        @endforeach
      </fieldset>
      @error('category')<p class="g-err" style="margin:-8px 0 12px">{{ $message }}</p>@enderror
      <label class="f" for="description">Description</label>
      <textarea id="description" name="description" maxlength="500" placeholder="Describe what you saw. Include the time and anything the Manager should know." @error('description') aria-invalid="true" @enderror>{{ $val('description') }}</textarea>
      @error('description')<p class="g-err">{{ $message }}</p>@else<p class="hint">At least 10 characters.</p>@enderror
    </section>

    <section class="g-card">
      <p class="g-lab" style="margin-bottom:10px">Vehicle and place</p>
      <div class="fld">
        <label class="f" for="plate_number">Plate <span data-need-plate>(choose a category first)</span></label>
        <input id="plate_number" name="plate_number" type="text" value="{{ $val('plate_number') }}" placeholder="For example ABC 1234" autocapitalize="characters" autocomplete="off" spellcheck="false" aria-describedby="plate-live" @error('plate_number') aria-invalid="true" @enderror>
        <p class="hint" id="plate-live" aria-live="polite">Only a vehicle that is parked in the lot now can be reported.</p>
        @error('plate_number')<p class="g-err">{{ $message }}</p>@enderror
      </div>
      <div class="fld">
        <label class="f" for="location">Slot <span data-need-place>(choose a category first)</span></label>
        <input id="location" name="location" type="text" value="{{ $val('location') }}" placeholder="For example B-03" maxlength="120" list="slot-codes" autocapitalize="characters" autocomplete="off" @error('location') aria-invalid="true" @enderror>
        <datalist id="slot-codes">@foreach ($slotCodes as $code)<option value="{{ $code }}">@endforeach</datalist>
        @error('location')<p class="g-err">{{ $message }}</p>@else<p class="hint" id="place-hint"></p>@enderror
      </div>
      <p class="g-lab" style="margin-top:4px">Photos (optional, up to 3)</p>
      <div class="g-photos">
        @foreach ([1, 2, 3] as $n)
          <label class="g-photo"><input type="file" name="photos[]" accept="image/jpeg,image/png" capture="environment" aria-label="Add photo {{ $n }}"><b>+</b>Add photo</label>
        @endforeach
      </div>
      <p class="hint" id="photo-count">0 of 3 added. JPG or PNG.</p>
      @if ($errors->has('photos') || $errors->has('photos.*'))<p class="g-err">{{ $errors->first('photos') ?: $errors->first('photos.*') }}</p>@endif
      <div class="g-actions">
        <button class="btn" type="submit">Submit report</button>
        <button class="btn ghost" type="button" data-open="#discard">Cancel</button>
      </div>
    </section>
  </div>
</form>

@push('dialogs')
<dialog class="gdlg" id="discard" aria-labelledby="dc-t">
  <div class="in">
    <h2 id="dc-t">Discard this report?</h2>
    <p>What you wrote will not be saved.</p>
    <div class="actions"><a class="btn" href="{{ route('guard.incidents') }}">Discard</a><button class="btn ghost" type="button" data-close>Keep writing</button></div>
  </div>
</dialog>
@endpush
@endsection
