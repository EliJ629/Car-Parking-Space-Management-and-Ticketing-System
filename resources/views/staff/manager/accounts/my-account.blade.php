<section class="manager-page-heading"><p class="eyebrow">Account preferences</p><h1>My account</h1><p class="manager-page-lede">Update your contact details or change your password.</p></section>
<section class="manager-form-panel">
  <h2 class="manager-form-title">Profile</h2>
  <form method="POST" action="{{ route('manager.my-account.update') }}">@csrf @method('PUT')
    <div class="manager-form-grid">
      <div class="manager-field manager-field-wide"><label class="f" for="name">Full name</label><input id="name" name="name" value="{{ old('name', $account->name) }}" required maxlength="120" autocomplete="name">@error('name')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="username">Username</label><input id="username" name="username" value="{{ old('username', $account->username) }}" required minlength="4" maxlength="40" pattern="[A-Za-z0-9]+" autocomplete="username">@error('username')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="email">Email</label><input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $account->email) }}" autocomplete="email">@error('email')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="phone">Mobile</label><input id="phone" name="phone" type="tel" inputmode="tel" maxlength="17" placeholder="0917 555 0142" value="{{ old('phone', $account->phone) }}" autocomplete="tel">@error('phone')<p class="err">{{ $message }}</p>@enderror</div>
      <p class="hint manager-field-wide">Keep at least one contact method on your account.</p>
    </div>
    <div class="manager-form-actions"><button class="btn sm" type="submit">Save profile</button></div>
  </form>

  <h2 class="manager-subheading">Change password</h2>
  <form method="POST" action="{{ route('manager.my-account.password') }}">@csrf @method('PUT')
    <div class="manager-form-grid">
      <div class="manager-field manager-field-wide"><label class="f" for="current_password">Current password</label><input id="current_password" name="current_password" type="password" maxlength="255" autocomplete="current-password" required>@error('current_password')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="manager-field"><label class="f" for="password">New password</label><input id="password" name="password" type="password" minlength="12" maxlength="64" autocomplete="new-password" required>@error('password')<p class="err">{{ $message }}</p>@else<p class="hint">At least 12 characters with a letter and a number.</p>@enderror</div>
      <div class="manager-field"><label class="f" for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="64" autocomplete="new-password" required></div>
    </div>
    <div class="manager-form-actions"><button class="btn sm" type="submit">Change password</button></div>
  </form>
</section>
