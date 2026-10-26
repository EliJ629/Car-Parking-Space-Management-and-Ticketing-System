<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\VehicleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\ConfirmsSignUp;
use Tests\TestCase;

/** Driver sign up: Gmail only, a link by email that must be clicked before the account is saved, no duplicates, model list by vehicle type. */
class SignUpVerificationTest extends TestCase
{
    use ConfirmsSignUp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array<string, mixed> */
    private function form(array $override = []): array
    {
        return $override + [
            'first_name' => 'Pedro', 'last_name' => 'Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '09171234567', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ];
    }

    public function test_sign_up_saves_nothing_until_the_link_is_clicked(): void
    {
        Mail::fake();

        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));

        // Only a waiting record exists. There is no account, no vehicle, and the password is not usable.
        $this->assertSame(0, User::where('role', 'driver')->count());
        $this->assertSame(0, Vehicle::withTrashed()->count());
        $pending = PendingRegistration::firstOrFail();
        $this->assertSame('pedro.cruz@gmail.com', $pending->email);
        $this->assertNotSame('Driver123x!', $pending->getRawOriginal('password'));

        $link = $this->signUpLink('pedro.cruz@gmail.com');
        $this->assertStringContainsString('/driver/register/confirm/', $link);
        $this->assertStringNotContainsString($pending->token_hash, $link);

        // The driver cannot sign in yet.
        $this->flushSession();
        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->assertSame(0, User::where('role', 'driver')->count());

        $this->get(route('driver.register.sent'))->assertRedirect(route('driver.register'));
    }

    public function test_clicking_the_link_creates_the_account_and_the_vehicle_and_allows_sign_in(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));

        $this->confirmSignUp('pedro.cruz@gmail.com')->assertRedirect(route('driver.login'))->assertSessionHas('status');

        $user = User::where('email', 'pedro.cruz@gmail.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Pedro Cruz', $user->name);
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame('driver', $user->role);
        $vehicle = Vehicle::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('KLM4455', $vehicle->plate_number);
        $this->assertSame('Toyota Vios', $vehicle->make_model);
        $this->assertSame(0, PendingRegistration::count());
        $this->assertGuest();

        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'Driver123x!'])->assertRedirect(route('driver.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_link_works_once_and_a_made_up_link_does_not_work(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $link = $this->signUpLink('pedro.cruz@gmail.com');

        $this->get(route('driver.register.confirm', ['token' => str_repeat('a', 64)]))->assertRedirect(route('driver.login'));
        $this->assertSame(0, User::where('role', 'driver')->count());

        $this->get($link)->assertRedirect(route('driver.login'));
        $this->assertSame(1, User::where('role', 'driver')->count());

        $this->get($link)->assertRedirect(route('driver.login'));
        $this->assertSame(1, User::where('role', 'driver')->count());
        $this->assertSame(1, Vehicle::count());
    }

    public function test_an_old_link_does_not_work_after_24_hours(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $link = $this->signUpLink('pedro.cruz@gmail.com');

        $this->travel(PendingRegistration::HOURS + 1)->hours();
        $this->get($link)->assertRedirect(route('driver.login'))->assertSessionHas('status');
        $this->assertSame(0, User::where('role', 'driver')->count());
        $this->assertSame(0, PendingRegistration::count());
        $this->travelBack();
    }

    public function test_signing_the_form_again_replaces_the_waiting_sign_up_and_the_older_link_dies(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $first = $this->signUpLink('pedro.cruz@gmail.com');

        Mail::fake();
        $this->post(route('driver.register'), $this->form(['first_name' => 'Pedro', 'last_name' => 'Santos']))->assertRedirect(route('driver.register.sent'));
        $this->assertSame(1, PendingRegistration::count());

        $this->get($first);
        $this->assertSame(0, User::where('role', 'driver')->count());

        $this->confirmSignUp('pedro.cruz@gmail.com');
        $this->assertSame('Pedro Santos', User::where('role', 'driver')->value('name'));
    }

    public function test_a_new_link_can_be_asked_for_after_a_minute_and_the_old_one_stops_working(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $old = $this->signUpLink('pedro.cruz@gmail.com');

        $this->get(route('driver.register.sent'))->assertOk()->assertSee('Check your email')->assertSee('pe***');
        $this->post(route('driver.register.resend'))->assertSessionHasErrors('link');

        $this->travel(2)->minutes();
        Mail::fake();
        $this->post(route('driver.register.resend'))->assertSessionHasNoErrors();
        $new = $this->signUpLink('pedro.cruz@gmail.com');
        $this->assertNotSame($old, $new);

        $this->get($old);
        $this->assertSame(0, User::where('role', 'driver')->count());
        $this->get($new);
        $this->assertSame(1, User::where('role', 'driver')->count());
        $this->travelBack();
    }

    public function test_starting_again_clears_the_waiting_sign_up(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));

        $this->post(route('driver.register.restart'))->assertRedirect(route('driver.register'));
        $this->assertSame(0, PendingRegistration::count());
    }

    public function test_signing_in_before_the_link_is_clicked_points_back_to_the_email(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $this->flushSession();

        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'Driver123x!'])
            ->assertRedirect(route('driver.register.sent'));
        $this->assertGuest();

        // A wrong password learns nothing about the waiting sign up.
        $this->flushSession();
        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'Wrong-Pass-1!'])
            ->assertRedirect()->assertSessionHasErrors();
    }

    public function test_the_link_refuses_when_the_email_name_or_plate_was_taken_in_the_meantime(): void
    {
        Mail::fake();

        // 1. The mailbox is registered by someone else first.
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $link = $this->signUpLink('pedro.cruz@gmail.com');
        User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'pedro.cruz@gmail.com']);
        $this->get($link)->assertRedirect(route('driver.login'));
        $this->assertSame(1, User::where('role', 'driver')->count());
        $this->assertSame(0, PendingRegistration::count());

        // 2. The plate is taken by someone else first.
        Mail::fake();
        $this->post(route('driver.register'), $this->form(['email' => 'second.person@gmail.com', 'first_name' => 'Second', 'last_name' => 'Person', 'plate_number' => 'ZZZ 9999']))
            ->assertRedirect(route('driver.register.sent'));
        $link = $this->signUpLink('second.person@gmail.com');
        $other = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $other->vehicles()->create(['plate_number' => 'ZZZ9999', 'vehicle_type' => 'Car', 'color' => 'Black', 'make_model' => 'Toyota Vios', 'contact_number' => '09170000000']);
        $this->get($link)->assertRedirect(route('driver.register'))->assertSessionHasErrors('plate_number');
        $this->assertNull(User::where('email', 'second.person@gmail.com')->first());
        $this->assertSame(0, PendingRegistration::count());
    }

    public function test_an_unconfirmed_old_account_still_gets_the_code_step_when_it_signs_in(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => User::ROLE_DRIVER, 'email' => 'old.account@gmail.com', 'email_verified_at' => null, 'password' => 'Driver123x!']);

        $this->post(route('driver.login'), ['identity' => 'old.account@gmail.com', 'password' => 'Driver123x!'])->assertRedirect(route('driver.verify'));
        $this->assertGuest();

        $code = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $wrong = $code === '123456' ? '654321' : '123456';
        $this->post(route('driver.verify.check'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->post(route('driver.verify.check'), ['code' => $code])->assertRedirect(route('driver.home'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_only_real_looking_gmail_addresses_are_accepted(): void
    {
        Mail::fake();

        foreach (['pedro.cruz@yahoo.com', 'pedro.cruz@example.com', 'pedro.cruz@gmail.co', 'pedro@gmail.com', 'pedro cruz@gmail.com', '.pedro.cruz@gmail.com', 'pedro..cruz@gmail.com'] as $email) {
            $this->post(route('driver.register'), $this->form(['email' => $email]))->assertSessionHasErrors('email');
        }

        $this->assertSame(0, PendingRegistration::count());
        Mail::assertNothingSent();
    }

    public function test_a_gmail_account_name_or_plate_that_is_already_registered_is_refused(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.register.sent'));
        $this->confirmSignUp('pedro.cruz@gmail.com');
        $this->flushSession();

        // Gmail ignores dots and capital letters, so these are the same mailbox.
        $this->post(route('driver.register'), $this->form(['email' => 'Pedro.Cruz@gmail.com', 'first_name' => 'Other', 'last_name' => 'One', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('email');
        $this->post(route('driver.register'), $this->form(['email' => 'pedrocruz@gmail.com', 'first_name' => 'Other', 'last_name' => 'One', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('email');
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'first_name' => '  pedro ', 'last_name' => ' CRUZ ', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('name');
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'first_name' => 'Other', 'last_name' => 'One', 'plate_number' => 'klm-4455']))->assertSessionHasErrors('plate_number');

        $this->assertSame(1, User::where('role', 'driver')->count());
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'first_name' => 'Other', 'last_name' => 'One', 'plate_number' => 'ABC 1234']))->assertSessionHasNoErrors();
        $this->confirmSignUp('someone.else@gmail.com');
        $this->assertSame(2, User::where('role', 'driver')->count());
    }

    public function test_an_address_the_mail_service_refuses_leaves_nothing_behind(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Sender or recipient not allowed'));

        $this->post(route('driver.register'), $this->form())->assertSessionHasErrors('email');

        $this->assertSame(0, User::where('role', 'driver')->count());
        $this->assertSame(0, Vehicle::withTrashed()->count());
        $this->assertSame(0, PendingRegistration::count());
    }

    public function test_the_vehicle_model_follows_the_vehicle_type_and_others_opens_a_text_box(): void
    {
        Mail::fake();
        $this->get(route('driver.register'))->assertOk()->assertSee('Vehicle model')->assertDontSee('Truck')->assertDontSee('Car model');

        // A Hilux is a pickup, not a car; Truck is not a type any more.
        $this->post(route('driver.register'), $this->form(['make_model' => 'Toyota Hilux']))->assertSessionHasErrors('make_model');
        $this->post(route('driver.register'), $this->form(['vehicle_type' => 'Truck', 'make_model' => 'Isuzu Giga']))->assertSessionHasErrors('vehicle_type');

        // Others needs the text.
        $this->post(route('driver.register'), $this->form(['make_model' => 'Others']))->assertSessionHasErrors('make_model_other');
        $this->post(route('driver.register'), $this->form(['color' => 'Others']))->assertSessionHasErrors('color_other');
        $this->assertSame(0, User::where('role', 'driver')->count());

        $this->post(route('driver.register'), $this->form(['make_model' => 'Others', 'make_model_other' => 'BYD Dolphin', 'color' => 'Others', 'color_other' => 'maroon']))
            ->assertSessionHasNoErrors();
        $this->confirmSignUp('pedro.cruz@gmail.com');
        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('BYD Dolphin', $vehicle->make_model);
        $this->assertSame('Maroon', $vehicle->color);

        foreach (VehicleCatalog::forForm() as $type => $models) {
            $this->assertNotEmpty($models, "{$type} has models");
            $this->assertSame('Others', end($models));
        }
    }

    public function test_the_password_needs_ten_characters_a_number_a_capital_and_a_special_character(): void
    {
        Mail::fake();

        foreach (['Short1!A', 'alllowercase1!', 'NoNumberHere!', 'NoSpecial1234'] as $weak) {
            $this->post(route('driver.register'), $this->form(['password' => $weak, 'password_confirmation' => $weak]))->assertSessionHasErrors('password');
        }

        $this->post(route('driver.register'), $this->form())->assertSessionHasNoErrors();
    }
}
