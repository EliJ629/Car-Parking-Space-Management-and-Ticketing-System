<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends ManagerController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(User::STAFF_ROLES)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $search = $filters['q'] ?? null;
        $accounts = User::query()->whereIn('role', User::STAFF_ROLES)
            ->when($role, fn ($query, string $value) => $query->where('role', $value))
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->when($search, fn ($query, string $value) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$value}%")
                ->orWhere('username', 'like', "%{$value}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return $this->managerPage('Staff accounts', 'accounts', view('staff.manager.accounts.index', [
            'accounts' => $accounts,
            'filters' => $filters,
            'roles' => [
                User::ROLE_MANAGER => 'Parking manager',
                User::ROLE_ATTENDANT => 'Booth attendant',
                User::ROLE_GUARD => 'Security guard',
            ],
        ]));
    }

    public function drivers(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'suspended', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $drivers = User::query()->where('role', User::ROLE_DRIVER)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount('vehicles')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return $this->managerPage('Driver accounts', 'accounts', view('staff.manager.accounts.drivers', [
            'drivers' => $drivers,
            'filters' => $filters,
        ]));
    }

    public function showDriver(User $account): View
    {
        abort_unless($account->isDriver(), 404);

        return $this->managerPage("Driver {$account->name}", 'accounts', view('staff.manager.accounts.driver', [
            'driver' => $account->load('vehicles'),
        ]));
    }

    public function myAccount(Request $request): View
    {
        return $this->managerPage('My account', 'accounts', view('staff.manager.accounts.my-account', [
            'account' => $request->user(),
        ]));
    }

    public function updateMyAccount(Request $request): RedirectResponse
    {
        $account = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('users', 'username')->ignore($account->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id), 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
        ]);
        $before = $account->only(['name', 'username', 'email', 'phone']);
        $account->update($data);

        ActivityLog::record('manager_profile_updated', $account, $request, $account->username, 'manager', [
            'before' => $before,
            'after' => $account->only(array_keys($before)),
        ]);

        return redirect()->route('manager.my-account')->with('status', 'Your profile was updated.');
    }

    public function changeOwnPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ]);
        $account = $request->user();
        $account->forceFill(['password' => Hash::make($data['password'])])->save();
        ActivityLog::record('password_changed', $account, $request, $account->username, 'manager');

        return redirect()->route('manager.my-account')->with('status', 'Your password was changed.');
    }

    public function create(): View
    {
        return $this->managerPage('Create staff account', 'accounts', view('staff.manager.accounts.form', [
            'account' => null,
            'roles' => $this->staffRoles(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'role' => ['required', Rule::in([User::ROLE_MANAGER, User::ROLE_ATTENDANT, User::ROLE_GUARD])],
        ]);
        $temporaryPassword = Str::password(16);
        $account = User::create($data + [
            'password' => $temporaryPassword,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => true,
        ]);

        ActivityLog::record('staff_account_created', $request->user(), $request, $account->username, 'manager', [
            'after' => $account->only(['name', 'username', 'email', 'phone', 'role', 'status']),
        ]);

        return redirect()->route('manager.accounts.index')
            ->with('status', "Account for {$account->name} was created. Share the temporary password once.")
            ->with('temporaryPassword', $temporaryPassword);
    }

    public function edit(User $account): View
    {
        abort_unless($account->isStaff(), 404);

        return $this->managerPage("Edit {$account->name}", 'accounts', view('staff.manager.accounts.form', [
            'account' => $account,
            'roles' => $this->staffRoles(),
        ]));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isStaff(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('users', 'username')->ignore($account->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id), 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'role' => ['required', Rule::in([User::ROLE_MANAGER, User::ROLE_ATTENDANT, User::ROLE_GUARD])],
        ]);

        if ($account->is($request->user()) && $data['role'] !== User::ROLE_MANAGER) {
            return back()->withErrors(['role' => 'You cannot remove your own manager access.'])->withInput();
        }

        $before = $account->only(['name', 'username', 'email', 'phone', 'role', 'status']);
        $account->update($data);
        ActivityLog::record('staff_account_updated', $request->user(), $request, $account->username, 'manager', [
            'before' => $before,
            'after' => $account->only(array_keys($before)),
        ]);

        return redirect()->route('manager.accounts.index')->with('status', "{$account->name}'s account was updated.");
    }

    public function toggleStatus(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isStaff() || $account->isDriver(), 404);

        if ($account->is($request->user())) {
            return back()->withErrors(['account' => 'You cannot deactivate your own account.']);
        }

        $newStatus = $account->status === User::STATUS_ACTIVE
            ? ($account->isDriver() ? 'suspended' : 'inactive')
            : User::STATUS_ACTIVE;

        if ($account->role === User::ROLE_MANAGER && $newStatus !== User::STATUS_ACTIVE
            && User::query()->where('role', User::ROLE_MANAGER)->where('status', User::STATUS_ACTIVE)->count() <= 1) {
            return back()->withErrors(['account' => 'At least one active manager account must remain.']);
        }

        $before = $account->only(['status']);
        $account->update(['status' => $newStatus]);
        ActivityLog::record('account_status_changed', $request->user(), $request, $account->username ?? $account->email, 'manager', [
            'before' => $before,
            'after' => ['status' => $newStatus],
        ]);

        return back()->with('status', "{$account->name}'s account is now {$newStatus}.");
    }

    public function resetPassword(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isStaff(), 404);
        $temporaryPassword = Str::password(16);
        $account->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        ActivityLog::record('staff_password_reset', $request->user(), $request, $account->username, 'manager');

        return back()->with('status', "A temporary password was created for {$account->name}.")
            ->with('temporaryPassword', $temporaryPassword);
    }

    /**
     * @return array<string, string>
     */
    private function staffRoles(): array
    {
        return [
            User::ROLE_MANAGER => 'Parking manager',
            User::ROLE_ATTENDANT => 'Booth attendant',
            User::ROLE_GUARD => 'Security guard',
        ];
    }
}
