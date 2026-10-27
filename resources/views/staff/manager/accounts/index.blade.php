<section class="manager-page-heading">
  <p class="eyebrow">Manager controls</p>
  <div class="manager-heading-row">
    <div><h1>Accounts and roles</h1><p class="manager-page-lede">Create staff accounts, assign roles, and control who can sign in.</p></div>
    <a class="btn sm" href="{{ route('manager.accounts.create') }}">Create staff account</a>
  </div>
</section>

<nav class="manager-tabs" aria-label="Account type">
  <a class="active" aria-current="page" href="{{ route('manager.accounts.index') }}">Staff accounts</a>
  <a href="{{ route('manager.drivers.index') }}">Driver accounts</a>
</nav>

<form class="manager-filter-panel" method="GET" action="{{ route('manager.accounts.index') }}" data-auto-filter>
  <div class="manager-filter-field"><label class="f" for="account-role">Role</label><select id="account-role" name="role"><option value="">All roles</option>@foreach ($roles as $value => $label)<option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
  <div class="manager-filter-field"><label class="f" for="account-status">Status</label><select id="account-status" name="status"><option value="">All statuses</option>@foreach (['active','inactive'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
  <div class="manager-filter-field manager-search-field"><label class="f" for="account-q">Search</label><input id="account-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or username" maxlength="100"></div>
  @if (array_filter($filters))
    <a class="manager-text-link" href="{{ route('manager.accounts.index') }}">Clear</a>
  @endif
</form>

<div class="manager-table-wrap">
  <table class="manager-table">
    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign in</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($accounts as $account)
        <tr>
          <th scope="row">{{ $account->name }}</th><td>{{ $account->username }}</td><td>{{ $roles[$account->role] }}</td>
          <td><span class="badge {{ $account->status === 'active' ? 'b-ok' : 'b-done' }}">{{ strtoupper($account->status) }}</span></td>
          <td>{{ \App\Support\Lot::local($account->last_login_at)?->format('M j, g:i A') ?? 'Never' }}</td>
          <td><div class="manager-row-actions">
            @if ($account->status !== 'inactive')
              <button type="button" data-open-dialog="account-edit-{{ $account->id }}">Edit</button>
            @endif
            <button type="button" data-open-dialog="account-delete-{{ $account->id }}">Delete</button>
            <button type="button" data-open-dialog="account-reset-{{ $account->id }}">Reset password</button>
            @if ($account->status === 'active')
              <button type="button" data-open-dialog="account-deactivate-{{ $account->id }}">Deactivate</button>
            @else
              <form method="POST" action="{{ route('manager.accounts.status', $account) }}">@csrf @method('PATCH')<button type="submit">Reactivate</button></form>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="6" class="manager-table-empty">No staff accounts match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@foreach ($accounts as $account)
  @if ($account->status !== 'inactive')
    <dialog class="manager-dialog" id="account-edit-{{ $account->id }}" aria-labelledby="account-edit-title-{{ $account->id }}" @if (session('openAccountModal') === "edit-{$account->id}") data-open-on-load @endif>
      <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.edit.confirm', $account) }}">
        @csrf
        <h2 id="account-edit-title-{{ $account->id }}">Edit account?</h2>
        <p class="manager-muted">Confirm your manager password to edit {{ $account->name }}'s account.</p>
        <div class="manager-dialog-field"><label class="f" for="edit-manager-password-{{ $account->id }}">Manager password</label><input id="edit-manager-password-{{ $account->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
        @if (session('openAccountModal') === "edit-{$account->id}") @error('manager_password')<p class="err">{{ $message }}</p>@enderror @endif
        <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">Continue to edit</button></div>
      </form>
    </dialog>
  @endif
  <dialog class="manager-dialog" id="account-delete-{{ $account->id }}" aria-labelledby="account-delete-title-{{ $account->id }}" @if (session('openAccountModal') === "delete-{$account->id}") data-open-on-load @endif>
    <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.destroy', $account) }}">
      @csrf @method('DELETE')
      <h2 id="account-delete-title-{{ $account->id }}">Delete account?</h2>
      <p class="manager-muted">This permanently deletes {{ $account->name }}'s staff account.</p>
      <div class="manager-dialog-field"><label class="f" for="delete-manager-password-{{ $account->id }}">Manager password</label><input id="delete-manager-password-{{ $account->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
      @if (session('openAccountModal') === "delete-{$account->id}") @error('manager_password')<p class="err">{{ $message }}</p>@enderror @endif
      <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">Delete account</button></div>
    </form>
  </dialog>
  @if ($account->status === 'active')
    <dialog class="manager-dialog" id="account-deactivate-{{ $account->id }}" aria-labelledby="account-deactivate-title-{{ $account->id }}" @if (session('openAccountModal') === "deactivate-{$account->id}") data-open-on-load @endif>
      <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.status', $account) }}">
        @csrf @method('PATCH')
        <h2 id="account-deactivate-title-{{ $account->id }}">Deactivate account?</h2>
        <p class="manager-muted">This prevents {{ $account->name }} from signing in until the account is reactivated.</p>
        <div class="manager-dialog-field"><label class="f" for="deactivate-manager-password-{{ $account->id }}">Manager password</label><input id="deactivate-manager-password-{{ $account->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
        @if (session('openAccountModal') === "deactivate-{$account->id}") @error('manager_password')<p class="err">{{ $message }}</p>@enderror @endif
        <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">Deactivate account</button></div>
      </form>
    </dialog>
  @endif
  <dialog class="manager-dialog" id="account-reset-{{ $account->id }}" aria-labelledby="account-reset-title-{{ $account->id }}" @if (session('openAccountModal') === "reset-{$account->id}") data-open-on-load @endif>
    <form class="manager-dialog-inner" method="POST" action="{{ route('manager.accounts.reset-password', $account) }}">
      @csrf
      <h2 id="account-reset-title-{{ $account->id }}">Reset password?</h2>
      <p class="manager-muted">A new temporary password will be generated for {{ $account->name }}. Their current password will stop working.</p>
      <div class="manager-dialog-field"><label class="f" for="reset-manager-password-{{ $account->id }}">Manager password</label><input id="reset-manager-password-{{ $account->id }}" name="manager_password" type="password" autocomplete="current-password" required></div>
      @if (session('openAccountModal') === "reset-{$account->id}") @error('manager_password')<p class="err">{{ $message }}</p>@enderror @endif
      <div class="manager-dialog-actions"><button class="btn ghost sm" type="button" data-close-dialog>Cancel</button><button class="btn sm" type="submit">Reset password</button></div>
    </form>
  </dialog>
@endforeach
<div class="manager-table-footer"><span>Showing {{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>
<p class="manager-hint-panel">New accounts and password resets receive a one-time temporary password and must change it before using staff tools.</p>
