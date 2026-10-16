@extends('layouts.guard')

@section('title', 'Report long-parked vehicle - Bayline')
@section('tab', 'incidents')

@section('content')
<h1 class="g-title">Report long-parked vehicle</h1>
<p class="g-sub">{{ $step === 1 ? 'Step 1 of 2. Enter the plate of the vehicle.' : 'Step 2 of 2. Check the details and describe what you saw.' }}</p>

@if ($step === 1)
  <form class="g-card g-form" method="GET" action="{{ route('guard.incidents.long-parked') }}" style="margin-top:16px;max-width:640px">
    <label class="f" for="plate">Plate number</label>
    <div class="g-search">
      <input id="plate" name="plate" type="text" value="{{ $plate }}" placeholder="For example QRS 3317" autocapitalize="characters" autocomplete="off" spellcheck="false" required autofocus>
      <button class="btn" type="submit" style="min-height:48px">Find vehicle</button>
    </div>
    <p class="hint">The plate must belong to a vehicle that is parked now.</p>
  </form>
  <p style="margin-top:14px"><a class="lnk" href="{{ route('guard.incidents') }}">Back to incidents</a></p>
@else
  @php $s = $session; @endphp
  <form class="g-form" method="POST" action="{{ route('guard.incidents.long-parked.store') }}" enctype="multipart/form-data" data-busy data-busy-label="Submitting" novalidate style="margin-top:16px">
    @csrf
    <input type="hidden" name="session_id" value="{{ $s->id }}">
    <div class="g-two">
      <section class="g-card">
        <p class="g-lab">Vehicle found</p>
        <div class="g-plate" style="margin-top:10px"><b>{{ \App\Support\Lot::plate($s->plate_number) }}</b> <span class="g-tag warn">{{ \App\Support\Lot::duration($minutes) }}</span></div>
        <dl class="g-kv cols">
          <dt>Ticket</dt><dd>{{ $s->ticket_number }}</dd>
          <dt>Slot</dt><dd>{{ $s->slot?->code ?? $s->slot_code }}</dd>
          <dt>Entry</dt><dd>{{ \App\Support\Lot::stamp($s->entered_at) }}</dd>
          <dt>Limit</dt><dd>{{ $limit }} h{{ $minutes >= $limit * 60 ? ', passed' : ', not passed yet' }}</dd>
        </dl>
        @if ($existing)<div class="g-banner" style="margin-top:14px"><b>{{ $existing->code }} already exists for this vehicle</b>Your report is added as a follow-up. <a href="{{ route('guard.incidents.show', $existing) }}">Open incident</a></div>@endif
        <div class="fld" style="margin-top:16px">
          <label class="f" for="description">What did you see?</label>
          <textarea id="description" name="description" maxlength="500" placeholder="For example: car is still in B-01, doors locked, no one nearby." @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
          @error('description')<p class="g-err">{{ $message }}</p>@else<p class="hint">At least 10 characters.</p>@enderror
        </div>
      </section>
      <section class="g-card">
        <p class="g-lab" style="margin-bottom:6px">Photos (optional, up to 3)</p>
        <div class="g-photos">
          @foreach ([1, 2, 3] as $n)
            <label class="g-photo"><input type="file" name="photos[]" accept="image/jpeg,image/png" capture="environment" aria-label="Add photo {{ $n }}"><b>+</b>Add photo</label>
          @endforeach
        </div>
        <p class="hint" id="photo-count">0 of 3 added. JPG or PNG.</p>
        @if ($errors->has('photos') || $errors->has('photos.*'))<p class="g-err">{{ $errors->first('photos') ?: $errors->first('photos.*') }}</p>@endif
        @unless ($existing)
          <label class="g-check" style="margin-top:14px"><input type="checkbox" name="notify_driver" value="1" checked><span>Notify the driver</span></label>
        @endunless
        <div class="g-actions">
          <button class="btn" type="submit">Submit report</button>
          <a class="btn ghost" href="{{ route('guard.incidents.long-parked') }}">Back</a>
        </div>
      </section>
    </div>
  </form>
@endif

@isset($modal)
  @if ($modal)@push('dialogs')@include('guard.partials.modal', ['modal' => $modal, 'id' => 'lp-modal', 'auto' => true])@endpush @endif
@endisset
@endsection
