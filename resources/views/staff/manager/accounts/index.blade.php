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
  <div class="manager-filter-field"><label class="f" for="account-status">Status</label><select id="account-status" name="status"><option value="">All statuses</option>@foreach (['active','inactive','suspended'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
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
            <a href="{{ route('manager.accounts.edit', $account) }}">Edit</a>
            <form method="POST" action="{{ route('manager.accounts.reset-password', $account) }}">@csrf<button type="submit">Reset password</button></form>
            <form method="POST" action="{{ route('manager.accounts.status', $account) }}" onsubmit="return confirm('{{ $account->status === 'active' ? 'Deactivate' : 'Reactivate' }} this account?')">@csrf @method('PATCH')<button type="submit">{{ $account->status === 'active' ? 'Deactivate' : 'Reactivate' }}</button></form>
          </div></td>
        </tr>
      @empty
        <tr><td colspan="6" class="manager-table-empty">No staff accounts match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="manager-table-footer"><span>Showing {{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>
<p class="manager-hint-panel">New accounts and password resets receive a one-time temporary password and must change it before using staff tools.</p>
