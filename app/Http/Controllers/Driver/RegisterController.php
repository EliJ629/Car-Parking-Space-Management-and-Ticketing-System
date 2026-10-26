<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmRegistrationMail;
use App\Models\ActivityLog;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EmailVerification;
use App\Services\SignInService;
use App\Support\Lot;
use App\Support\MailFailure;
use App\Support\PlateEntry;
use App\Support\Rules;
use App\Support\VehicleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public const SESSION_KEY = 'pending_registration_id';

    public function create(): View
    {
        return view('driver.register', [
            'vehicleTypes' => Vehicle::DRIVER_TYPES,
            'catalog' => VehicleCatalog::forForm(),
            'colors' => VehicleCatalog::colors(),
            'form' => VehicleCatalog::formValues(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rules::joinNameFields($request);

        $request->merge([
            'email' => Rules::email($request->input('email')),
            'phone' => Lot::normalizePhone($request->input('phone')),
        ]);

        // "Still in registration" ticked: the plate is optional, and a plate that is typed must be a temporary plate.
        // Unticked: the permanent plate is required. Either way the plate belongs to one account only.
        $entry = PlateEntry::resolve($request, null, [
            fn (string $attribute, mixed $value, \Closure $fail) => Vehicle::plateTaken((string) $value)
                ? $fail(Lot::plate((string) $value).' is already registered to an account. A plate can belong to one account only.')
                : null,
        ]);

        $validate = fn () => VehicleCatalog::validate($request, Rules::nameParts() + [
            'email' => Rules::driverEmail(),
            'phone' => Rules::mobile(),
            'plate_number' => $entry->rules,
            'details_confirmed' => ['accepted'],
            'password' => Rules::driverPassword(),
        ], Rules::namePartMessages() + Rules::driverEmailMessages() + Rules::driverPasswordMessages() + [
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
            'plate_number.required' => 'Enter your plate number, or tick "still in registration".',
            'plate_number.regex' => Rules::PLATE_MESSAGE,
            'details_confirmed.accepted' => 'Check your details and confirm that they are all correct.',
        ]);

        // An account that never confirmed its email lets go of its name, Gmail address and plate after a day. That clean-up
        // costs database round trips, so it runs only when one of those three was refused (it may have been held by a stale
        // account) and the form is checked again once. A sign up with a mistake elsewhere does not pay for it.
        try {
            $data = $validate();
        } catch (ValidationException $e) {
            $heldByAnother = collect($e->errors())->flatten()->contains(fn ($message) => str_contains((string) $message, 'already'));

            if (! $heldByAnother) {
                throw $e;
            }

            $this->forgetStaleAccounts();
            $data = $validate();
        }

        $data['plate_number'] = $entry->plate;
        $data['plate_is_temporary'] = $entry->temporary;
        unset($data['details_confirmed']);

        // Nothing goes into users or vehicles yet. The details wait here until the link in the email is clicked.
        $pending = DB::transaction(function () use ($data, &$token) {
            PendingRegistration::query()->where('email_key', Rules::emailKey($data['email']))->delete();

            return $this->makePending($data, $token);
        });

        try {
            $this->sendLink($pending, $token);
        } catch (Throwable $e) {
            report($e);
            $pending->delete();

            throw ValidationException::withMessages([
                'email' => 'We could not send a link to '.$data['email'].'. Check that the Gmail address is spelled right and exists, then try again.'.MailFailure::screenDetail($e),
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $pending->id);

        return redirect()->route('driver.register.sent')->with('status', 'We sent a link to '.EmailVerification::mask($pending->email).'.');
    }

    /** The "check your email" page. */
    public function sent(Request $request): View|RedirectResponse
    {
        $pending = $this->current($request);

        if (! $pending) {
            return redirect()->route('driver.register');
        }

        return view('driver.register-sent', [
            'maskedEmail' => EmailVerification::mask($pending->email),
            'wait' => $pending->secondsUntilResend(),
            'hours' => PendingRegistration::HOURS,
            'logHint' => config('mail.default') === 'log' && ! app()->isProduction(),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->current($request);

        if (! $pending) {
            return redirect()->route('driver.register');
        }

        $wait = $pending->secondsUntilResend();

        if ($wait > 0) {
            return back()->withErrors(['link' => "Wait {$wait} seconds before asking for another link."]);
        }

        $token = Str::random(64);
        $pending->forceFill(['token_hash' => PendingRegistration::hashToken($token), 'expires_at' => now()->addHours(PendingRegistration::HOURS)])->save();

        try {
            $this->sendLink($pending, $token);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['link' => 'We could not send the email. Check the Gmail address and try again in a moment.'.MailFailure::screenDetail($e)]);
        }

        return back()->with('status', 'A new link was sent to '.EmailVerification::mask($pending->email).'. The older link no longer works.');
    }

    /** The email was typed wrong. Drop the waiting sign up so the person can fill the form again. */
    public function restart(Request $request): RedirectResponse
    {
        $this->current($request)?->delete();
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('driver.register')->with('status', 'Start again with the right Gmail address.');
    }

    /** The link in the email. Only now are the account and the vehicle saved. */
    public function confirm(Request $request, string $token): RedirectResponse
    {
        $user = null;
        $problem = null;
        $pending = null;

        DB::transaction(function () use ($token, &$user, &$problem, &$pending) {
            $pending = PendingRegistration::query()
                ->where('token_hash', PendingRegistration::hashToken($token))
                ->lockForUpdate()
                ->first();

            if (! $pending || $pending->expires_at->isPast()) {
                $pending?->delete();
                $pending = null;

                return;
            }

            // Anything can have changed in the hours since the form was sent. The first person to confirm keeps the name, mailbox and plate.
            if (Rules::emailTaken($pending->email)) {
                $problem = ['email', 'This Gmail account is already registered. Sign in instead.'];
            } elseif (Rules::driverNameTaken($pending->name)) {
                $problem = ['name', 'An account with this name already exists. If it is yours, sign in instead.'];
            } elseif ($pending->plate_number !== null && Vehicle::withTrashed()->where('plate_number', $pending->plate_number)->lockForUpdate()->exists()) {
                $problem = ['plate_number', Lot::plate($pending->plate_number).' is already registered to an account. A plate can belong to one account only.'];
            }

            if ($problem) {
                return;
            }

            $user = new User;
            $user->forceFill([
                'name' => $pending->name,
                'email' => $pending->email,
                'phone' => $pending->phone,
                'password' => $pending->password,
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ])->save();

            $user->vehicles()->create([
                'plate_number' => $pending->plate_number,
                'plate_is_temporary' => $pending->plate_number !== null && (bool) $pending->plate_is_temporary,
                'vehicle_type' => $pending->vehicle_type,
                'color' => $pending->color,
                'make_model' => $pending->make_model,
                'contact_number' => $pending->phone,
            ]);

            $pending->delete();
        });

        if (! $pending) {
            return redirect()->route('driver.login')
                ->with('status', 'This link was already used or has expired. If you already confirmed, sign in. Otherwise sign up again.');
        }

        if ($problem) {
            $input = $pending->only(['name', 'email', 'phone', 'plate_number', 'vehicle_type', 'color', 'make_model']) + Rules::splitName($pending->name);
            $pending->delete();

            if ($problem[0] === 'email') {
                return redirect()->route('driver.login')->with('status', $problem[1]);
            }

            return redirect()->route('driver.register')->withInput($input)->withErrors([$problem[0] => $problem[1]]);
        }

        $request->session()->forget(self::SESSION_KEY);
        ActivityLog::record('driver_registered', $user, $request, null, SignInService::AREA_DRIVER);
        ActivityLog::record('driver_email_verified', $user, $request, null, SignInService::AREA_DRIVER);

        return redirect()->route('driver.login')->with('status', 'Email confirmed. Your account is ready. Sign in to continue.');
    }

    /** @param array<string, mixed> $data */
    private function makePending(array $data, ?string &$token): PendingRegistration
    {
        $token = Str::random(64);

        return PendingRegistration::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'email_key' => Rules::emailKey($data['email']),
            'phone' => $data['phone'],
            'plate_number' => $data['plate_number'] ?? null,
            'plate_is_temporary' => (bool) ($data['plate_is_temporary'] ?? false),
            'vehicle_type' => $data['vehicle_type'],
            'color' => $data['color'] ?? null,
            'make_model' => $data['make_model'] ?? null,
            'password' => $data['password'],
            'token_hash' => PendingRegistration::hashToken($token),
            'expires_at' => now()->addHours(PendingRegistration::HOURS),
        ]);
    }

    private function sendLink(PendingRegistration $pending, string $token): void
    {
        Mail::to($pending->email)->send(new ConfirmRegistrationMail(
            $pending->name,
            route('driver.register.confirm', ['token' => $token]),
            PendingRegistration::HOURS,
        ));

        $pending->forceFill(['sent_at' => now()])->save();
    }

    private function current(Request $request): ?PendingRegistration
    {
        $id = (int) $request->session()->get(self::SESSION_KEY, 0);

        return $id ? PendingRegistration::query()->where('expires_at', '>', now())->find($id) : null;
    }

    /** Live check while typing the email on the sign-up form. Says only whether the address is in use. */
    public function checkEmail(Request $request): JsonResponse
    {
        $email = Rules::email($request->query('email'));

        if ($email === '' || ! preg_match(Rules::GMAIL_PATTERN, $email)) {
            return response()->json(['taken' => false]);
        }

        return response()->json(['taken' => Rules::emailTaken($email)]);
    }

    /** An account that never confirmed its email holds a name, a Gmail address and a plate. After a day it lets them go. */
    private function forgetStaleAccounts(): void
    {
        PendingRegistration::query()->where('expires_at', '<', now())->delete();

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
