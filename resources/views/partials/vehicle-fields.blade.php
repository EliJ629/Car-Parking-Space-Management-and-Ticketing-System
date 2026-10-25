{{-- Vehicle type, the model list that follows the type, and the color. "Others" opens a box to type in.
     Needs: $vehicleTypes, $catalog, $colors, $form (model, model_other, color, color_other), $type (selected type). --}}
<script type="application/json" id="vehicle-catalog">@json($catalog)</script>
<div class="fld">
  <label class="f" for="vehicle_type">Vehicle type</label>
  <select id="vehicle_type" name="vehicle_type" required @error('vehicle_type') aria-invalid="true" @enderror>
    <option value="">Choose a type</option>
    @foreach ($vehicleTypes as $t)
      <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
    @endforeach
  </select>
  @error('vehicle_type')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="fld">
  <label class="f" for="make_model">Vehicle model</label>
  <select id="make_model" name="make_model" data-selected="{{ old('make_model', $form['model']) }}" required @error('make_model') aria-invalid="true" @enderror>
    <option value="">Choose a vehicle type first</option>
  </select>
  @error('make_model')<p class="err">{{ $message }}</p>@else<p class="hint">The list follows the vehicle type.</p>@enderror
</div>
<div class="fld" id="model-other-wrap" hidden>
  <label class="f" for="make_model_other">Type your vehicle model</label>
  <input id="make_model_other" name="make_model_other" type="text" value="{{ old('make_model_other', $form['model_other']) }}" placeholder="Toyota Vios" maxlength="60" autocomplete="off" @error('make_model_other') aria-invalid="true" @enderror>
  @error('make_model_other')<p class="err">{{ $message }}</p>@else<p class="hint">Make and model, for example Toyota Vios.</p>@enderror
</div>
<div class="fld">
  <label class="f" for="color">Color</label>
  <select id="color" name="color" required @error('color') aria-invalid="true" @enderror>
    <option value="">Choose a color</option>
    @foreach ($colors as $c)
      <option value="{{ $c }}" @selected(old('color', $form['color']) === $c)>{{ $c }}</option>
    @endforeach
  </select>
  @error('color')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="fld" id="color-other-wrap" hidden>
  <label class="f" for="color_other">Type the color</label>
  <input id="color_other" name="color_other" type="text" value="{{ old('color_other', $form['color_other']) }}" placeholder="Maroon" maxlength="30" autocomplete="off" @error('color_other') aria-invalid="true" @enderror>
  @error('color_other')<p class="err">{{ $message }}</p>@enderror
</div>
