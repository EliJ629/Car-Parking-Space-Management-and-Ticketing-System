<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Forgot password for drivers, and the "email already has an account" rule at sign up. */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-New-Pass9!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
    }

    private function driver(array $override = []): User
    {
        return User::factory()->create($override + ['role' => User::ROLE_DRIVER, 'email' => 'maria.santos@gmail.com', 'password' => 'Old-Password-1!']);
    }

    private function ask(string $email)
    {
        return $this->from(route('driver.password.request'))->post(route('driver.password.email'), ['email' => $email]);
    }

    public function test_an_unknown_email_is_refused_and_nothing_is_sent(): void
    {
        $this->driver();

        $this->ask('nobody.here@gmail.com')
            ->assertRedirect(route('driver.password.request'))
            ->assertSessionHasErrors(['email' => 'We could not find an account with that email. Check the spelling, or create an account.']);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_bad_email_is_refused_before_any_lookup(): void
    {
        $this->ask('not-an-email')->assertSessionHasErrors('email');
        $this->ask('')->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_an_existing_email_gets_a_link_with_a_masked_confirmation(): void
    {
        $user = $this->driver();

        $this->ask('Maria.Santos@Gmail.com')
            ->assertRedirect(route('driver.password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Mail::assertSent(ResetPasswordMail::class, 1);
        Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $m) => $m->hasTo($user->email)
            && str_contains($m->link, '/driver/reset-password/') && str_contains($m->link, 'email='));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_a_dotted_variant_finds_the_account_and_mail_goes_to_the_stored_address(): void
    {
        $user = $this->driver(['email' => 'mariasantos@gmail.com']);

        $this->ask('maria.santos@gmail.com')->assertSessionHasNoErrors();

        Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $m) => $m->hasTo('mariasantos@gmail.com'));
    }

    public function test_a_staff_email_is_not_accepted(): void
    {
        User::factory()->create(['role' => User::ROLE_GUARD, 'email' => 'guard.one@gmail.com']);

        $this->ask('guard.one@gmail.com')->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_a_google_account_gets_no_link(): void
    {
        $user = $this->driver();
        $user->forceFill(['google_id' => 'g-1'])->save();

        $this->ask('maria.santos@gmail.com')->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_asking_twice_in_a_row_is_throttled(): void
    {
        $this->driver();

        $this->ask('maria.santos@gmail.com')->assertSessionHasNoErrors();
        $this->ask('maria.santos@gmail.com')->assertSessionHasErrors('email');

        Mail::assertSent(ResetPasswordMail::class, 1);
    }

    public function test_the_link_opens_the_new_password_form_with_a_confirmation_field(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('New password')
            ->assertSee('Confirm')
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="token"', false);
    }

    public function test_a_wrong_or_missing_token_goes_back_to_the_request_page(): void
    {
        $user = $this->driver();
        Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => 'wrong', 'email' => $user->email]))
            ->assertRedirect(route('driver.password.request'))
            ->assertSessionHasErrors('email');
        $this->get(route('password.reset', ['token' => 'wrong']))
            ->assertRedirect(route('driver.password.request'));
    }

    public function test_a_google_account_cannot_open_a_reset_link(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);
        $user->forceFill(['google_id' => 'g-1'])->save();

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertRedirect(route('driver.password.request'));
    }

    public function test_the_new_password_is_saved_and_the_link_works_once(): void
    {
        $user = $this->driver(['email_verified_at' => null]);
        $token = Password::broker()->createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->post(route('driver.password.update'), $payload)
            ->assertRedirect(route('driver.login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, PasswordHistory::where('user_id', $user->id)->count());

        // The same link again no longer works.
        $this->post(route('driver.password.update'), $payload)->assertSessionHasErrors();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_a_reset_link_stops_working_after_it_expires(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->travel($minutes + 1)->minutes();

        $this->post(route('driver.password.update'), $payload)->assertSessionHasErrors();
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check('Old-Password-1!', $user->fresh()->password));
    }

    public function test_the_confirmation_must_match_and_the_password_must_be_strong(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);

        $this->post(route('driver.password.update'), ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => 'Different-Pass9!'])
            ->assertSessionHasErrors('password');
        $this->post(route('driver.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Old-Password-1!', $user->fresh()->password));
    }

    public function test_the_current_password_cannot_be_reused(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);

        $this->post(route('driver.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Old-Password-1!', 'password_confirmation' => 'Old-Password-1!'])
            ->assertSessionHasErrors('password');
    }

    public function test_a_google_account_cannot_change_a_password_with_an_old_token(): void
    {
        $user = $this->driver();
        $token = Password::broker()->createToken($user);
        $user->forceFill(['google_id' => 'g-1'])->save();

        $this->post(route('driver.password.update'), ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Old-Password-1!', $user->fresh()->password));
    }

    public function test_the_forgot_page_offers_to_create_an_account(): void
    {
        $this->get(route('driver.password.request'))->assertOk()->assertSee(route('driver.register'), false);
    }

    public function test_signing_in_with_google_sends_no_mail(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 's']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-9', 'email' => 'ana.reyes@gmail.com', 'email_verified' => true, 'name' => 'Ana Reyes'], 200),
        ]);
        $this->driver(['email' => 'ana.reyes@gmail.com', 'email_verified_at' => null]);

        $r = $this->get(route('driver.google.redirect'));
        parse_str((string) parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->get(route('driver.google.callback', ['state' => $q['state'], 'code' => 'abc']))->assertRedirect();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    // Sign up: an email that already has an account is refused.

    private function signUp(string $email, array $override = [])
    {
        return $this->from(route('driver.register'))->post(route('driver.register'), $override + [
            'name' => 'Pedro Cruz', 'email' => $email, 'phone' => '09171234567', 'plate_number' => 'XYZ 1234',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]);
    }

    public function test_sign_up_refuses_an_email_that_already_has_an_account(): void
    {
        $this->driver();
        $before = User::count();

        $this->signUp('maria.santos@gmail.com')->assertSessionHasErrors(['email' => 'This Gmail account is already registered. Try signing in.']);
        $this->signUp('MARIA.SANTOS@gmail.com')->assertSessionHasErrors('email');
        $this->signUp('mariasantos@gmail.com')->assertSessionHasErrors('email');
        $this->signUp('maria.santos@googlemail.com')->assertSessionHasErrors('email');

        $this->assertSame($before, User::count());
    }

    public function test_sign_up_refuses_a_staff_or_google_account_email(): void
    {
        User::factory()->create(['role' => User::ROLE_GUARD, 'email' => 'guard.one@gmail.com']);
        $g = $this->driver(['email' => 'ana.reyes@gmail.com']);
        $g->forceFill(['google_id' => 'g-1'])->save();

        $this->signUp('guard.one@gmail.com')->assertSessionHasErrors('email');
        $this->signUp('ana.reyes@gmail.com')->assertSessionHasErrors('email');
    }

    public function test_sign_up_refuses_an_address_waiting_in_pending_email(): void
    {
        $user = $this->driver();
        $user->forceFill(['pending_email' => 'new.address@gmail.com'])->save();

        $this->signUp('new.address@gmail.com')->assertSessionHasErrors('email');
    }

    public function test_the_live_check_says_whether_an_email_is_taken(): void
    {
        $this->driver();

        $this->getJson(route('driver.register.check-email', ['email' => 'maria.santos@gmail.com']))->assertOk()->assertJson(['taken' => true]);
        $this->getJson(route('driver.register.check-email', ['email' => 'mariasantos@gmail.com']))->assertJson(['taken' => true]);
        $this->getJson(route('driver.register.check-email', ['email' => 'free.person@gmail.com']))->assertJson(['taken' => false]);
        $this->getJson(route('driver.register.check-email', ['email' => 'nonsense']))->assertJson(['taken' => false]);
        $this->getJson(route('driver.register.check-email'))->assertJson(['taken' => false]);
    }

    public function test_the_register_email_field_points_at_the_live_check(): void
    {
        $this->get(route('driver.register'))->assertOk()->assertSee(route('driver.register.check-email'), false);
    }
}
