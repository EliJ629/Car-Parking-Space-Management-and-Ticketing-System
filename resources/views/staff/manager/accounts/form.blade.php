<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.accounts.index') }}">Staff accounts</a> / {{ $account ? 'Edit account' : 'Create account' }}</p>
  <h1>{{ $account ? 'Edit staff account' : 'Create staff account' }}</h1>
</section>
<section class="manager-form-panel">
  <form method="POST" action="{{ $account ? route('manager.accounts.update', $account) : route('manager.accounts.store') }}">
    @csrf @if ($account) @method('PATCH') @endif
    <div class="manager-form-grid">
      <div class="manager-field manager-field-wide"><label class="f" for="name">Full name</label><input id="name" name="name" value="{{ old('name', $account?->name) }}" maxlength="120" autocomplete="off" required>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="username">Username</label><input id="username" name="username" value="{{ old('username', $account?->username) }}" minlength="4" maxlength="40" pattern="[A-Za-z0-9]+" autocomplete="off" required><p class="hint">Letters and numbers only.</p>@error('username')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="role">Role</label><select id="role" name="role" required>@foreach ($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $account?->role ?? \App\Models\User::ROLE_ATTENDANT) === $value)>{{ $label }}</option>@endforeach</select>@error('role')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $account?->email) }}" maxlength="255" pattern="[^@\s]+@[^@\s]+\.[A-Za-z]{2,}" title="Enter a valid email with a domain, for example name@example.com." autocomplete="email">@error('email')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="phone">Mobile</label><input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $account?->phone) }}" maxlength="13" placeholder="09175550142" autocomplete="tel">@error('phone')<p class="err">{{ $message }}</p>@enderror</div>
      <p class="hint manager-field-wide">Provide an email or mobile number. Temporary credentials are generated automatically for new accounts.</p>
    </div>
    <div class="manager-form-actions"><a class="btn ghost sm" href="{{ route('manager.accounts.index') }}">Cancel</a><button class="btn sm" type="submit">{{ $account ? 'Save changes' : 'Create account' }}</button></div>
  </form>
</section>
