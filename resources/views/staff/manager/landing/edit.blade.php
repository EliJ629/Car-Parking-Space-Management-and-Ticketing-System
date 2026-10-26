@php
    $err = fn ($k) => $errors->first($k);
    $order = collect(old('sections', collect($config['sections'])->map(fn ($s, $i) => $s + ['position' => $i + 1])->all()))->values();
@endphp
<section class="manager-page-heading"><p class="eyebrow">Public pages</p><div class="manager-heading-row"><div><h1>Landing page</h1><p class="manager-page-lede">Change the wording, the order of the sections and the layout of the &ldquo;Find my car&rdquo; lookup. Fees, grace period and booking days always come from your Rates and Settings, so they cannot go out of date here.</p></div><a class="btn ghost sm" href="{{ route('driver.landing') }}" target="_blank" rel="noopener">View the page</a></div></section>
<section class="manager-form-panel"><form method="POST" action="{{ route('manager.landing.update') }}" novalidate>@csrf @method('PUT')
  <h2 class="manager-form-title">Top of the page</h2>
  <div class="manager-form-grid">
    <div class="manager-field"><label class="f" for="hero_eyebrow">Small line above the headline</label><input id="hero_eyebrow" name="hero_eyebrow" maxlength="{{ $limits['hero_eyebrow'] }}" value="{{ old('hero_eyebrow', $config['hero_eyebrow']) }}" required>@error('hero_eyebrow')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="f" for="hero_title">Headline</label><input id="hero_title" name="hero_title" maxlength="{{ $limits['hero_title'] }}" value="{{ old('hero_title', $config['hero_title']) }}" required aria-describedby="hero-title-hint"><p class="hint" id="hero-title-hint">Put *stars* around one word to emphasise it.</p>@error('hero_title')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field manager-field-wide"><label class="f" for="hero_lede">Text under the headline</label><textarea id="hero_lede" name="hero_lede" rows="3" maxlength="{{ $limits['hero_lede'] }}" required>{{ old('hero_lede', $config['hero_lede']) }}</textarea>@error('hero_lede')<p class="err">{{ $message }}</p>@enderror</div>
  </div>

  <h2 class="manager-subheading">Sections: show and order</h2>
  <p class="hint" style="margin:0 0 10px">Tick the sections to show and give each one its own place (1 is first).</p>
  <div class="manager-form-grid">
    @foreach ($order as $i => $row)
      <div class="manager-field">
        <input type="hidden" name="sections[{{ $i }}][key]" value="{{ $row['key'] }}">
        <label class="f" for="sec-pos-{{ $i }}">{{ $sections[$row['key']] }}</label>
        <div style="display:flex;gap:12px;align-items:center">
          <select id="sec-pos-{{ $i }}" name="sections[{{ $i }}][position]" aria-label="Place of {{ $sections[$row['key']] }}">@foreach (range(1, count($sections)) as $n)<option value="{{ $n }}" @selected((int) ($row['position'] ?? $loop->parent->iteration) === $n)>{{ $n }}</option>@endforeach</select>
          <label class="ck"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" @checked(! empty($row['enabled']))> Show</label>
        </div>
        @error("sections.{$i}.position")<p class="err">{{ $message }}</p>@enderror
      </div>
    @endforeach
  </div>
  @error('sections')<p class="err">{{ $message }}</p>@enderror

  <h2 class="manager-subheading">&ldquo;Find my car&rdquo; lookup</h2>
  <p class="hint" style="margin:0 0 10px">This is one block. The layout you choose shows on the driver sign in page and in the Find my car section of the landing page.</p>
  <div class="manager-form-grid">
    <div class="manager-field"><label class="f" for="lookup_layout">Layout</label><select id="lookup_layout" name="lookup_layout" required>@foreach ($layouts as $value => $label)<option value="{{ $value }}" @selected(old('lookup_layout', $config['lookup']['layout']) === $value)>{{ $label }}</option>@endforeach</select>@error('lookup_layout')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field"><label class="ck" style="margin-top:28px"><input type="checkbox" name="lookup_on_login" value="1" @checked(old('lookup_on_login', $errors->any() ? null : $config['lookup']['on_login']))> Show the lookup below the sign in form</label></div>
    <div class="manager-field"><label class="f" for="lookup_heading">Heading</label><input id="lookup_heading" name="lookup_heading" maxlength="{{ $limits['lookup_heading'] }}" value="{{ old('lookup_heading', $config['lookup']['heading']) }}" required>@error('lookup_heading')<p class="err">{{ $message }}</p>@enderror</div>
    <div class="manager-field manager-field-wide"><label class="f" for="lookup_intro">Text under the heading (not shown in the compact layout)</label><textarea id="lookup_intro" name="lookup_intro" rows="2" maxlength="{{ $limits['lookup_intro'] }}" required>{{ old('lookup_intro', $config['lookup']['intro']) }}</textarea>@error('lookup_intro')<p class="err">{{ $message }}</p>@enderror</div>
  </div>

  <div class="manager-form-actions">
    <button class="btn ghost sm" type="submit" name="action" value="reset" formnovalidate>Reset to defaults</button>
    <button class="btn sm" type="submit">Save landing page</button>
  </div>
</form></section>
