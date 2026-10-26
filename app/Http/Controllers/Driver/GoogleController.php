<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GoogleSignIn;
use App\Services\GoogleSignInFailed;
use App\Services\SignInService;
use App\Support\Lot;
use App\Support\PlateEntry;
use App\Support\Rules;
use App\Support\VehicleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Continue to Google Sign-in". A driver who already has an account is signed in (and the Google id is linked).
 * A new driver finishes a short form with the details Google does not have: mobile number and vehicle.
 */
class GoogleController extends Controller
{
    /** The sign in attempts that are still open: state => time started. More than one can be open (two tabs, a double click, a page the browser loaded ahead). */
    private const STATE_KEY = 'google_oauth_states';

    private const STATES_KEPT = 5;

    private const STATE_MINUTES = 15;

    /** A second copy of the state, sent as its own SameSite=Lax cookie, in case the session cookie does not come back from Google. */
    private const STATE_COOKIE = 'bayline_google_state';

    private const PENDING_KEY = 'google_pending';

    /** Minutes a new driver has to finish the form after coming back from Google. */
    private const PENDING_MINUTES = 20;

    public function __construct(private GoogleSignIn $google, private SignInService $signIn) {}

    /** Step 1: send the person to Google. */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->google->configured()) {
            return $this->toLogin('Google Sign-in is not set up yet. Sign in with your email and password for now.');
        }

        $state = Str::random(40);
        $open = $this->openStates($request);
        $open[$state] = now()->timestamp;
        $open = array_slice($open, -self::STATES_KEPT, null, true);
        $request->session()->put(self::STATE_KEY, $open);

        return redirect()->away($this->google->authUrl($state))
            ->withCookie(cookie(self::STATE_COOKIE, implode(',', array_keys($open)), 15, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    /** @return array<string, int> the open attempts of this browser that are not too old */
    private function openStates(Request $request): array
    {
        $oldest = now()->subMinutes(self::STATE_MINUTES)->timestamp;

        return array_filter((array) $request->session()->get(self::STATE_KEY, []), fn ($time) => is_int($time) && $time >= $oldest);
    }

    /** Step 2: Google sends the person back here with a code. */
    public function callback(Request $request): RedirectResponse
    {
        $open = $this->openStates($request);
        $fromCookie = array_values(array_filter(explode(',', (string) $request->cookie(self::STATE_COOKIE, ''))));
        $given = (string) $request->query('state');

        if ($request->query('error') !== null) {
            return $this->toLogin('Google sign in was cancelled. You can try again or use your email and password.');
        }

        // Any attempt this browser started a moment ago is good. An older Google tab still works after a second click.
        $stateOk = $given !== '' && (
            array_key_exists($given, $open)
            || collect($fromCookie)->contains(fn (string $kept) => hash_equals($kept, $given))
        );

        if (! $stateOk) {
            // The values themselves are never logged, only enough to tell what went wrong.
            Log::warning('Google sign in: the state check failed', [
                'host' => $request->getHost(),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                'open_in_session' => count($open),
                'open_in_cookie' => count($fromCookie),
                'given_length' => strlen($given),
                'given_start' => substr($given, 0, 4),
                'session_starts' => array_map(fn ($k) => substr((string) $k, 0, 4), array_keys($open)),
                'query_keys' => array_keys($request->query()),
                'same_site' => config('session.same_site'),
            ]);

            return $this->toLogin('That sign in link expired. Please try again.');
        }

        // This attempt is used up. The others stay valid.
        unset($open[$given]);
        $request->session()->put(self::STATE_KEY, $open);
        Cookie::queue(Cookie::forget(self::STATE_COOKIE));

        $code = (string) $request->query('code');

        if ($code === '') {
            return $this->toLogin('Google did not send a sign in code. Please try again.');
        }

        try {
            $profile = $this->google->profile($code);
        } catch (GoogleSignInFailed $e) {
            return $this->toLogin($e->getMessage());
        }

        if (! $profile['verified']) {
            return $this->toLogin('Google says this email address is not confirmed. Confirm it with Google first.');
        }

        if (! preg_match('/@(gmail|googlemail)\.com$/', $profile['email'])) {
            return $this->toLogin('Bayline accepts Gmail accounts only. Choose a Gmail account.');
        }

        $user = User::query()->where('role', User::ROLE_DRIVER)->where('google_id', $profile['sub'])->first()
            ?? User::query()->where('role', User::ROLE_DRIVER)->where('email_key', Rules::emailKey($profile['email']))->first();

        if ($user) {
            return $this->signInExisting($request, $user, $profile);
        }

        // The mailbox belongs to a staff account or to a pending email change of someone else.
        if (Rules::emailTaken($profile['email'])) {
            return $this->toLogin('This Gmail account is already used by another account.');
        }

        $request->session()->put(self::PENDING_KEY, $profile + ['until' => now()->addMinutes(self::PENDING_MINUTES)->timestamp]);

        return redirect()->route('driver.google.complete');
    }

    /** Step 3 for a new driver: the form for the details Google does not have. */
    public function complete(Request $request): View|RedirectResponse
    {
        $profile = $this->pending($request);

        if (! $profile) {
            return $this->toLogin('That sign up took too long. Start again with Google.');
        }

        return view('driver.google-complete', [
            'email' => $profile['email'],
            'googleName' => $profile['name'],
            'googleParts' => Rules::splitName($profile['name']),
            'vehicleTypes' => Vehicle::DRIVER_TYPES,
            'catalog' => VehicleCatalog::forForm(),
            'colors' => VehicleCatalog::colors(),
            'form' => VehicleCatalog::formValues(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->pending($request);

        if (! $profile) {
            return $this->toLogin('That sign up took too long. Start again with Google.');
        }

        Rules::joinNameFields($request);

        $request->merge(['phone' => Lot::normalizePhone($request->input('phone'))]);

        // "Still in registration" ticked: the plate is optional, and a plate that is typed must be a temporary plate.
        $entry = PlateEntry::resolve($request, null, [
            fn (string $attribute, mixed $value, \Closure $fail) => Vehicle::plateTaken((string) $value)
                ? $fail(Lot::plate((string) $value).' is already registered to an account. A plate can belong to one account only.')
                : null,
        ]);

        $data = VehicleCatalog::validate($request, Rules::nameParts() + [
            'phone' => Rules::mobile(),
            'plate_number' => $entry->rules,
            'details_confirmed' => ['accepted'],
        ], Rules::namePartMessages() + [
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => Rules::MOBILE_MESSAGE,
            'plate_number.required' => 'Enter your plate number, or tick "still in registration".',
            'plate_number.regex' => Rules::PLATE_MESSAGE,
            'details_confirmed.accepted' => 'Check your details and confirm that they are all correct.',
        ]);

        $data['plate_number'] = $entry->plate;
        $data['plate_is_temporary'] = $entry->temporary;
        unset($data['details_confirmed']);

        $user = DB::transaction(function () use ($data, $profile) {
            if (Rules::emailTaken($profile['email'])) {
                throw ValidationException::withMessages(['name' => 'This Gmail account is already registered. Try signing in.']);
            }

            if ($data['plate_number'] !== null && Vehicle::withTrashed()->where('plate_number', $data['plate_number'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'plate_number' => Lot::plate($data['plate_number']).' is already registered to an account. A plate can belong to one account only.',
                ]);
            }

            $user = new User([
                'name' => $data['name'],
                'email' => $profile['email'],
                'phone' => $data['phone'],
                // There is no password to remember. "Forgot password" lets the driver set one later.
                'password' => Str::random(48),
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
            ]);
            $user->forceFill(['google_id' => $profile['sub'], 'email_verified_at' => now()])->save();

            $user->vehicles()->create([
                'plate_number' => $data['plate_number'] ?? null,
                'plate_is_temporary' => (bool) $data['plate_is_temporary'],
                'vehicle_type' => $data['vehicle_type'],
                'color' => $data['color'],
                'make_model' => $data['make_model'],
                'contact_number' => $data['phone'],
            ]);

            return $user;
        });

        $request->session()->forget(self::PENDING_KEY);
        ActivityLog::record('driver_registered', $user, $request, 'google', SignInService::AREA_DRIVER);
        $this->signIn->signInTrusted($request, $user);

        return redirect()->route('driver.home')->with('modal', [
            'title' => 'Welcome to Bayline',
            'body' => 'Your account is ready. You signed up with Google, so there is no password to remember.',
            'primary' => ['label' => 'Start', 'close' => true],
        ]);
    }

    /** The person changed their mind on the form. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_KEY);

        return redirect()->route('driver.login');
    }

    /** @param array{sub:string,email:string,verified:bool,name:string} $profile */
    private function signInExisting(Request $request, User $user, array $profile): RedirectResponse
    {
        if ($user->google_id !== null && $user->google_id !== $profile['sub']) {
            return $this->toLogin('This Bayline account is linked to a different Google account.');
        }

        // Google has just proved the person owns this mailbox, so a pending code is no longer needed.
        $user->forceFill([
            'google_id' => $profile['sub'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        try {
            $this->signIn->signInTrusted($request, $user);
        } catch (ValidationException $e) {
            return $this->toLogin(collect($e->errors())->flatten()->first());
        }

        return redirect()->route('driver.home');
    }

    private function toLogin(string $message): RedirectResponse
    {
        return redirect()->route('driver.login')->withErrors(['identity' => $message]);
    }

    /** @return array{sub:string,email:string,verified:bool,name:string,until:int}|null */
    private function pending(Request $request): ?array
    {
        $profile = $request->session()->get(self::PENDING_KEY);

        if (! is_array($profile) || ($profile['until'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $profile;
    }
}
