<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Continue to Google Sign-in: the button, the redirect, the callback for old and new drivers, and the refusals. */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['services.google.client_id' => 'id-123', 'services.google.client_secret' => 'secret-456', 'services.google.redirect' => null]);
    }

    private function fakeGoogle(array $info = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200),
            'openidconnect.googleapis.com/*' => Http::response($info + [
                'sub' => 'g-1001', 'email' => 'ana.reyes@gmail.com', 'email_verified' => true, 'name' => 'Ana Reyes',
            ], 200),
        ]);
    }

    /** Runs step 1 and returns the state the callback must carry. */
    private function begin(): string
    {
        $response = $this->get(route('driver.google.redirect'));
        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        return $q['state'];
    }

    private function comeBack(string $state, array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('driver.google.callback', $query + ['state' => $state, 'code' => 'abc']));
    }

    private function details(array $override = []): array
    {
        return $override + [
            'name' => 'Ana Reyes', 'phone' => '09171234567', 'plate_number' => 'QRS 7788',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
        ];
    }

    public function test_login_and_register_pages_show_the_google_button(): void
    {
        $this->get(route('driver.login'))->assertOk()->assertSee('Continue to Google Sign-in');
        $this->get(route('driver.register'))->assertOk()->assertSee('Continue to Google Sign-in');
    }

    public function test_the_redirect_asks_google_for_email_and_profile_only(): void
    {
        $response = $this->get(route('driver.google.redirect'));
        $url = $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $this->assertSame('id-123', $q['client_id']);
        $this->assertSame('openid email profile', $q['scope']);
        $this->assertSame(route('driver.google.callback'), $q['redirect_uri']);
        $this->assertSame(40, strlen($q['state']));
    }

    public function test_without_credentials_the_button_explains_instead_of_failing(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('driver.google.redirect'))
            ->assertRedirect(route('driver.login'))
            ->assertSessionHasErrors('identity');
    }

    public function test_a_wrong_state_is_refused(): void
    {
        $this->fakeGoogle();
        $this->begin();

        $this->comeBack('forged-state')->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_the_backup_cookie_carries_the_check_when_the_session_is_lost(): void
    {
        $this->fakeGoogle();
        $response = $this->get(route('driver.google.redirect'));
        $response->assertCookie('bayline_google_state');
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        // The session did not come back from Google, but the backup cookie did.
        $this->flushSession();
        $this->withCookie('bayline_google_state', $q['state'])
            ->get(route('driver.google.callback', ['state' => $q['state'], 'code' => 'abc']))
            ->assertRedirect(route('driver.google.complete'));
    }

    public function test_an_older_open_attempt_still_works_after_a_second_click(): void
    {
        $this->fakeGoogle();
        $first = $this->begin();
        $second = $this->begin();
        $this->assertNotSame($first, $second);

        // The person finishes in the first Google tab.
        $this->comeBack($first)->assertRedirect(route('driver.google.complete'));
    }

    public function test_an_attempt_can_be_used_only_once(): void
    {
        $this->fakeGoogle();
        $state = $this->begin();

        $this->comeBack($state)->assertRedirect();
        $this->flushSession();
        $this->comeBack($state)->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
    }

    public function test_an_attempt_older_than_fifteen_minutes_is_refused(): void
    {
        $this->fakeGoogle();
        $state = $this->begin();

        $this->travel(16)->minutes();
        $this->comeBack($state)->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
    }

    public function test_only_the_last_five_attempts_are_kept(): void
    {
        $this->fakeGoogle();
        $states = [];
        for ($i = 0; $i < 6; $i++) {
            $states[] = $this->begin();
        }

        $this->comeBack($states[0])->assertSessionHasErrors('identity');
        $this->comeBack($states[5])->assertRedirect(route('driver.google.complete'));
    }

    public function test_a_backup_cookie_with_another_state_is_refused(): void
    {
        $this->fakeGoogle();
        $this->begin();
        $this->flushSession();

        $this->withCookie('bayline_google_state', 'some-other-state')
            ->get(route('driver.google.callback', ['state' => 'forged-state', 'code' => 'abc']))
            ->assertRedirect(route('driver.login'))
            ->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_cancelling_at_google_returns_to_sign_in(): void
    {
        $state = $this->begin();

        $this->get(route('driver.google.callback', ['error' => 'access_denied', 'state' => $state]))
            ->assertRedirect(route('driver.login'))
            ->assertSessionHasErrors('identity');
    }

    public function test_an_existing_driver_is_signed_in_and_linked(): void
    {
        $this->fakeGoogle(['email' => 'anareyes@gmail.com']);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'ana.reyes@gmail.com', 'email_verified_at' => null]);

        $this->comeBack($this->begin())->assertRedirect(route('driver.home'));

        $this->assertAuthenticatedAs($driver);
        $driver->refresh();
        $this->assertSame('g-1001', $driver->google_id);
        $this->assertNotNull($driver->email_verified_at);
    }

    public function test_a_linked_account_is_found_by_google_id(): void
    {
        $this->fakeGoogle(['email' => 'new.name@gmail.com']);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'old.name@gmail.com']);
        $driver->forceFill(['google_id' => 'g-1001'])->save();

        $this->comeBack($this->begin())->assertRedirect(route('driver.home'));
        $this->assertAuthenticatedAs($driver);
    }

    public function test_an_account_linked_to_another_google_id_is_refused(): void
    {
        $this->fakeGoogle();
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'ana.reyes@gmail.com']);
        $driver->forceFill(['google_id' => 'someone-else'])->save();

        $this->comeBack($this->begin())->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_only_gmail_accounts_are_accepted(): void
    {
        $this->fakeGoogle(['email' => 'ana@school.edu.ph']);

        $this->comeBack($this->begin())->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ana@school.edu.ph']);
    }

    public function test_an_unconfirmed_google_email_is_refused(): void
    {
        $this->fakeGoogle(['email_verified' => false]);

        $this->comeBack($this->begin())->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_a_new_driver_finishes_the_form_and_gets_in_without_a_code(): void
    {
        $this->fakeGoogle();

        $this->comeBack($this->begin())->assertRedirect(route('driver.google.complete'));
        $this->get(route('driver.google.complete'))->assertOk()
            ->assertSee('value="ana.reyes@gmail.com" readonly', false)
            ->assertSee('value="Ana Reyes"', false)
            ->assertSee('Signed in with Google');

        $this->post(route('driver.google.store'), $this->details())->assertRedirect(route('driver.home'));

        $user = User::where('email', 'ana.reyes@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-1001', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(User::ROLE_DRIVER, $user->role);
        $this->assertSame('QRS7788', Vehicle::where('user_id', $user->id)->value('plate_number'));
    }

    public function test_the_finish_form_keeps_the_same_checks_as_sign_up(): void
    {
        $this->fakeGoogle();
        $this->comeBack($this->begin());

        $other = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $other->vehicles()->create(['plate_number' => 'QRS7788', 'vehicle_type' => 'Car', 'color' => 'Black', 'make_model' => 'Toyota Vios', 'contact_number' => '09170000000']);

        $this->post(route('driver.google.store'), $this->details())->assertSessionHasErrors('plate_number');
        $this->post(route('driver.google.store'), $this->details(['plate_number' => 'TUV 1122', 'vehicle_type' => 'Truck']))->assertSessionHasErrors('vehicle_type');
        $this->post(route('driver.google.store'), $this->details(['plate_number' => 'TUV 1122', 'phone' => '123']))->assertSessionHasErrors('phone');
        $this->post(route('driver.google.store'), $this->details(['plate_number' => 'TUV 1122', 'name' => $other->name]))->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'ana.reyes@gmail.com']);
        $this->assertGuest();
    }

    public function test_the_finish_form_needs_a_google_sign_in_first(): void
    {
        $this->get(route('driver.google.complete'))->assertRedirect(route('driver.login'));
        $this->post(route('driver.google.store'), $this->details())->assertRedirect(route('driver.login'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cancel_clears_the_waiting_google_details(): void
    {
        $this->fakeGoogle();
        $this->comeBack($this->begin());

        $this->post(route('driver.google.cancel'))->assertRedirect(route('driver.login'));
        $this->get(route('driver.google.complete'))->assertRedirect(route('driver.login'));
    }

    public function test_a_suspended_driver_still_sees_the_account_notice(): void
    {
        $this->fakeGoogle();
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'ana.reyes@gmail.com', 'status' => User::STATUS_SUSPENDED]);

        $this->comeBack($this->begin())->assertRedirect(route('driver.home'));
        $this->assertAuthenticatedAs($driver);
    }

    public function test_a_staff_email_is_not_taken_over_by_google(): void
    {
        $this->fakeGoogle();
        User::factory()->create(['role' => User::ROLE_GUARD, 'email' => 'ana.reyes@gmail.com']);

        $this->comeBack($this->begin())->assertRedirect(route('driver.login'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }
}
