<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Services\VerificationMailFailed;
use App\Support\Lot;
use App\Support\Rules;
use App\Support\VehicleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('driver.register', [
            'vehicleTypes' => Vehicle::DRIVER_TYPES,
            'catalog' => VehicleCatalog::forForm(),
            'colors' => VehicleCatalog::colors(),
            'form' => VehicleCatalog::formValues(null),
        ]);
    }

    public function store(Request $request, EmailVerification $verification): RedirectResponse
    {
        $this->forgetStaleAccounts();

        Rules::tidy($request, 'name');
        $request->merge([
            'email' => Rules::email($request->input('email')),
            'phone' => Lot::normalizePhone($request->input('phone')),
            'plate_number' => Vehicle::normalizePlate($request->input('plate_number')),
        ]);

        $data = VehicleCatalog::validate($request, [
            'name' => array_merge(Rules::personName(), [
                fn (string $attribute, mixed $value, \Closure $fail) => Rules::driverNameTaken((string) $value)
                    ? $fail('An account with this name already exists. If it is yours, sign in instead.')
                    : null,
            ]),
            'email' => Rules::driverEmail(),
            'phone' => Rules::mobile(),
            'plate_number' => array_merge(Rules::plate(), [
                fn (string $attribute, mixed $value, \Closure $fail) => Vehicle::withTrashed()->where('plate_number', $value)->exists()
                    ? $fail(Lot::plate((string) $value).' is already registered to an account. A plate can belong to one account only.')
                    : null,
            ]),
            'password' => Rules::driverPassword(),
        ], Rules::personNameMessages() + Rules::driverEmailMessages() + Rules::driverPasswordMessages() + [
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
            'plate_number.required' => 'Enter your plate number.',
            'plate_number.regex' => Rules::PLATE_MESSAGE,
        ]);

        $user = DB::transaction(function () use ($data) {
            // A plate belongs to one account. Removed vehicles still hold their plate.
            if (Vehicle::withTrashed()->where('plate_number', $data['plate_number'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'plate_number' => Lot::plate($data['plate_number']).' is already registered to an account. A plate can belong to one account only.',
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->vehicles()->create([
                'plate_number' => $data['plate_number'],
                'vehicle_type' => $data['vehicle_type'],
                'color' => $data['color'],
                'make_model' => $data['make_model'],
                'contact_number' => $data['phone'],
            ]);

            return $user;
        });

        try {
            $verification->issue($user);
        } catch (VerificationMailFailed) {
            // The mail service did not take the address. Nothing is kept, so the person can try again.
            Vehicle::withTrashed()->where('user_id', $user->id)->forceDelete();
            $user->delete();

            throw ValidationException::withMessages([
                'email' => 'We could not send a code to '.$data['email'].'. Check that the Gmail address is spelled right and exists, then try again.',
            ]);
        }

        ActivityLog::record('driver_registered', $user, $request, null, SignInService::AREA_DRIVER);
        $request->session()->put(VerifyEmailController::SESSION_KEY, $user->id);

        return redirect()->route('driver.verify')->with('status', 'We sent a 6 digit code to '.EmailVerification::mask($user->email).'.');
    }

    /** An account that never confirmed its email holds a name, a Gmail address and a plate. After a day it lets them go. */
    private function forgetStaleAccounts(): void
    {
        $stale = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDay())
            ->pluck('id');

        if ($stale->isEmpty()) {
            return;
        }

        Vehicle::withTrashed()->whereIn('user_id', $stale)->forceDelete();
        User::query()->whereIn('id', $stale)->delete();
    }
}
