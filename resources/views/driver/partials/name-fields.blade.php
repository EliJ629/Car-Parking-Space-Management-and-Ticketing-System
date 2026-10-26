{{-- First, middle (optional) and last name in separate boxes. Letters only; the script drops anything else as you type.
     Optional: $parts (first_name, middle_name, last_name) to start with, for example from Google. --}}
@php $parts = $parts ?? ['first_name' => '', 'middle_name' => '', 'last_name' => '']; @endphp
<div class="fld">
  <label class="f" for="first_name">First name</label>
  <input id="first_name" name="first_name" type="text" data-person value="{{ old('first_name', $parts['first_name']) }}" maxlength="40" autocomplete="given-name" required @error('first_name') aria-invalid="true" @enderror>
  @error('first_name')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="fld">
  <label class="f" for="middle_name">Middle name <span class="opt">(optional)</span></label>
  <input id="middle_name" name="middle_name" type="text" data-person data-person-optional value="{{ old('middle_name', $parts['middle_name']) }}" maxlength="40" autocomplete="additional-name" @error('middle_name') aria-invalid="true" @enderror>
  @error('middle_name')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="fld">
  <label class="f" for="last_name">Last name</label>
  <input id="last_name" name="last_name" type="text" data-person value="{{ old('last_name', $parts['last_name']) }}" maxlength="40" autocomplete="family-name" required @error('last_name') aria-invalid="true" @enderror>
  @error('last_name')<p class="err">{{ $message }}</p>@enderror
  @error('name')<p class="err">{{ $message }}</p>@else<p class="hint">Letters only, in separate boxes. No numbers or special characters.</p>@enderror
</div>
