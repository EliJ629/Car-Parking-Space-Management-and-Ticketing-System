<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Lot;
use App\Support\Rules;
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
        $data = $this->validatedAccount($request, $account, false);
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
            'current_password' => ['required', 'string', 'max:255', 'current_password'],
            'password' => [...Rules::staffPassword(), 'different:current_password'],
        ], Rules::staffPasswordMessages() + [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'Current password is incorrect.',
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
        $data = $this->validatedAccount($request, null, true);
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

        $data = $this->validatedAccount($request, $account, true);

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
     * Shared rules for a staff account or the manager's own profile. Writes the tidied values
     * (squished name, lowercase email, 09 mobile number) back to the request first.
     *
     * @return array<string, mixed>
     */
    private function validatedAccount(Request $request, ?User $ignore, bool $withRole): array
    {
        Rules::tidy($request, 'name');
        $request->merge([
            'username' => trim((string) $request->input('username')),
            'email' => ($email = Rules::email($request->input('email'))) !== '' ? $email : null,
            'phone' => ($phone = Lot::normalizePhone($request->input('phone'))) !== '' ? $phone : null,
        ]);

        $unique = function (string $column) use ($ignore) {
            return function (string $attribute, mixed $value, \Closure $fail) use ($column, $ignore): void {
                $taken = User::query()
                    ->whereRaw("lower({$column}) = ?", [mb_strtolower((string) $value)])
                    ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
                    ->exists();

                if ($taken) {
                    $fail($column === 'email' ? 'This email is already registered.' : 'This username is already taken.');
                }
            };
        };

        $rules = [
            'name' => Rules::personName(),
            'username' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9]+$/', $unique('username')],
            'email' => ['nullable', 'email:rfc', 'max:255', $unique('email'), 'required_without:phone'],
            'phone' => [...Rules::mobile(false), 'required_without:email'],
        ];

        if ($withRole) {
            $rules['role'] = ['required', Rule::in([User::ROLE_MANAGER, User::ROLE_ATTENDANT, User::ROLE_GUARD])];
        }

        return $request->validate($rules, Rules::personNameMessages() + [
            'username.required' => 'Enter a username.',
            'username.min' => 'The username needs at least 4 letters or numbers.',
            'username.max' => 'The username can have up to 40 characters.',
            'username.regex' => 'Use letters and numbers only in the username, with no spaces.',
            'email.email' => 'Enter a valid email, for example name@example.com.',
            'email.max' => 'The email can have up to 255 characters.',
            'email.required_without' => 'Enter an email or a mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
            'phone.required_without' => 'Enter a mobile number or an email.',
            'role.required' => 'Choose a role.',
            'role.in' => 'Choose a role from the list.',
        ]);
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
