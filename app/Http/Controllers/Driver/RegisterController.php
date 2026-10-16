<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SignInService;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('driver.register', ['vehicleTypes' => Vehicle::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rules::tidy($request, 'name', 'make_model');
        $request->merge([
            'email' => Rules::email($request->input('email')),
            'phone' => Lot::normalizePhone($request->input('phone')),
            'plate_number' => Vehicle::normalizePlate($request->input('plate_number')),
        ]);

        $data = $request->validate([
            'name' => Rules::personName(),
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => Rules::mobile(),
            'plate_number' => Rules::plate(),
            'vehicle_type' => ['required', Rule::in(Vehicle::TYPES)],
            'make_model' => Rules::makeModel(),
            'color' => ['required', Rule::in(Vehicle::COLORS)],
            'password' => Rules::driverPassword(),
        ], Rules::personNameMessages() + Rules::makeModelMessages() + Rules::driverPasswordMessages() + [
            'email.required' => 'Enter your email.',
            'email.email' => 'Enter a valid email, for example maria@example.com.',
            'email.max' => 'The email can have up to 255 characters.',
            'email.unique' => 'This email is already registered. Try signing in.',
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
            'plate_number.required' => 'Enter your plate number.',
            'plate_number.regex' => Rules::PLATE_MESSAGE,
            'vehicle_type.required' => 'Choose your vehicle type.',
            'vehicle_type.in' => 'Choose your vehicle type from the list.',
            'color.required' => 'Choose your vehicle color.',
            'color.in' => 'Choose a color from the list.',
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

        ActivityLog::record('driver_registered', $user, $request, null, SignInService::AREA_DRIVER);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('driver.home')->with('status', 'Your account is ready. Welcome to Bayline.');
    }
}
