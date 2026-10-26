<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Forgot password for the booth attendant and the security guard. */
class StaffForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'A-brand-new-pass-2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Mail::fake();
    }

    private function staff(string $role = User::ROLE_ATTENDANT, array $override = []): User
    {
        return User::create($override + [
            'name' => 'Ana Reyes', 'username' => $role.'1', 'email' => $role.'.one@example.com', 'phone' => '09171110000',
            'password' => 'Temporary-pass-1', 'role' => $role, 'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function ask(string $email)
    {
        return $this->from(route('staff.password.request'))->post(route('staff.password.email'), ['email' => $email]);
    }

    public function test_the_sign_in_page_links_to_forgot_password(): void
    {
        $this->get(route('staff.login'))->assertOk()->assertSee(route('staff.password.request'), false)->assertSee('Forgot password?');
        $this->get(route('staff.password.request'))->assertOk()->assertSee('Send reset link');
    }

    public function test_an_unknown_email_is_refused_and_nothing_is_sent(): void
    {
        $this->staff();

        $this->ask('nobody@example.com')->assertRedirect(route('staff.password.request'))->assertSessionHasErrors('email');
        $this->ask('not-an-email')->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_attendants_and_guards_get_a_link_to_their_own_email(): void
    {
        foreach ([User::ROLE_ATTENDANT, User::ROLE_GUARD] as $role) {
            $user = $this->staff($role);

            $this->ask(strtoupper($user->email))->assertSessionHasNoErrors()->assertSessionHas('status');

            Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $m) => $m->hasTo($user->email)
                && str_contains($m->link, '/staff/reset-password/') && str_contains($m->link, 'email='));
            $this->assertDatabaseHas('activity_logs', ['action' => 'staff_password_link_sent']);
        }
    }

    public function test_managers_and_drivers_are_not_accepted_here(): void
    {
        $this->staff(User::ROLE_MANAGER);
        User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'maria@gmail.com']);

        $this->ask('manager.one@example.com')->assertSessionHasErrors('email');
        $this->ask('maria@gmail.com')->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_a_closed_account_gets_no_link(): void
    {
        $user = $this->staff(User::ROLE_GUARD, ['status' => User::STATUS_SUSPENDED]);

        $this->ask($user->email)->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_asking_twice_in_a_row_is_throttled(): void
    {
        $user = $this->staff();

        $this->ask($user->email)->assertSessionHasNoErrors();
        $this->ask($user->email)->assertSessionHasErrors('email');
        Mail::assertSent(ResetPasswordMail::class, 1);
    }

    public function test_the_link_opens_the_new_password_form_with_a_confirmation(): void
    {
        $user = $this->staff();
        $token = Password::broker()->createToken($user);

        $this->get(route('staff.password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()->assertSee('New password')->assertSee('name="password_confirmation"', false)->assertSee('name="token"', false);
    }

    public function test_a_wrong_link_goes_back_to_the_request_page(): void
    {
        $user = $this->staff();
        Password::broker()->createToken($user);

        $this->get(route('staff.password.reset', ['token' => 'wrong', 'email' => $user->email]))
            ->assertRedirect(route('staff.password.request'))->assertSessionHasErrors('email');
        $this->get(route('staff.password.reset', ['token' => 'wrong']))->assertRedirect(route('staff.password.request'));
    }

    public function test_the_new_password_is_saved_the_lock_is_lifted_and_the_link_works_once(): void
    {
        $user = $this->staff(User::ROLE_GUARD, ['must_change_password' => true]);
        $user->forceFill(['failed_login_count' => 5, 'locked_until' => now()->addMinutes(10)])->save();
        $token = Password::broker()->createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->post(route('staff.password.update'), $payload)->assertRedirect(route('staff.login'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertFalse($user->isLocked());
        $this->assertTrue(ActivityLog::where('action', 'password_reset')->exists());

        $this->post(route('staff.password.update'), $payload)->assertSessionHasErrors();

        $this->post(route('staff.login'), ['identity' => $user->username, 'password' => self::NEW_PASSWORD])->assertRedirect(route('staff.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_confirmation_must_match_and_the_password_must_be_long_enough(): void
    {
        $user = $this->staff();
        $token = Password::broker()->createToken($user);

        $this->post(route('staff.password.update'), ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => 'Different-pass-2026'])
            ->assertSessionHasErrors('password');
        $this->post(route('staff.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors('password');
        $this->post(route('staff.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Temporary-pass-1', 'password_confirmation' => 'Temporary-pass-1'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Temporary-pass-1', $user->fresh()->password));
    }

    public function test_a_driver_reset_still_uses_the_driver_link(): void
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'maria@gmail.com']);

        $this->from(route('driver.password.request'))->post(route('driver.password.email'), ['email' => 'maria@gmail.com'])->assertSessionHasNoErrors();
        Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $m) => str_contains($m->link, '/driver/reset-password/'));
    }
}
