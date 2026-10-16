<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('driver.register', ['vehicleTypes' => Vehicle::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone' => $this->normalizePhone($request->input('phone')),
            'plate_number' => Vehicle::normalizePlate($request->input('plate_number')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'plate_number' => ['required', 'regex:/^[A-Z0-9]{5,8}$/'],
            'vehicle_type' => ['required', Rule::in(Vehicle::TYPES)],
            'color' => ['required', 'string', 'min:3', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'name.required' => 'Enter your full name.',
            'email.required' => 'Enter your email.',
            'email.email' => 'Enter a valid email, for example maria@example.com.',
            'email.unique' => 'An account with this email already exists. Try signing in.',
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => 'Enter a Philippine mobile number, for example 0917 123 4567.',
            'plate_number.required' => 'Enter your plate number.',
            'plate_number.regex' => 'Enter 5 to 8 letters and numbers, for example ABC 1234.',
            'vehicle_type.required' => 'Choose your vehicle type.',
            'vehicle_type.in' => 'Choose your vehicle type from the list.',
            'color.required' => 'Enter your vehicle colour.',
            'color.min' => 'Enter your vehicle colour, for example Black.',
            'password.required' => 'Choose a password.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->vehicles()->create([
                'plate_number' => $data['plate_number'],
                'vehicle_type' => $data['vehicle_type'],
                'color' => trim($data['color']),
            ]);

            return $user;
        });

        ActivityLog::record('driver_registered', $user, $request, null, SignInService::AREA_DRIVER);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('driver.home')->with('status', 'Your account is ready. Welcome to Bayline.');
    }

    /**
     * 0917 123 4567, 0917-123-4567 and +63 917 123 4567 all become 09171234567.
     */
    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/[\s()\-]+/', '', (string) $phone);

        if (preg_match('/^\+?63(9\d{9})$/', $phone, $matches)) {
            return '0'.$matches[1];
        }

        return $phone;
    }
}
